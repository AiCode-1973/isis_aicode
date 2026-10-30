<?php

declare(strict_types=1);

namespace Isis\Tests\Integration;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Core\Database;
use Isis\Repositories\AuditRepository;
use Isis\Services\AccountService;

/**
 * Convites (entram na empresa certa, com o perfil certo, uma única vez) e integridade da auditoria.
 */
final class InvitationAndAuditTest extends DatabaseTestCase
{
    public function testConviteCriaUsuarioNaEmpresaQueConvidou(): void
    {
        [$a, $b] = $this->seedTwoCompanies();
        Auth::actAs($a['users']['admin'], $a['tenant']);

        self::assertNull(AccountService::invite([
            'name' => 'Diego Ramos', 'email' => 'diego@vidaplena.example', 'role' => 'auditor', 'department_id' => null,
        ]));
        $token = $this->lastMailToken('/convite');
        Auth::actAs(null);

        $user = AccountService::acceptInvitation($token, 'Diego Ramos', 'Senha-Forte-2026');
        self::assertNotNull($user);
        self::assertSame((int) $a['tenant']['id'], (int) $user['tenant_id']);
        self::assertSame('auditor', $user['role']);
        self::assertNotNull($user['email_verified_at']);

        self::assertNull(AccountService::acceptInvitation($token, 'De Novo', 'Senha-Forte-2026'), 'Convite é de uso único.');
    }

    public function testNaoConvidaEmailQueJaTemContaNemDuplicaConvite(): void
    {
        [$a, $b] = $this->seedTwoCompanies();
        Auth::actAs($a['users']['admin'], $a['tenant']);
        self::assertNotNull(AccountService::invite([
            'name' => 'X', 'email' => $b['users']['gestor']['email'], 'role' => 'colaborador', 'department_id' => null,
        ]));
        self::assertNotNull(AccountService::invite([
            'name' => 'X', 'email' => 'novo.colaborador@vidaplena.example', 'role' => 'colaborador', 'department_id' => null,
        ]));
    }

    public function testConviteCanceladoNaoPodeSerAceito(): void
    {
        [$a] = $this->seedTwoCompanies();
        Auth::actAs($a['users']['admin'], $a['tenant']);
        AccountService::invite(['name' => 'Eva', 'email' => 'eva@vidaplena.example', 'role' => 'gestor', 'department_id' => null]);
        $token = $this->lastMailToken('/convite');
        $id = (int) Database::value("SELECT id FROM invitations WHERE email = 'eva@vidaplena.example'");
        AccountService::revokeInvitation($id);
        Auth::actAs(null);
        self::assertNull(AccountService::acceptInvitation($token, 'Eva', 'Senha-Forte-2026'));
    }

    public function testAuditoriaRegistraAutorEAlteracoesEMantemCadeiaIntegra(): void
    {
        [$a, $b] = $this->seedTwoCompanies();
        Auth::actAs($a['users']['admin'], $a['tenant']);
        AuditLogger::setRequestInfo('203.0.113.9', 'PHPUnit');
        AuditLogger::log('user.updated', 'user', (int) $a['users']['gestor']['id'], ['role' => 'gestor'], ['role' => 'admin']);

        $log = (new AuditRepository())->recent(1)[0];
        self::assertSame('Mariana Costa', $log['user_name']);
        self::assertSame('203.0.113.9', $log['ip']);
        self::assertSame(['role' => ['de' => 'gestor', 'para' => 'admin']], json_decode($log['changes'], true));
        self::assertTrue((new AuditRepository())->verifyChain()['ok']);
    }

    public function testAdulteracaoDiretaNoBancoEDetectada(): void
    {
        [$a] = $this->seedTwoCompanies();
        Auth::actAs($a['users']['admin'], $a['tenant']);
        $repo = new AuditRepository();
        self::assertTrue($repo->verifyChain()['ok']);

        $id = (int) Database::value('SELECT MIN(id) FROM audit_logs WHERE tenant_id = ?', [$a['tenant']['id']]);
        Database::execute("UPDATE audit_logs SET user_name = 'Outra pessoa' WHERE id = ?", [$id]);
        $result = $repo->verifyChain();
        self::assertFalse($result['ok']);
        self::assertSame($id, $result['broken_id']);
    }

    public function testExclusaoDoUltimoRegistroEDetectada(): void
    {
        [$a] = $this->seedTwoCompanies();
        Auth::actAs($a['users']['admin'], $a['tenant']);
        Database::execute('DELETE FROM audit_logs WHERE tenant_id = ? ORDER BY id DESC LIMIT 1', [$a['tenant']['id']]);
        self::assertFalse((new AuditRepository())->verifyChain()['ok']);
    }
}
