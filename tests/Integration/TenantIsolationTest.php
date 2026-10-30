<?php

declare(strict_types=1);

namespace Isis\Tests\Integration;

use Isis\Auth\Auth;
use Isis\Auth\TenantContext;
use Isis\Core\Database;
use Isis\Core\HttpException;
use Isis\Repositories\AuditRepository;
use Isis\Repositories\DepartmentRepository;
use Isis\Repositories\InvitationRepository;
use Isis\Repositories\UserRepository;

/**
 * Garante que uma empresa não lê, altera nem vincula dados de outra.
 */
final class TenantIsolationTest extends DatabaseTestCase
{
    public function testSemEmpresaNaSessaoNadaEAcessado(): void
    {
        $this->seedTwoCompanies();
        $this->expectException(\LogicException::class);
        (new DepartmentRepository())->all();
    }

    public function testListagensMostramSoDadosDaPropriaEmpresa(): void
    {
        [$a, $b] = $this->seedTwoCompanies();
        Auth::actAs($a['users']['admin']);

        $emails = array_column((new UserRepository())->listWithDepartment(), 'email');
        self::assertCount(5, $emails);
        foreach ($emails as $email) {
            self::assertStringEndsWith('@vidaplena.example', $email);
        }
        foreach ((new DepartmentRepository())->all() as $dept) {
            self::assertSame((int) $a['tenant']['id'], (int) $dept['tenant_id']);
        }
        foreach ((new AuditRepository())->recent(50) as $log) {
            self::assertSame((int) $a['tenant']['id'], (int) $log['tenant_id']);
        }
        self::assertCount(1, (new InvitationRepository())->pending());
    }

    public function testRegistroDeOutraEmpresaETratadoComoInexistente(): void
    {
        [$a, $b] = $this->seedTwoCompanies();
        Auth::actAs($a['users']['admin']);
        $foreignUserId = (int) $b['users']['admin']['id'];

        self::assertNull((new UserRepository())->find($foreignUserId));
        try {
            (new UserRepository())->findOrFail($foreignUserId);
            self::fail('Deveria responder 404.');
        } catch (HttpException $e) {
            self::assertSame(404, $e->status);
        }
        // A tentativa fica registrada na auditoria da empresa que tentou.
        $last = (new AuditRepository())->recent(1)[0];
        self::assertSame('access.not_found', $last['action']);
        self::assertSame($foreignUserId, (int) $last['entity_id']);
    }

    public function testAlteracaoEExclusaoNaoAtingemOutraEmpresa(): void
    {
        [$a, $b] = $this->seedTwoCompanies();
        $foreignDept = Database::fetch('SELECT * FROM departments WHERE tenant_id = ? LIMIT 1', [$b['tenant']['id']]);
        Auth::actAs($a['users']['admin']);

        $repo = new DepartmentRepository();
        $repo->rename((int) $foreignDept['id'], 'Invadido');
        $repo->remove((int) $foreignDept['id']);
        (new UserRepository())->save((int) $b['users']['admin']['id'], ['name' => 'Invadido']);

        self::assertSame($foreignDept['name'], Database::value('SELECT name FROM departments WHERE id = ?', [$foreignDept['id']]));
        self::assertSame($b['users']['admin']['name'], Database::value('SELECT name FROM users WHERE id = ?', [$b['users']['admin']['id']]));
    }

    public function testTenantIdInformadoDeForaEIgnorado(): void
    {
        [$a, $b] = $this->seedTwoCompanies();
        Auth::actAs($a['users']['admin']);
        $id = (new UserRepository())->create([
            'tenant_id' => $b['tenant']['id'],
            'name' => 'Tentativa',
            'email' => 'tentativa@teste.example',
            'password_hash' => 'x',
        ]);
        self::assertSame((int) $a['tenant']['id'], (int) Database::value('SELECT tenant_id FROM users WHERE id = ?', [$id]));
    }

    public function testBancoImpedeVinculoEntreEmpresasPelaChaveComposta(): void
    {
        [$a, $b] = $this->seedTwoCompanies();
        $foreignDeptId = (int) Database::value('SELECT id FROM departments WHERE tenant_id = ? LIMIT 1', [$b['tenant']['id']]);
        Auth::actAs($a['users']['admin']);

        $this->expectException(\PDOException::class);
        (new UserRepository())->save((int) $a['users']['gestor']['id'], ['department_id' => $foreignDeptId]);
    }

    public function testContextoTemporarioVoltaAoAnterior(): void
    {
        [$a, $b] = $this->seedTwoCompanies();
        Auth::actAs($a['users']['admin']);
        $inner = TenantContext::runAs((int) $b['tenant']['id'], fn (): int => TenantContext::id());
        self::assertSame((int) $b['tenant']['id'], $inner);
        self::assertSame((int) $a['tenant']['id'], TenantContext::id());
    }
}
