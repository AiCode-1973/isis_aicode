<?php

declare(strict_types=1);

namespace Isis\Tests\Integration;

use Isis\Auth\Auth;
use Isis\Auth\Totp;
use Isis\Controllers\AuthController;
use Isis\Controllers\MfaController;
use Isis\Controllers\UserController;
use Isis\Core\Database;
use Isis\Core\HttpException;
use Isis\Core\Middleware;
use Isis\Core\Request;
use Isis\Core\Session;
use Isis\Database\Seeds\DemoSeeder;
use Isis\Repositories\UserRepository;
use Isis\Services\MfaService;

/**
 * Verificação em duas etapas: algoritmo TOTP, ativação, login em duas etapas, códigos de
 * recuperação, política da empresa e redefinição pelo administrador.
 */
final class MfaTest extends DatabaseTestCase
{
    public function testVetoresDaRfc6238(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // "12345678901234567890" em Base32
        self::assertSame('287082', Totp::code($secret, Totp::currentStep(59)));
        self::assertSame('081804', Totp::code($secret, Totp::currentStep(1111111109)));
        self::assertSame(Totp::currentStep(59), Totp::verify($secret, '287 082', null, 59));
        self::assertNull(Totp::verify($secret, '287082', Totp::currentStep(59), 59), 'Janela já usada é recusada.');
        self::assertNull(Totp::verify($secret, '287082', null, 59 + 90), 'Código vencido é recusado.');
        self::assertStringContainsString('<svg', Totp::qrSvg(Totp::uri($secret, 'ana@exemplo.com')));
    }

    public function testAtivacaoCodigoUnicoERecuperacao(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        $user = $clinic['users']['gestor'];
        Auth::actAs($user);
        $secret = Totp::generateSecret();
        self::assertNull(MfaService::enable($user, $secret, '000000'), 'Código errado não ativa.');

        $codes = MfaService::enable($user, $secret, Totp::code($secret, Totp::currentStep()));
        self::assertCount(10, $codes);
        $user = UserRepository::findByIdGlobal((int) $user['id']);
        self::assertTrue(MfaService::isEnabled($user));
        self::assertStringNotContainsString($secret, (string) $user['mfa_secret_enc'], 'Segredo guardado criptografado.');

        // O código usado na ativação não serve de novo; o da próxima janela serve uma vez só
        self::assertNull(MfaService::verify($user, Totp::code($secret, Totp::currentStep())));
        $next = Totp::code($secret, Totp::currentStep() + 1);
        self::assertSame('totp', MfaService::verify($user, $next));
        self::assertNull(MfaService::verify(UserRepository::findByIdGlobal((int) $user['id']), $next));

        // Código de recuperação: aceita maiúsculas e sem hífen, uma única vez
        self::assertSame('recovery', MfaService::verify($user, strtoupper(str_replace('-', '', $codes[0]))));
        self::assertNull(MfaService::verify($user, $codes[0]));
        self::assertSame(9, MfaService::remainingRecoveryCodes($user));
        $fresh = MfaService::replaceRecoveryCodes($user);
        self::assertNull(MfaService::verify($user, $codes[1]), 'Códigos antigos deixam de valer.');
        self::assertSame('recovery', MfaService::verify($user, $fresh[0]));
    }

    public function testLoginPedeOCodigoDepoisDaSenha(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        $user = $clinic['users']['encarregado'];
        $secret = Totp::generateSecret();
        MfaService::enable($user, $secret, Totp::code($secret, Totp::currentStep()));
        Auth::actAs(null);

        $response = (new AuthController())->login($this->post('/entrar', ['email' => $user['email'], 'password' => DemoSeeder::PASSWORD]));
        self::assertSame('/entrar/verificacao', $response->headers['Location']);
        self::assertFalse(Auth::check(), 'Só a senha não abre a sessão.');

        $mfa = new MfaController();
        $wrong = $mfa->verify($this->post('/entrar/verificacao', ['code' => '000000']));
        self::assertSame('/entrar/verificacao', $wrong->headers['Location']);
        self::assertFalse(Auth::check());

        $ok = $mfa->verify($this->post('/entrar/verificacao', ['code' => Totp::code($secret, Totp::currentStep() + 1)]));
        self::assertSame('/painel', $ok->headers['Location']);
        self::assertTrue(Auth::check());
        self::assertSame((int) $user['id'], Auth::id());
        self::assertNull(Session::get('mfa_pending'));
        $actions = array_column(Database::fetchAll('SELECT action FROM audit_logs WHERE entity_type = ? AND entity_id = ?', ['user', $user['id']]), 'action');
        self::assertContains('auth.mfa_failed', $actions);
        self::assertContains('auth.mfa_verified', $actions);
    }

