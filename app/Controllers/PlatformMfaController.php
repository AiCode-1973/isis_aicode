<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Audit\AuditLogger;
use Isis\Auth\PlatformAuth;
use Isis\Auth\Totp;
use Isis\Core\Config;
use Isis\Core\RateLimiter;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Session;
use Isis\Repositories\PlatformRepository;
use Isis\Services\PlatformMfaService;

/**
 * Segunda etapa do login da equipe AiCode. Obrigatória: depois da senha, quem já ativou digita o código;
 * quem ainda não ativou cadastra o aplicativo antes de entrar no painel.
 */
final class PlatformMfaController extends Controller
{
    /** Tempo para concluir a etapa depois de acertar a senha (inclui instalar o aplicativo). */
    private const int PENDING_SECONDS = 600;
    private const int MAX_ATTEMPTS = 5;

    /** Chamado pelo login da plataforma depois de a senha conferir. */
    public static function begin(array $admin, ?string $intended): string
    {
        Session::regenerate();
        Session::set('platform_pending', ['aid' => (int) $admin['id'], 'at' => time(), 'intended' => $intended]);
        return PlatformMfaService::isEnabled($admin) ? '/plataforma/verificacao' : '/plataforma/verificacao/ativar';
    }

    public function challenge(Request $request): Response
    {
        $admin = $this->pendingAdmin();
        if ($admin === null) {
            return $this->redirect('/plataforma/entrar');
        }
        if (!PlatformMfaService::isEnabled($admin)) {
            return $this->redirect('/plataforma/verificacao/ativar');
        }
        return $this->view('platform/mfa', ['title' => 'Verificação em duas etapas'], 'guest');
    }

    public function verify(Request $request): Response
    {
        $admin = $this->pendingAdmin();
        if ($admin === null || !PlatformMfaService::isEnabled($admin)) {
            return $this->failure('/plataforma/entrar', 'O tempo para digitar o código terminou. Entre novamente.');
        }
        $key = (string) $admin['id'];
        if (RateLimiter::tooMany('platform_mfa', $key, self::MAX_ATTEMPTS, 15)) {
            Session::forget('platform_pending');
            return $this->failure('/plataforma/entrar', 'Muitos códigos incorretos. Por segurança, aguarde 15 minutos e entre novamente.');
        }
        $method = PlatformMfaService::verify($admin, (string) $request->input('code'));
        if ($method === null) {
            RateLimiter::hit('platform_mfa', $key);
            AuditLogger::log('platform.mfa_failed', 'platform_admin', (int) $admin['id'], null, null, self::actor($admin));
            return $this->failure('/plataforma/verificacao', 'Código incorreto ou já usado. Confira o aplicativo e tente de novo.');
        }
        RateLimiter::clear('platform_mfa', $key);
        $intended = $this->finish($admin);
        if ($method === 'recovery') {
            AuditLogger::log('platform.mfa_recovery_used', 'platform_admin', (int) $admin['id'], null, null, self::actor($admin));
            $left = PlatformMfaService::remainingRecoveryCodes($admin);
            Session::flash('error', "Você entrou com um código de recuperação. Restam {$left}. Se perdeu o celular, peça a redefinição no servidor e gere novos códigos.");
            return $this->redirect('/plataforma/configuracoes#dois-fatores');
        }
        return $this->redirect($intended);
    }

    public function setup(Request $request): Response
    {
        $admin = $this->pendingAdmin();
        if ($admin === null) {
            return $this->redirect('/plataforma/entrar');
        }
        if (PlatformMfaService::isEnabled($admin)) {
            return $this->redirect('/plataforma/verificacao');
        }
        $secret = Session::get('platform_mfa_setup');
        if (!is_string($secret) || $secret === '') {
            $secret = Totp::generateSecret();
            Session::set('platform_mfa_setup', $secret);
        }
        $uri = Totp::uri($secret, (string) $admin['email'], (string) Config::get('app.name', 'ÍSIS') . ' Plataforma');
        return $this->view('platform/mfa-setup', [
            'title' => 'Ativar verificação em duas etapas',
            'qr' => Totp::qrSvg($uri), 'secret' => Totp::formatSecret($secret), 'uri' => $uri,
        ], 'guest');
    }

    public function enable(Request $request): Response
    {
        $admin = $this->pendingAdmin();
        $secret = Session::get('platform_mfa_setup');
        if ($admin === null || !is_string($secret)) {
            return $this->failure('/plataforma/entrar', 'O tempo para ativar terminou. Entre novamente.');
        }
        if (PlatformMfaService::isEnabled($admin)) {
            return $this->redirect('/plataforma/verificacao');
        }
        if (RateLimiter::tooMany('platform_mfa', (string) $admin['id'], 10, 15)) {
            return $this->failure('/plataforma/verificacao/ativar', 'Muitas tentativas. Aguarde alguns minutos.');
        }
        $codes = PlatformMfaService::enable($admin, $secret, (string) $request->input('code'));
        if ($codes === null) {
            RateLimiter::hit('platform_mfa', (string) $admin['id']);
            return $this->failure('/plataforma/verificacao/ativar', 'O código não confere. Confira se o horário do celular está automático e digite o código que aparece agora.');
        }
        Session::forget('platform_mfa_setup');
        AuditLogger::log('platform.mfa_enabled', 'platform_admin', (int) $admin['id'], null, null, self::actor($admin));
        $this->finish($admin);
        Session::flash('platform_mfa_codes', $codes);
        return $this->success('/plataforma/configuracoes#dois-fatores', 'Verificação em duas etapas ativada. Guarde agora os códigos de recuperação.');
    }

    /** Gera novos códigos de recuperação (com a senha). */
    public function regenerate(Request $request): Response
    {
        $admin = PlatformAuth::admin();
        if (!password_verify((string) $request->input('password_codes'), (string) $admin['password_hash'])) {
            return $this->backWithErrors('/plataforma/configuracoes#dois-fatores', ['password_codes' => 'Senha incorreta.']);
        }
        Session::flash('platform_mfa_codes', PlatformMfaService::replaceRecoveryCodes($admin));
        AuditLogger::log('platform.mfa_codes_regenerated', 'platform_admin', (int) $admin['id'], null, null, self::actor($admin));
        return $this->success('/plataforma/configuracoes#dois-fatores', 'Novos códigos de recuperação gerados. Os anteriores deixaram de valer.');
    }

    /** Conclui o login e devolve o destino pedido antes da senha. */
    private function finish(array $admin): string
    {
        $intended = Session::get('platform_pending')['intended'] ?? null;
        Session::forget('platform_pending');
        PlatformAuth::login($admin);
        return is_string($intended) && str_starts_with($intended, '/plataforma') ? $intended : '/plataforma';
    }

    private function pendingAdmin(): ?array
    {
        $pending = Session::get('platform_pending');
        if (!is_array($pending) || !isset($pending['aid'], $pending['at']) || time() - (int) $pending['at'] > self::PENDING_SECONDS) {
            Session::forget('platform_pending');
            return null;
        }
        $admin = (new PlatformRepository())->findAdmin((int) $pending['aid']);
        if ($admin === null || $admin['status'] !== 'active') {
            Session::forget('platform_pending');
            return null;
        }
        return $admin;
    }

    private static function actor(array $admin): array
    {
        return ['tenant_id' => null, 'user_id' => null, 'user_name' => 'AiCode · ' . $admin['name']];
    }
}
