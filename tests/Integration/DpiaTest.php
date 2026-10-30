<?php

declare(strict_types=1);

namespace Isis\Tests\Integration;

use Isis\Auth\Auth;
use Isis\Auth\Permission as P;
use Isis\Auth\Role;
use Isis\Controllers\DpiaController;
use Isis\Core\Database;
use Isis\Core\HttpException;
use Isis\Core\Request;
use Isis\Repositories\ActivityRepository;
use Isis\Repositories\DpiaRepository;
use Isis\Services\DpiaService;

/**
 * Relatório de Impacto à Proteção de Dados Pessoais: indicadores, preenchimento, aprovação com
 * versão congelada, revisão e isolamento entre empresas.
 */
final class DpiaTest extends DatabaseTestCase
{
    private const array FILLED = [
        'nature' => 'Coleta no atendimento e registro no prontuário eletrônico.',
        'scope' => 'Cerca de 8 mil pacientes por ano; guarda por 20 anos.',
        'context' => 'Pacientes esperam sigilo; há crianças atendidas.',
        'purpose' => 'Prestar assistência à saúde com segurança.',
        'necessity' => 'Tutela da saúde (art. 11, II, f); só os dados clínicos necessários.',
        'conclusion' => 'aceitavel_com_medidas',
        'dpo_opinion' => 'De acordo, desde que o plano de ação seja concluído.',
    ];

    public function testIndicadoresDoInventarioSugeremRipd(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        Auth::actAs($clinic['users']['admin']);
        $stats = (new DpiaRepository())->stats();
        self::assertSame(1, $stats['approved'], 'A demonstração traz o RIPD do prontuário aprovado.');
        $candidates = DpiaService::candidates();
        self::assertNotEmpty($candidates, 'A clínica demo trata dados de saúde.');
        $withSensitive = array_filter($candidates, static fn (array $c): bool => in_array('dados_sensiveis', $c['triggers'], true));
        self::assertNotEmpty($withSensitive);

        $first = reset($withSensitive);
        (new DpiaRepository())->create(['title' => 'RIPD teste', 'triggers' => '[]'], [$first['id']]);
        self::assertNotContains($first['id'], array_column(DpiaService::candidates(), 'id'), 'Operação com RIPD sai da lista de sugestões.');
    }

    public function testFluxoCompletoComVersaoCongelada(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        Auth::actAs($clinic['users']['encarregado']);
        $activityId = DpiaService::candidates()[0]['id'];
        $controller = new DpiaController();

        $controller->store($this->post('/ripd', ['title' => 'RIPD do prontuário', 'activity_ids' => [(string) $activityId], 'triggers' => ['dados_sensiveis', 'invalido']]));
        $repo = new DpiaRepository();
        $id = (int) Database::value('SELECT MAX(id) FROM dpias WHERE tenant_id = ?', [$clinic['tenant']['id']]);
        $dpia = $repo->findFull($id);
        self::assertSame(['dados_sensiveis'], $dpia['triggers'], 'Indicador desconhecido é descartado.');
        self::assertSame([$activityId], $dpia['activity_ids']);
        self::assertNotEmpty(DpiaService::missing($dpia));

        // Incompleto: não sai do rascunho
        $controller->submit($this->post("/ripd/{$id}/enviar"), $id);
        self::assertSame('rascunho', $repo->findFull($id)['status']);

        $controller->update($this->post("/ripd/{$id}", ['title' => 'RIPD do prontuário', 'activity_ids' => [(string) $activityId], 'triggers' => ['dados_sensiveis']] + self::FILLED), $id);
        self::assertSame([], DpiaService::missing($repo->findFull($id)));
        $controller->submit($this->post("/ripd/{$id}/enviar"), $id);
        self::assertSame('em_aprovacao', $repo->findFull($id)['status']);

        // Em aprovação não se edita
        try {
            $controller->update($this->post("/ripd/{$id}", ['title' => 'Outro'] + self::FILLED), $id);
            self::fail('Edição em aprovação deveria ser recusada.');
        } catch (HttpException $e) {
            self::assertSame(409, $e->status);
        }

        Auth::actAs($clinic['users']['admin']);
        $controller->approve($this->post("/ripd/{$id}/aprovar", ['notes' => 'Aprovado em reunião']), $id);
        $approved = $repo->findFull($id);
        self::assertSame('aprovado', $approved['status']);
        self::assertSame(1, (int) $approved['approved_version']);
        $v1 = $repo->version($id, 1);
        self::assertSame(hash('sha256', $v1['snapshot']), $v1['sha256']);
        $snapshot = json_decode($v1['snapshot'], true);
        self::assertSame('RIPD do prontuário', $snapshot['dpia']['title']);
        self::assertSame($activityId, $snapshot['activities'][0]['id']);

        // Mudança posterior no inventário não altera a versão aprovada
        Database::execute('UPDATE processing_activities SET name = ? WHERE tenant_id = ? AND id = ?', ['Nome alterado', $clinic['tenant']['id'], $activityId]);
        self::assertSame($snapshot, json_decode($repo->version($id, 1)['snapshot'], true));

        // Revisão abre a versão 2 e mantém a 1 guardada; RIPD aprovado não pode ser excluído
        Auth::actAs($clinic['users']['encarregado']);
        $controller->revise($this->post("/ripd/{$id}/revisar"), $id);
        $revised = $repo->findFull($id);
        self::assertSame(2, (int) $revised['version']);
        self::assertSame('rascunho', $revised['status']);
        self::assertSame(1, (int) $revised['approved_version']);
        $controller->delete($this->post("/ripd/{$id}/excluir"), $id);
        self::assertNotNull($repo->find($id));

        $actions = array_column(Database::fetchAll('SELECT action FROM audit_logs WHERE tenant_id = ? AND entity_type = ?', [$clinic['tenant']['id'], 'dpia']), 'action');
        foreach (['dpia.created', 'dpia.updated', 'dpia.submitted', 'dpia.approved', 'dpia.revision'] as $expected) {
            self::assertContains($expected, $actions);
        }
    }

