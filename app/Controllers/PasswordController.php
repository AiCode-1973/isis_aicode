<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Core\RateLimiter;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Validator;
use Isis\Repositories\UserTokenRepository;
use Isis\Services\AccountService;

/**
 * Recuperação de acesso: link de uso único, válido por 60 minutos, com resposta neutra.
 */
final class PasswordController extends Controller
{
    private const string NEUTRAL = 'Se o e-mail estiver cadastrado e ativo, você receberá um link para criar uma nova senha em instantes.';

    public function showForgot(Request $request): Response
    {
        return $this->view('auth/forgot', ['title' => 'Recuperar acesso'], 'guest');
    }

    public function sendLink(Request $request): Response
    {
        $v = Validator::make($request->all(), ['email' => 'required|email'], ['email' => 'seu e-mail']);
        if ($v->fails()) {
            return $this->backWithErrors('/esqueci-senha', $v->errors(), $request->all());
        }
        $email = (string) $v->validated()['email'];
        $ip = $request->ip();
        if (!RateLimiter::tooMany('reset_account', $email, 3, 15) && !RateLimiter::tooMany('reset_ip', $ip, 10, 15)) {
            RateLimiter::hit('reset_account', $email);
            RateLimiter::hit('reset_ip', $ip);
            AccountService::requestPasswordReset($email);
        }
        return $this->success('/entrar', self::NEUTRAL);
    }

    public function showReset(Request $request, string $token): Response
    {
        if (UserTokenRepository::findValid($token, UserTokenRepository::PASSWORD_RESET) === null) {
            return $this->failure('/esqueci-senha', 'Link inválido ou expirado. Solicite um novo.');
        }
        return $this->view('auth/reset', ['title' => 'Criar nova senha', 'token' => $token], 'guest');
    }

    public function reset(Request $request, string $token): Response
    {
        $v = Validator::make($request->all(), [
            'password' => 'required|password',
            'password_confirmation' => 'required|same:password',
        ], ['password' => 'a nova senha', 'password_confirmation' => 'a confirmação']);
        if ($v->fails()) {
            return $this->backWithErrors('/redefinir-senha/' . $token, $v->errors());
        }
        if (!AccountService::resetPassword($token, (string) $request->input('password'))) {
            return $this->failure('/esqueci-senha', 'Link inválido ou expirado. Solicite um novo.');
        }
        return $this->success('/entrar', 'Senha redefinida. Por segurança, as sessões abertas foram encerradas. Entre com a nova senha.');
    }
}
