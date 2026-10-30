<?php

declare(strict_types=1);

namespace Isis\Tests\Integration;

use Isis\Auth\Auth;
use Isis\Auth\TenantContext;
use Isis\Core\Database;
use Isis\Core\Mailer;
use Isis\Repositories\IncidentRepository;
use Isis\Repositories\RequestRepository;
use Isis\Services\RequestService;

/**
 * Fase 3: solicitações de titulares (criptografia, prazos, canal público, isolamento) e incidentes.
 */
final class PrivacyModulesTest extends DatabaseTestCase
{
    private function publicRequest(int $tenantId, string $email = 'Maria.Titular@Exemplo.example'): array
    {
        return TenantContext::runAs($tenantId, static fn (): array => RequestService::register([
            'right_type' => 'acesso', 'channel' => 'portal', 'requester_relation' => 'titular',
            'requester_name' => 'Maria Titular', 'requester_email' => $email, 'requester_phone' => '11 99999-0000',
            'requester_document' => '123.456.789-09', 'description' => 'Quero acesso aos meus dados de saúde.',
        ], true));
    }

    public function testDadosDoTitularFicamCriptografadosNoBanco(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        $created = $this->publicRequest((int) $clinic['tenant']['id']);
        $row = Database::fetch('SELECT * FROM dsar_requests WHERE id = ?', [$created['id']]);
        $raw = json_encode($row, JSON_UNESCAPED_UNICODE);
        foreach (['Maria Titular', 'maria.titular', '123.456.789-09', 'dados de saúde', '99999'] as $plain) {
            self::assertStringNotContainsStringIgnoringCase($plain, (string) $raw);
        }
        self::assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $created['protocol']);

