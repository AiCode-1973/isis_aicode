<?php

declare(strict_types=1);

namespace Isis\Tests\Integration;

use Isis\Auth\Auth;
use Isis\Core\Database;
use Isis\Repositories\ActionRepository;
use Isis\Repositories\ActivityRepository;
use Isis\Repositories\AttachmentRepository;
use Isis\Repositories\CatalogRepository;
use Isis\Repositories\RiskRepository;
use Isis\Services\AccessScope;
use Isis\Services\EvidenceService;

/**
 * Fase 2: inventário, riscos, planos de ação e evidências, com isolamento e escopo por perfil.
 */
final class ComplianceModulesTest extends DatabaseTestCase
{
    public function testDadosDemonstrativosGeramIndicadores(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        Auth::actAs($clinic['users']['admin']);

        $inventory = (new ActivityRepository())->stats();
        self::assertSame(5, $inventory['total']);
        self::assertGreaterThanOrEqual(2, $inventory['sensitive']);
        self::assertSame(1, $inventory['no_basis']);

        $risks = (new RiskRepository())->stats();
        self::assertSame(6, $risks['total']);
        self::assertSame(1, $risks['critical']);
        $heat = (new RiskRepository())->heatmap();
        self::assertSame(1, $heat[4][4]);

        $actions = (new ActionRepository())->stats();
        self::assertSame(7, $actions['total']);
        self::assertSame(1, $actions['overdue']);
        self::assertSame(2, $actions['done']);
        self::assertSame(2, $actions['done_on_time']);
    }

    public function testCodigoSequencialPorEmpresa(): void
    {
        [$a, $b] = $this->seedTwoCompanies();
        Auth::actAs($b['users']['admin']);
        $id = (new ActivityRepository())->create(['name' => 'Nova', 'purpose' => 'Teste', 'status' => 'rascunho'], $this->noRelations());
        // A loja tinha 3 operações; a clínica, 5. A numeração é independente.
        self::assertSame(4, (int) Database::value('SELECT seq FROM processing_activities WHERE id = ?', [$id]));
    }

    public function testCatalogoDeOutraEmpresaNaoEntraNaOperacao(): void
    {
        [$a, $b] = $this->seedTwoCompanies();
        $foreignCategory = (int) Database::value('SELECT id FROM data_categories WHERE tenant_id = ? LIMIT 1', [$b['tenant']['id']]);
        Auth::actAs($a['users']['admin']);

        self::assertSame([], (new CatalogRepository('dados'))->ownIds([$foreignCategory]));

        $this->expectException(\PDOException::class);
        (new ActivityRepository())->create(['name' => 'X', 'purpose' => 'Y', 'status' => 'rascunho'],
            ['data' => [$foreignCategory], 'subjects' => [], 'systems' => [], 'departments' => [], 'sharings' => []]);
    }

    public function testRiscoEAcaoDeOutraEmpresaSaoInvisiveis(): void
    {
        [$a, $b] = $this->seedTwoCompanies();
        $foreignRisk = (int) Database::value('SELECT id FROM risks WHERE tenant_id = ? LIMIT 1', [$b['tenant']['id']]);
        $foreignAction = (int) Database::value('SELECT id FROM actions WHERE tenant_id = ? LIMIT 1', [$b['tenant']['id']]);
        Auth::actAs($a['users']['admin']);

        self::assertNull((new RiskRepository())->findFull($foreignRisk));
        self::assertNull((new ActionRepository())->findFull($foreignAction));
        foreach ((new ActionRepository())->search([]) as $x) {
            self::assertSame((int) $a['tenant']['id'], (int) $x['tenant_id']);
        }
        $this->expectException(\PDOException::class);
        (new ActionRepository())->create(['title' => 'Vínculo indevido', 'risk_id' => $foreignRisk, 'priority' => 'media', 'status' => 'pendente']);
    }

    public function testGestorSoAlteraOQueEDoSeuSetor(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        $manager = $clinic['users']['gestor']; // setor: Recepção e Atendimento
        Auth::actAs($manager);
        $own = Database::fetch("SELECT * FROM processing_activities WHERE tenant_id = ? AND name = 'Agendamento de consultas'", [$clinic['tenant']['id']]);
        $other = Database::fetch("SELECT * FROM processing_activities WHERE tenant_id = ? AND name = 'Gestão de colaboradores e folha de pagamento'", [$clinic['tenant']['id']]);
        self::assertTrue(AccessScope::canEditActivity($own));
        self::assertFalse(AccessScope::canEditActivity($other));

        Auth::actAs($clinic['users']['encarregado']);
        self::assertTrue(AccessScope::canEditActivity($other), 'O encarregado altera qualquer operação.');
        Auth::actAs($clinic['users']['auditor']);
        self::assertFalse(AccessScope::canEditActivity($own), 'Auditor só lê.');
    }

