<?php

declare(strict_types=1);

namespace Isis\Tests\Integration;

use Isis\Auth\Auth;
use Isis\Core\Database;
use Isis\Repositories\PolicyRepository;
use Isis\Repositories\TrainingRepository;
use Isis\Repositories\VendorRepository;
use Isis\Services\Templates;
use Isis\Services\TrainingService;
use Isis\Services\VendorQuestionnaire;

/**
 * Fase 4: fornecedores, políticas (versões, aprovação, ciência) e treinamentos (avaliação e certificado).
 */
final class ProgramModulesTest extends DatabaseTestCase
{
    public function testDadosDemonstrativosDaFase4(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        Auth::actAs($clinic['users']['admin']);
        $vendors = (new VendorRepository())->stats();
        self::assertSame(3, $vendors['active']);
        self::assertSame(1, $vendors['unassessed']);
        $policies = (new PolicyRepository())->stats();
        self::assertSame(2, $policies['published']);
        self::assertSame(1, $policies['awaiting']);
        $training = (new TrainingRepository())->stats();
        self::assertSame(5, $training['enrolled']);
        self::assertSame(2, $training['completed']);
    }

    public function testAvaliacaoAtualizaRiscoDoFornecedor(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        Auth::actAs($clinic['users']['admin']);
        $repo = new VendorRepository();
        $vendor = $repo->search(['unassessed' => true])[0];
        $answers = array_fill_keys(array_keys(VendorQuestionnaire::QUESTIONS), 'nao');
        $result = VendorQuestionnaire::evaluate($answers, (bool) $vendor['sensitive_data']);
        $repo->addAssessment((int) $vendor['id'], $answers, $result['score'], $result['level'], null, (int) $clinic['users']['admin']['id'], null);
        $fresh = $repo->findFull((int) $vendor['id']);
        self::assertSame('alto', $fresh['risk_level']);
        self::assertSame(0, (int) $fresh['last_score']);
        self::assertCount(1, $fresh['assessments']);
    }

    public function testFornecedorDeOutraEmpresaEInvisivel(): void
    {
        [$clinic, $store] = $this->seedTwoCompanies();
        $foreign = (int) Database::value('SELECT id FROM vendors WHERE tenant_id = ? LIMIT 1', [$store['tenant']['id']]);
        Auth::actAs($clinic['users']['admin']);
        self::assertNull((new VendorRepository())->findFull($foreign));
        self::assertSame(0, (new VendorRepository())->removeContract($foreign, 1));
        $this->expectException(\PDOException::class);
        (new VendorRepository())->create(['name' => 'X'], [(int) Database::value('SELECT id FROM processing_activities WHERE tenant_id = ? LIMIT 1', [$store['tenant']['id']])]);
    }

    public function testFluxoDeVersaoAprovacaoECiencia(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        Auth::actAs($clinic['users']['encarregado']);
        $repo = new PolicyRepository();
        $id = $repo->create(['title' => 'Política de Teste', 'category' => 'outro', 'review_months' => 6, 'requires_ack' => 1], 'Texto da versão 1 com conteúdo suficiente.', 'v1', (int) $clinic['users']['encarregado']['id']);
        self::assertNull($repo->findFull($id)['current_version_id'], 'Rascunho não é vigente.');

        $v1 = $repo->editableVersion($id);
        $repo->setVersionStatus((int) $v1['id'], 'em_aprovacao');
        $validUntil = $repo->approve($repo->findOrFail($id), $repo->pendingApproval($id), (int) $clinic['users']['admin']['id'], null);
        self::assertSame(date('Y-m-d', strtotime('+6 months')), $validUntil);
        self::assertSame((int) $v1['id'], (int) $repo->findFull($id)['current_version_id']);

        // Ciência
        $collaborator = (int) $clinic['users']['colaborador']['id'];
        self::assertContains($id, array_map('intval', array_column($repo->pendingForUser($collaborator), 'id')));
        self::assertTrue($repo->acknowledge((int) $v1['id'], $collaborator, '127.0.0.1'));
        self::assertFalse($repo->acknowledge((int) $v1['id'], $collaborator, '127.0.0.1'), 'Ciência não duplica.');
        self::assertNotContains($id, array_map('intval', array_column($repo->pendingForUser($collaborator), 'id')));

        // Nova versão: a anterior vira substituída e a ciência precisa ser renovada
        $v2 = $repo->saveDraft($id, 'Texto da versão 2 revisado.', 'v2', (int) $clinic['users']['encarregado']['id']);
        $repo->setVersionStatus($v2, 'em_aprovacao');
        $repo->approve($repo->findOrFail($id), $repo->pendingApproval($id), (int) $clinic['users']['admin']['id'], null);
        self::assertSame('substituida', $repo->version((int) $v1['id'])['status']);
        self::assertContains($id, array_map('intval', array_column($repo->pendingForUser($collaborator), 'id')));
    }

