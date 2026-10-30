<?php

declare(strict_types=1);

namespace Isis\Tests\Integration;

use Isis\Auth\Auth;
use Isis\Core\Database;
use Isis\Database\Seeds\DemoSeeder;
use Isis\Repositories\UserRepository;
use Isis\Repositories\UserTokenRepository;
use Isis\Services\AccountService;

/**
 * Cadastro da empresa, confirmação de e-mail, login com bloqueio, sessão e recuperação de acesso.
 */
final class AccountFlowTest extends DatabaseTestCase
{
    private function register(): int
    {
        return AccountService::registerCompany([
            'legal_name' => 'Escola Saber Mais Ltda.',
            'trade_name' => 'Escola Saber Mais',
            'cnpj' => '11222333000181',
            'segment_code' => 'educacao',
            'name' => 'Paula Reis',
            'email' => 'paula@sabermais.example',
            'password' => 'Senha-Forte-2026',
        ]);
    }

    public function testCadastroCriaEmpresaComAdministradorEExigeConfirmacao(): void
    {
        $userId = $this->register();
        $user = UserRepository::findByIdGlobal($userId);
        self::assertSame('admin', $user['role']);
        self::assertNull($user['email_verified_at']);
        self::assertNotSame('Senha-Forte-2026', $user['password_hash']);
        self::assertTrue(password_verify('Senha-Forte-2026', $user['password_hash']));

        $tenant = Database::fetch('SELECT * FROM tenants WHERE id = ?', [$user['tenant_id']]);
        self::assertSame('escola-saber-mais', $tenant['slug']);
        self::assertNull($tenant['onboarding_completed_at']);

        self::assertSame('unverified', Auth::attempt('paula@sabermais.example', 'Senha-Forte-2026', '10.0.0.1')['error']);

        $token = $this->lastMailToken('/verificar-email');
        self::assertTrue(AccountService::verifyEmail($token));
        self::assertFalse(AccountService::verifyEmail($token), 'O link de confirmação é de uso único.');

        $result = Auth::attempt('PAULA@sabermais.example', 'Senha-Forte-2026', '10.0.0.1');
        self::assertNull($result['error']);
    }

    public function testTokensSaoGuardadosApenasComoHash(): void
    {
        $this->register();
        $token = $this->lastMailToken('/verificar-email');
        self::assertNull(Database::value('SELECT 1 FROM user_tokens WHERE token_hash = ?', [$token]));
        self::assertNotNull(Database::value('SELECT 1 FROM user_tokens WHERE token_hash = ?', [hash('sha256', $token)]));
    }

    public function testBloqueioAposTentativasErradas(): void
    {
        $this->seedTwoCompanies();
        $email = 'carlos.souza@vidaplena.example';
        for ($i = 0; $i < 5; $i++) {
            self::assertSame('invalid', Auth::attempt($email, 'errada', '10.0.0.2')['error']);
        }
        // Mesmo com a senha certa, a conta fica bloqueada durante a janela.
        self::assertSame('throttled', Auth::attempt($email, DemoSeeder::PASSWORD, '10.0.0.3')['error']);
        self::assertGreaterThan(0, (int) Database::value("SELECT COUNT(*) FROM audit_logs WHERE action = 'auth.login_blocked'"));
    }

    public function testEmailInexistenteTemMesmaRespostaQueSenhaErrada(): void
    {
        $this->seedTwoCompanies();
        self::assertSame('invalid', Auth::attempt('ninguem@nada.example', 'qualquer', '10.0.0.4')['error']);
        self::assertSame('invalid', Auth::attempt('carlos.souza@vidaplena.example', 'qualquer', '10.0.0.4')['error']);
    }

    public function testUsuarioInativoNaoEntra(): void
    {
        [$a] = $this->seedTwoCompanies();
        Database::execute("UPDATE users SET status = 'inactive' WHERE id = ?", [$a['users']['colaborador']['id']]);
        self::assertSame('inactive', Auth::attempt('carlos.souza@vidaplena.example', DemoSeeder::PASSWORD, '10.0.0.5')['error']);
    }

    public function testSessaoEDerrubadaQuandoAEpocaMuda(): void
    {
        [$a] = $this->seedTwoCompanies();
        $user = $a['users']['gestor'];
        Auth::login($user);
        self::assertNull(Auth::boot());
        self::assertTrue(Auth::check());

        Database::execute('UPDATE users SET session_epoch = session_epoch + 1 WHERE id = ?', [$user['id']]);
        self::assertSame('revoked', Auth::boot());
        self::assertFalse(Auth::check());
    }

    public function testSessaoExpiraPorInatividade(): void
    {
        [$a] = $this->seedTwoCompanies();
        Auth::login($a['users']['gestor']);
        $_SESSION['auth']['last_seen'] = time() - 3600;
        self::assertSame('expired', Auth::boot());
    }

    public function testRecuperacaoDeSenhaDeUsoUnicoEncerraSessoes(): void
    {
        [$a] = $this->seedTwoCompanies();
        $user = $a['users']['colaborador'];

        AccountService::requestPasswordReset('ninguem@nada.example');
        self::assertSame([], \Isis\Core\Mailer::$sent, 'E-mail inexistente não recebe nada (e a tela responde igual).');

        AccountService::requestPasswordReset($user['email']);
        $token = $this->lastMailToken('/redefinir-senha');
        self::assertNotNull(UserTokenRepository::findValid($token, UserTokenRepository::PASSWORD_RESET));

        self::assertTrue(AccountService::resetPassword($token, 'Nova-Senha-2026'));
        self::assertFalse(AccountService::resetPassword($token, 'Outra-Senha-2026'), 'Link de uso único.');

        $fresh = UserRepository::findByIdGlobal((int) $user['id']);
        self::assertTrue(password_verify('Nova-Senha-2026', $fresh['password_hash']));
        self::assertSame((int) $user['session_epoch'] + 1, (int) $fresh['session_epoch']);
    }

    public function testTokenExpiradoNaoFunciona(): void
    {
        [$a] = $this->seedTwoCompanies();
        AccountService::requestPasswordReset($a['users']['colaborador']['email']);
        $token = $this->lastMailToken('/redefinir-senha');
        Database::execute('UPDATE user_tokens SET expires_at = NOW() - INTERVAL 1 MINUTE');
        self::assertFalse(AccountService::resetPassword($token, 'Nova-Senha-2026'));
    }
}
