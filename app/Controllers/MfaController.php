<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Auth\Totp;
use Isis\Core\Config;
use Isis\Core\RateLimiter;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Session;
use Isis\Repositories\UserRepository;
use Isis\Services\MfaService;

/**
 * Verificação em duas etapas: segunda etapa do login e ativação/gestão no perfil.
 */
final class MfaController extends Controller
{
    /** Tempo para digitar o código depois de acertar a senha. */
    private const int PENDING_SECONDS = 300;
    private const int MAX_ATTEMPTS = 5;

    // ---------- Segunda etapa do login ----------

    public function challenge(Request $request): Response
    {
        if ($this->pendingUser() === null) {
            return $this->redirect('/entrar');
        }
        return $this->view('auth/mfa', ['title' => 'Verificação em duas etapas'], 'guest');
    }

    public function verify(Request $request): Response
    {
        $user = $this->pendingUser();
        if ($user === null) {
            return $this->failure('/entrar', 'O tempo para digitar o código terminou. Entre novamente.');
        }
        $key = (string) $user['id'];
        if (RateLimiter::tooMany('mfa', $key, self::MAX_ATTEMPTS, 15)) {
            Session::forget('mfa_pending');
            return $this->failure('/entrar', 'Muitos códigos incorretos. Por segurança, aguarde 15 minutos e entre novamente.');
        }
        $actor = ['tenant_id' => (int) $user['tenant_id'], 'user_id' => (int) $user['id'], 'user_name' => $user['name']];
        $method = MfaService::verify($user, (string) $request->input('code'));
        if ($method === null) {
            RateLimiter::hit('mfa', $key);
            AuditLogger::log('auth.mfa_failed', 'user', (int) $user['id'], null, null, $actor);
            return $this->failure('/entrar/verificacao', 'Código incorreto ou já usado. Confira o aplicativo e tente de novo.');
        }

        RateLimiter::clear('mfa', $key);
        $intended = Session::get('mfa_pending')['intended'] ?? null;
        Session::forget('mfa_pending');
        Auth::login($user);
        AuditLogger::log($method === 'recovery' ? 'auth.mfa_recovery_used' : 'auth.mfa_verified', 'user', (int) $user['id']);
        if ($method === 'recovery') {
            $left = MfaService::remainingRecoveryCodes($user);
            Session::flash('error', "Você entrou com um código de recuperação. Restam {$left}. Se perdeu o celular, cadastre o aplicativo de novo e gere novos códigos.");
            return $this->redirect('/perfil/dois-fatores');
        }
        return $this->redirect(is_string($intended) && str_starts_with($intended, '/') ? $intended : '/painel');
    }

    /** Usuário que acertou a senha e ainda deve o código (sessão de no máximo 5 minutos). */
    private function pendingUser(): ?array
    {
        $pending = Session::get('mfa_pending');
        if (!is_array($pending) || !isset($pending['uid'], $pending['at']) || time() - (int) $pending['at'] > self::PENDING_SECONDS) {
            Session::forget('mfa_pending');
            return null;
        }
        $user = UserRepository::findByIdGlobal((int) $pending['uid']);
        if ($user === null || $user['status'] !== 'active' || !MfaService::isEnabled($user)) {
            Session::forget('mfa_pending');
            return null;
        }
        return $user;
    }

    // ---------- Perfil: ativar, desativar e códigos de recuperação ----------

    public function show(Request $request): Response
    {
        $me = Auth::user();
        $enabled = MfaService::isEnabled($me);
        $data = [
            'title' => 'Verificação em duas etapas',
            'me' => $me,
            'enabled' => $enabled,
            'required' => MfaService::isRequired($me, Auth::tenant()),
            'codes' => Session::getFlash('mfa_codes'),
            'remaining' => $enabled ? MfaService::remainingRecoveryCodes($me) : 0,
        ];
        if (!$enabled) {
            $secret = Session::get('mfa_setup');
            if (!is_string($secret) || $secret === '') {
                $secret = Totp::generateSecret();
                Session::set('mfa_setup', $secret);
            }
            $uri = Totp::uri($secret, (string) $me['email'], (string) Config::get('app.name', 'ÍSIS'));
            $data += ['qr' => Totp::qrSvg($uri), 'secret' => Totp::formatSecret($secret), 'uri' => $uri];
        }
        return $this->view('profile/mfa', $data);
    }

    public function enable(Request $request): Response
    {
        $me = Auth::user();
        $secret = Session::get('mfa_setup');
        if (MfaService::isEnabled($me) || !is_string($secret)) {
            return $this->redirect('/perfil/dois-fatores');
        }
        if (RateLimiter::tooMany('mfa_setup', (string) $me['id'], 10, 15)) {
            return $this->failure('/perfil/dois-fatores', 'Muitas tentativas. Aguarde alguns minutos.');
        }
        $codes = MfaService::enable($me, $secret, (string) $request->input('code'));
        if ($codes === null) {
            RateLimiter::hit('mfa_setup', (string) $me['id']);
            return $this->failure('/perfil/dois-fatores', 'O código não confere. Confira se o horário do celular está automático e digite o código que aparece agora.');
        }
        Session::forget('mfa_setup');
        Auth::refreshEpoch((new UserRepository())->bumpSessionEpoch((int) $me['id']));
        AuditLogger::log('mfa.enabled', 'user', (int) $me['id']);
        Session::flash('mfa_codes', $codes);
        return $this->success('/perfil/dois-fatores', 'Verificação em duas etapas ativada. Guarde agora os códigos de recuperação. As sessões em outros dispositivos foram encerradas.');
    }

    public function disable(Request $request): Response
    {
        $me = Auth::user();
        if (MfaService::isRequired($me, Auth::tenant())) {
            return $this->failure('/perfil/dois-fatores', 'A política da sua empresa exige a verificação em duas etapas para o seu perfil.');
        }
        if (!$this->passwordOk($request, $me)) {
            return $this->backWithErrors('/perfil/dois-fatores', ['password' => 'Senha incorreta.']);
        }
        MfaService::disable($me);
        AuditLogger::log('mfa.disabled', 'user', (int) $me['id']);
        return $this->success('/perfil/dois-fatores', 'Verificação em duas etapas desativada.');
    }

    public function regenerate(Request $request): Response
    {
        $me = Auth::user();
        if (!MfaService::isEnabled($me)) {
            return $this->redirect('/perfil/dois-fatores');
        }
        if (!$this->passwordOk($request, $me)) {
            return $this->backWithErrors('/perfil/dois-fatores', ['password_codes' => 'Senha incorreta.']);
        }
        Session::flash('mfa_codes', MfaService::replaceRecoveryCodes($me));
        AuditLogger::log('mfa.codes_regenerated', 'user', (int) $me['id']);
        return $this->success('/perfil/dois-fatores', 'Novos códigos de recuperação gerados. Os anteriores deixaram de valer.');
    }

    private function passwordOk(Request $request, array $me): bool
    {
        return password_verify((string) ($request->input('password') ?? $request->input('password_codes')), (string) $me['password_hash']);
    }
}