        Auth::actAs($clinic['users']['encarregado']);
        $item = (new RequestRepository())->findFull($created['id']);
        self::assertSame('Maria Titular', $item['requester_name']);
        self::assertSame('123.456.789-09', $item['requester_document']);
        self::assertSame(date('Y-m-d', strtotime('+15 days')), $item['due_date']);
    }

    public function testCanalPublicoAvisaTitularEEncarregadoSemExporDados(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        $created = $this->publicRequest((int) $clinic['tenant']['id']);
        $to = array_column(Mailer::$sent, 'to');
        self::assertContains('Maria.Titular@Exemplo.example', $to);
        self::assertContains($clinic['tenant']['dpo_email'], $to);
        $dpoMail = Mailer::$sent[array_search($clinic['tenant']['dpo_email'], $to, true)];
        self::assertStringContainsString($created['protocol'], $dpoMail['html']);
        self::assertStringNotContainsString('Maria Titular', $dpoMail['html'], 'O aviso ao encarregado não traz dados do titular.');
        // A criação feita pelo canal público fica na auditoria sem dados pessoais.
        $log = Database::fetch("SELECT * FROM audit_logs WHERE action = 'request.created' ORDER BY id DESC LIMIT 1");
        self::assertSame('Titular (canal público)', $log['user_name']);
        self::assertStringNotContainsString('Maria', (string) $log['changes']);
    }

    public function testAcompanhamentoExigeProtocoloEEmailCorretosEDaMesmaEmpresa(): void
    {
        [$clinic, $store] = $this->seedTwoCompanies();
        $created = $this->publicRequest((int) $clinic['tenant']['id']);

        $found = TenantContext::runAs((int) $clinic['tenant']['id'], static fn () => (new RequestRepository())->findForRequester(strtolower($created['protocol']), 'maria.titular@exemplo.EXAMPLE'));
        self::assertNotNull($found, 'Protocolo e e-mail sem diferenciar maiúsculas.');
        self::assertNull(TenantContext::runAs((int) $clinic['tenant']['id'], static fn () => (new RequestRepository())->findForRequester($created['protocol'], 'outra@pessoa.example')));
        self::assertNull(TenantContext::runAs((int) $store['tenant']['id'], static fn () => (new RequestRepository())->findForRequester($created['protocol'], 'maria.titular@exemplo.example')), 'Pelo canal de outra empresa o protocolo não existe.');

        Auth::actAs($store['users']['admin']);
        self::assertNull((new RequestRepository())->findFull($created['id']));
    }

    public function testRespostaEncerraEFicaVisivelAoTitular(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        $created = $this->publicRequest((int) $clinic['tenant']['id']);
        Auth::actAs($clinic['users']['encarregado'], $clinic['tenant']);
        $repo = new RequestRepository();
        Mailer::$sent = [];
        RequestService::respond($repo->findFull($created['id']), 'Segue o relatório com seus dados.', 'respondida', true);

        $item = $repo->findFull($created['id']);
        self::assertSame('respondida', $item['status']);
        self::assertNotNull($item['responded_at']);
        self::assertSame('Maria.Titular@Exemplo.example', Mailer::$sent[0]['to']);
        $public = array_column($repo->events($created['id'], true), 'event_type');
        self::assertContains('resposta', $public);
        self::assertSame(1, $repo->stats()['answered_on_time'] >= 1 ? 1 : 0);
    }

    public function testSolicitacaoVencidaApareceNosIndicadores(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        Auth::actAs($clinic['users']['admin']);
        // Dados demonstrativos: um pedido recebido há 20 dias e ainda aberto.
        $stats = (new RequestRepository())->stats();
        self::assertGreaterThanOrEqual(1, $stats['overdue']);
        self::assertCount($stats['overdue'], (new RequestRepository())->search(['overdue' => true]));
    }

    public function testIncidenteRelevanteTemPrazoAnpdEDetalhesCriptografados(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        Auth::actAs($clinic['users']['admin']);
        $repo = new IncidentRepository();
        $id = $repo->create([
            'title' => 'Notebook furtado', 'category' => 'furto', 'description' => 'Notebook com planilha de pacientes furtado no estacionamento.',
            'detected_at' => '2026-10-30 08:00:00', 'status' => 'comunicado', 'reported_by' => (int) $clinic['users']['colaborador']['id'],
        ]);
        $raw = (string) json_encode(Database::fetch('SELECT * FROM incidents WHERE id = ?', [$id]), JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('estacionamento', $raw);
        self::assertSame('Notebook com planilha de pacientes furtado no estacionamento.', $repo->findFull($id)['description']);

        $repo->save($id, ['risk_to_subjects' => 'relevante', 'anpd_deadline' => \Isis\Support\Deadline::addBusinessDays(new \DateTimeImmutable('2026-10-30'), 3)]);
        self::assertSame('2026-11-04', $repo->findFull($id)['anpd_deadline']);
        self::assertGreaterThanOrEqual(1, $repo->stats()['anpd_pending']);
    }

    public function testIncidentesSaoIsoladosEntreEmpresas(): void
    {
        [$clinic, $store] = $this->seedTwoCompanies();
        $foreign = (int) Database::value('SELECT id FROM incidents WHERE tenant_id = ? LIMIT 1', [$store['tenant']['id']]);
        Auth::actAs($clinic['users']['admin']);
        self::assertNull((new IncidentRepository())->findFull($foreign));
        foreach ((new IncidentRepository())->search([]) as $incident) {
            self::assertSame((int) $clinic['tenant']['id'], (int) $incident['tenant_id']);
        }
        $this->expectException(\PDOException::class);
        (new \Isis\Repositories\ActionRepository())->create(['title' => 'X', 'incident_id' => $foreign, 'priority' => 'media', 'status' => 'pendente']);
    }

    public function testColaboradorComunicaMasSoVeOsProprios(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        $collaborator = $clinic['users']['colaborador'];
        Auth::actAs($collaborator);
        self::assertTrue(Auth::can(\Isis\Auth\Permission::INCIDENTS_REPORT));
        self::assertFalse(Auth::can(\Isis\Auth\Permission::INCIDENTS_VIEW));
        self::assertFalse(Auth::can(\Isis\Auth\Permission::INCIDENTS_MANAGE));
        self::assertFalse(Auth::can(\Isis\Auth\Permission::REQUESTS_VIEW));
        foreach ((new IncidentRepository())->search(['reported_by' => (int) $collaborator['id']]) as $incident) {
            self::assertSame((int) $collaborator['id'], (int) $incident['reported_by']);
        }
    }
}