    public function testMuitosCodigosErradosEncerramATentativa(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        $user = $clinic['users']['admin'];
        $secret = Totp::generateSecret();
        MfaService::enable($user, $secret, Totp::code($secret, Totp::currentStep()));
        Auth::actAs(null);
        (new AuthController())->login($this->post('/entrar', ['email' => $user['email'], 'password' => DemoSeeder::PASSWORD]));
        $mfa = new MfaController();
        for ($i = 0; $i < 5; $i++) {
            $mfa->verify($this->post('/entrar/verificacao', ['code' => '000000']));
        }
        $blocked = $mfa->verify($this->post('/entrar/verificacao', ['code' => Totp::code($secret, Totp::currentStep() + 1)]));
        self::assertSame('/entrar', $blocked->headers['Location']);
        self::assertFalse(Auth::check());
    }

    public function testPoliticaDaEmpresaObrigaAAtivar(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        Database::execute("UPDATE tenants SET mfa_policy = 'gestao' WHERE id = ?", [$clinic['tenant']['id']]);
        $tenant = Database::fetch('SELECT * FROM tenants WHERE id = ?', [$clinic['tenant']['id']]);
        $get = static fn (string $path): Request => new Request('GET', $path);

        Auth::actAs($clinic['users']['admin'], $tenant);
        self::assertSame('/perfil/dois-fatores', Middleware::handle('auth', $get('/riscos'))?->headers['Location']);
        self::assertNull(Middleware::handle('auth', $get('/perfil/dois-fatores')));

        Auth::actAs($clinic['users']['colaborador'], $tenant);
        self::assertNull(Middleware::handle('auth', $get('/planos')), 'Na política "gestao", o colaborador não é obrigado.');

        $tenant['mfa_policy'] = 'todos';
        Auth::actAs($clinic['users']['colaborador'], $tenant);
        self::assertNotNull(Middleware::handle('auth', $get('/planos')));

        // Obrigatória: não pode desativar
        $admin = $clinic['users']['admin'];
        $secret = Totp::generateSecret();
        MfaService::enable($admin, $secret, Totp::code($secret, Totp::currentStep()));
        Auth::actAs(UserRepository::findByIdGlobal((int) $admin['id']), $tenant);
        self::assertNull(Middleware::handle('auth', $get('/riscos')));
        (new MfaController())->disable($this->post('/perfil/dois-fatores/desativar', ['password' => DemoSeeder::PASSWORD]));
        self::assertTrue(MfaService::isEnabled(UserRepository::findByIdGlobal((int) $admin['id'])));
    }

    public function testAdministradorRedefineSoNaPropriaEmpresa(): void
    {
        [$clinic, $store] = $this->seedTwoCompanies();
        $target = $clinic['users']['gestor'];
        $secret = Totp::generateSecret();
        MfaService::enable($target, $secret, Totp::code($secret, Totp::currentStep()));

        Auth::actAs($clinic['users']['admin']);
        (new UserController())->resetMfa($this->post("/usuarios/{$target['id']}/dois-fatores/redefinir"), (int) $target['id']);
        $after = UserRepository::findByIdGlobal((int) $target['id']);
        self::assertFalse(MfaService::isEnabled($after));
        self::assertSame(0, MfaService::remainingRecoveryCodes($after));
        self::assertGreaterThan((int) $target['session_epoch'], (int) $after['session_epoch'], 'Sessões abertas são encerradas.');

        $foreign = $store['users']['gestor'];
        MfaService::enable($foreign, $secret, Totp::code($secret, Totp::currentStep()));
        try {
            (new UserController())->resetMfa($this->post("/usuarios/{$foreign['id']}/dois-fatores/redefinir"), (int) $foreign['id']);
            self::fail('Usuário de outra empresa deveria ser invisível.');
        } catch (HttpException $e) {
            self::assertSame(404, $e->status);
        }
        self::assertTrue(MfaService::isEnabled(UserRepository::findByIdGlobal((int) $foreign['id'])));
    }

    /** @param array<string, mixed> $data */
    private function post(string $path, array $data = []): Request
    {
        return new Request('POST', $path, [], $data, ['REMOTE_ADDR' => '127.0.0.1']);
    }
}