    public function testDevolucaoExigeMotivoEVoltaParaRascunho(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        Auth::actAs($clinic['users']['admin']);
        $repo = new DpiaRepository();
        $activityId = DpiaService::candidates()[0]['id'];
        $id = $repo->create(['title' => 'RIPD', 'triggers' => '[]', 'status' => 'em_aprovacao'] + self::FILLED, [$activityId]);
        $controller = new DpiaController();

        $controller->reject($this->post("/ripd/{$id}/devolver", ['notes' => '']), $id);
        self::assertSame('em_aprovacao', $repo->findFull($id)['status']);
        $controller->reject($this->post("/ripd/{$id}/devolver", ['notes' => 'Faltam os riscos de acesso']), $id);
        $back = $repo->findFull($id);
        self::assertSame('rascunho', $back['status']);
        self::assertSame('Faltam os riscos de acesso', $back['returned_notes']);

        // Rascunho nunca aprovado pode ser excluído
        $controller->delete($this->post("/ripd/{$id}/excluir"), $id);
        self::assertNull($repo->find($id));
    }

    public function testRipdDeOutraEmpresaEInvisivel(): void
    {
        [$clinic, $store] = $this->seedTwoCompanies();
        Auth::actAs($store['users']['admin']);
        $storeActivity = (int) Database::value('SELECT id FROM processing_activities WHERE tenant_id = ? LIMIT 1', [$store['tenant']['id']]);
        $foreign = (new DpiaRepository())->create(['title' => 'RIPD da loja', 'triggers' => '[]'], [$storeActivity]);

        Auth::actAs($clinic['users']['admin']);
        self::assertNull((new DpiaRepository())->findFull($foreign));
        self::assertNull((new DpiaRepository())->version($foreign, 1));
        self::assertSame([], (new DpiaRepository())->forActivity($storeActivity));

        // Operação de outra empresa não entra no RIPD
        $controller = new DpiaController();
        $controller->store($this->post('/ripd', ['title' => 'Tentativa', 'activity_ids' => [(string) $storeActivity]]));
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM dpias WHERE tenant_id = ? AND title = ?', [$clinic['tenant']['id'], 'Tentativa']));
        $this->expectException(\PDOException::class);
        (new DpiaRepository())->create(['title' => 'Direto', 'triggers' => '[]'], [$storeActivity]);
    }

    public function testPermissoesPorPerfil(): void
    {
        self::assertTrue(Role::Dpo->can(P::DPIA_APPROVE));
        self::assertTrue(Role::Admin->can(P::DPIA_APPROVE));
        self::assertTrue(Role::Manager->can(P::DPIA_VIEW));
        self::assertFalse(Role::Manager->can(P::DPIA_EDIT));
        self::assertTrue(Role::Auditor->can(P::DPIA_VIEW));
        self::assertFalse(Role::Auditor->can(P::DPIA_EDIT));
        self::assertFalse(Role::Collaborator->can(P::DPIA_VIEW));
    }

    public function testDocumentoReuneOperacaoERiscos(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        Auth::actAs($clinic['users']['admin']);
        $activityId = (int) Database::value('SELECT activity_id FROM risks WHERE tenant_id = ? AND activity_id IS NOT NULL LIMIT 1', [$clinic['tenant']['id']]);
        $id = (new DpiaRepository())->create(['title' => 'RIPD', 'triggers' => '["dados_sensiveis"]'], [$activityId]);
        $doc = DpiaService::document((new DpiaRepository())->findFull($id));
        self::assertSame((new ActivityRepository())->find($activityId)['name'], $doc['activities'][0]['name']);
        self::assertNotEmpty($doc['risks']);
        self::assertSame(['Dados pessoais sensíveis'], $doc['dpia']['triggers']);
        self::assertSame($clinic['tenant']['legal_name'], $doc['tenant']['legal_name']);
    }

    /** @param array<string, mixed> $data */
    private function post(string $path, array $data = []): Request
    {
        return new Request('POST', $path, [], $data);
    }
}
