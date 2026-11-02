<?php

declare(strict_types=1);

namespace Isis\Tests\Integration;

use Isis\Auth\Auth;
use Isis\Auth\PlatformAuth;
use Isis\Auth\Totp;
use Isis\Controllers\PlatformController;
use Isis\Controllers\PlatformMfaController;
use Isis\Core\Database;
use Isis\Core\Request;
use Isis\Core\Session;
use Isis\Repositories\PlatformRepository;
use Isis\Services\PlatformMfaService;

/**
 * Verificação em duas etapas obrigatória no painel da plataforma: ativação no primeiro acesso,
 * código a cada entrada, recuperação e redefinição pelo servidor.
 */
final class PlatformMfaTest extends DatabaseTestCase
{
    private const PASSWORD = 'Plataforma@2026x';

    private function admin(): array
    {
        $id = (new PlatformRepository())->saveAdmin('Equipe AiCode', 'equipe@aicode.example', Auth::hashPassword(self::PASSWORD));
        PlatformAuth::actAs(null);
        return (new PlatformRepository())->findAdmin($id);
    }

    private function login(): string
    {
        $response = (new PlatformController())->login($this->post('/plataforma/entrar', ['email' => 'equipe@aicode.example', 'password' => self::PASSWORD]));
        return (string) $response->headers['Location'];
    }

    public function testPrimeiroAcessoObrigaAAtivarAntesDeEntrar(): void
    {
        $admin = $this->admin();
        self::assertSame('/plataforma/verificacao/ativar', $this->login());
        self::assertFalse(PlatformAuth::check(), 'Só a senha não abre o painel.');

        $mfa = new PlatformMfaController();
        $mfa->setup(new Request('GET', '/plataforma/verificacao/ativar'));
        $secret = (string) Session::get('platform_mfa_setup');
        self::assertNotSame('', $secret);

        $wrong = $mfa->enable($this->post('/plataforma/verificacao/ativar', ['code' => '000000']));
        self::assertSame('/plataforma/verificacao/ativar', $wrong->headers['Location']);
        self::assertFalse(PlatformAuth::check());

        $ok = $mfa->enable($this->post('/plataforma/verificacao/ativar', ['code' => Totp::code($secret, Totp::currentStep())]));
        self::assertSame('/plataforma/configuracoes#dois-fatores', $ok->headers['Location']);
        self::assertTrue(PlatformAuth::check());
        self::assertCount(10, (array) ($_SESSION['_flash']['platform_mfa_codes'] ?? []), 'Os códigos aparecem na próxima tela.');

        $saved = (new PlatformRepository())->findAdmin((int) $admin['id']);
        self::assertTrue(PlatformMfaService::isEnabled($saved));
        self::assertStringNotContainsString($secret, (string) $saved['mfa_secret_enc'], 'O segredo fica criptografado.');
    }

    public function testCadaEntradaPedeOCodigo(): void
    {
        $admin = $this->admin();
        $secret = Totp::generateSecret();
        $codes = PlatformMfaService::enable($admin, $secret, Totp::code($secret, Totp::currentStep()));
        $admin = (new PlatformRepository())->findAdmin((int) $admin['id']);

        self::assertSame('/plataforma/verificacao', $this->login());
        self::assertFalse(PlatformAuth::check());
        $mfa = new PlatformMfaController();
        $wrong = $mfa->verify($this->post('/plataforma/verificacao', ['code' => '000000']));
        self::assertSame('/plataforma/verificacao', $wrong->headers['Location']);
        self::assertFalse(PlatformAuth::check());

        $ok = $mfa->verify($this->post('/plataforma/verificacao', ['code' => Totp::code($secret, Totp::currentStep() + 1)]));
        self::assertSame('/plataforma', $ok->headers['Location']);
        self::assertTrue(PlatformAuth::check());
        self::assertNull(Session::get('platform_pending'));

        // Código de recuperação vale uma vez
        self::assertSame('recovery', PlatformMfaService::verify($admin, $codes[0]));
        self::assertNull(PlatformMfaService::verify($admin, $codes[0]));
        self::assertSame(9, PlatformMfaService::remainingRecoveryCodes($admin));

        $actions = array_column(Database::fetchAll("SELECT action FROM audit_logs WHERE entity_type = 'platform_admin'"), 'action');
        self::assertContains('platform.mfa_failed', $actions);
        self::assertContains('platform.login', $actions);
    }

    public function testMuitosCodigosErradosEncerramATentativa(): void
    {
        $admin = $this->admin();
        $secret = Totp::generateSecret();
        PlatformMfaService::enable($admin, $secret, Totp::code($secret, Totp::currentStep()));
        $this->login();
        $mfa = new PlatformMfaController();
        for ($i = 0; $i < 5; $i++) {
            $mfa->verify($this->post('/plataforma/verificacao', ['code' => '000000']));
        }
        $blocked = $mfa->verify($this->post('/plataforma/verificacao', ['code' => Totp::code($secret, Totp::currentStep() + 1)]));
        self::assertSame('/plataforma/entrar', $blocked->headers['Location']);
        self::assertFalse(PlatformAuth::check());
    }

    public function testRedefinicaoVoltaAPedirAtivacao(): void
    {
        $admin = $this->admin();
        $secret = Totp::generateSecret();
        PlatformMfaService::enable($admin, $secret, Totp::code($secret, Totp::currentStep()));
        PlatformMfaService::reset($admin);
        $admin = (new PlatformRepository())->findAdmin((int) $admin['id']);
        self::assertFalse(PlatformMfaService::isEnabled($admin));
        self::assertSame(0, PlatformMfaService::remainingRecoveryCodes($admin));
        self::assertSame('/plataforma/verificacao/ativar', $this->login());
    }

    private function post(string $path, array $data = []): Request
    {
        return new Request('POST', $path, [], $data, ['REMOTE_ADDR' => '127.0.0.1']);
    }
}