    public function testRascunhoNaoApareceParaQuemSoLe(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        Auth::actAs($clinic['users']['colaborador']);
        $titles = array_column((new PolicyRepository())->search((int) $clinic['users']['colaborador']['id'], true), 'title');
        self::assertNotContains('Política de Segurança da Informação', $titles, 'A política aguardando aprovação não é exibida ao colaborador.');
        self::assertContains('Política de Privacidade e Proteção de Dados Pessoais', $titles);
    }

    public function testAprovacaoNaAvaliacaoGeraCertificadoVerificavel(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        $user = $clinic['users']['colaborador'];
        Auth::actAs($user);
        $repo = new TrainingRepository();
        $module = $repo->modules()[0];
        $enrollment = $repo->enrollment((int) $module['id'], (int) $user['id']);
        $questions = $repo->questions((int) $module['id']);

        $wrong = [];
        foreach ($questions as $q) {
            $wrong[$q['id']] = (string) (((int) $q['correct_index'] + 1) % count($q['options']));
        }
        $fail = TrainingService::submit($module, $enrollment, $wrong);
        self::assertFalse($fail['passed']);
        self::assertSame('reprovado', $repo->enrollment((int) $module['id'], (int) $user['id'])['status']);

        $right = array_column($questions, 'correct_index', 'id');
        $pass = TrainingService::submit($module, $repo->enrollment((int) $module['id'], (int) $user['id']), array_map('strval', $right));
        self::assertTrue($pass['passed']);
        self::assertSame(100, $pass['score']);
        self::assertMatchesRegularExpression('/^ISIS-[A-Z0-9]{4}-[A-Z0-9]{4}$/', (string) $pass['certificate']);
        $after = $repo->enrollment((int) $module['id'], (int) $user['id']);
        self::assertSame('concluido', $after['status']);
        self::assertSame(2, (int) $after['attempts']);

        $public = TrainingRepository::verifyCertificate((string) $pass['certificate']);
        self::assertSame($user['name'], $public['user_name']);
        self::assertNull(TrainingRepository::verifyCertificate('ISIS-AAAA-BBBB'));
    }

    public function testCertificadoDeOutraEmpresaNaoAbreNoAmbiente(): void
    {
        [$clinic, $store] = $this->seedTwoCompanies();
        $code = (string) Database::value('SELECT certificate_code FROM training_enrollments WHERE tenant_id = ? AND certificate_code IS NOT NULL LIMIT 1', [$store['tenant']['id']]);
        self::assertNotSame('', $code);
        Auth::actAs($clinic['users']['admin']);
        self::assertNull((new TrainingRepository())->certificate($code));
    }

    public function testModelosNaoDuplicam(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        Auth::actAs($clinic['users']['admin']);
        $repo = new TrainingRepository();
        self::assertTrue($repo->titleExists(Templates::lgpdTraining()['title']));
        self::assertTrue((new PolicyRepository())->titleExists(Templates::policies('X')[0]['title']));
    }
}