    public function testColaboradorVeEAtualizaSoAsProprias(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        $collaborator = $clinic['users']['colaborador'];
        Auth::actAs($collaborator);
        self::assertTrue(AccessScope::onlyOwnActions());

        $mine = (new ActionRepository())->search(['only_owner' => (int) $collaborator['id']]);
        self::assertCount(1, $mine);
        self::assertTrue(AccessScope::canProgressAction($mine[0]));
        self::assertFalse(AccessScope::canEditAction($mine[0]));

        $others = Database::fetch('SELECT * FROM actions WHERE tenant_id = ? AND owner_user_id <> ? LIMIT 1', [$clinic['tenant']['id'], $collaborator['id']]);
        self::assertFalse(AccessScope::canViewAction($others));
        self::assertFalse(AccessScope::canProgressAction($others));
    }

    public function testEvidenciaGuardaHashERecusaTipoProibido(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        Auth::actAs($clinic['users']['admin']);
        $actionId = (int) Database::value('SELECT MIN(id) FROM actions WHERE tenant_id = ?', [$clinic['tenant']['id']]);

        $tmp = tempnam(sys_get_temp_dir(), 'isis');
        file_put_contents($tmp, "Relatório de revisão de acessos\n");
        $id = EvidenceService::persist($tmp, 'revisao.txt', (int) filesize($tmp), 'action', $actionId, null);
        $saved = (new AttachmentRepository())->find($id);
        self::assertSame(hash_file('sha256', $tmp), $saved['sha256']);
        self::assertFileExists(EvidenceService::path($saved));
        self::assertStringNotContainsString('revisao', $saved['stored_name']);

        file_put_contents($tmp, "<?php echo 'x';");
        try {
            EvidenceService::persist($tmp, 'script.php', (int) filesize($tmp), 'action', $actionId, null);
            self::fail('Deveria recusar .php');
        } catch (\DomainException) {
            self::assertTrue(true);
        }
        file_put_contents($tmp, "%PDF-falso mas com extensao trocada\n");
        try {
            EvidenceService::persist($tmp, 'disfarcado.exe', (int) filesize($tmp), 'action', $actionId, null);
            self::fail('Deveria recusar extensão não permitida');
        } catch (\DomainException) {
            self::assertTrue(true);
        }
        EvidenceService::delete($saved);
        self::assertFileDoesNotExist(EvidenceService::path($saved));
        @unlink($tmp);
    }

    public function testExcluirOperacaoMantemRiscosSemVinculo(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        Auth::actAs($clinic['users']['admin']);
        $activityId = (int) Database::value("SELECT id FROM processing_activities WHERE tenant_id = ? AND name = 'Prontuário eletrônico do paciente'", [$clinic['tenant']['id']]);
        $risksBefore = (int) Database::value('SELECT COUNT(*) FROM risks WHERE tenant_id = ?', [$clinic['tenant']['id']]);

        (new ActivityRepository())->remove($activityId);

        self::assertSame($risksBefore, (int) Database::value('SELECT COUNT(*) FROM risks WHERE tenant_id = ?', [$clinic['tenant']['id']]));
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM risks WHERE activity_id = ?', [$activityId]));
        self::assertSame(0, (int) Database::value('SELECT COUNT(*) FROM activity_sharings WHERE activity_id = ?', [$activityId]));
    }

    public function testExcluirSetorEmUsoDesvinculaRegistros(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        Auth::actAs($clinic['users']['admin']);
        $dept = (int) Database::value("SELECT id FROM departments WHERE tenant_id = ? AND name = 'Recepção e Atendimento'", [$clinic['tenant']['id']]);
        self::assertGreaterThan(0, (int) Database::value('SELECT COUNT(*) FROM processing_activities WHERE department_id = ?', [$dept]));

        (new \Isis\Repositories\DepartmentRepository())->remove($dept);

        self::assertNull(Database::value('SELECT id FROM departments WHERE id = ?', [$dept]));
        foreach (['users', 'processing_activities', 'risks', 'actions'] as $table) {
            self::assertSame(0, (int) Database::value("SELECT COUNT(*) FROM {$table} WHERE department_id = ?", [$dept]), $table);
        }
    }

    private function noRelations(): array
    {
        return ['data' => [], 'subjects' => [], 'systems' => [], 'departments' => [], 'sharings' => []];
    }
}
