<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Auth\Auth;
use Isis\Core\RateLimiter;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Session;

final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return $this->view('auth/login', ['title' => 'Entrar'], 'guest');
    }

    public function login(Request $request): Response
    {
        $email = trim((string) $request->input('email'));
        $password = (string) $request->input('password');
        if ($email === '' || $password === '') {
            return $this->backWithErrors('/entrar', ['email' => 'Informe e-mail e senha.'], ['email' => $email]);
        }

        $result = Auth::attempt($email, $password, $request->ip());
        if ($result['error'] !== null) {
            $message = match ($result['error']) {
                'throttled' => 'Muitas tentativas sem sucesso. Por segurança, aguarde 15 minutos ou redefina sua senha.',
                'inactive' => 'Este acesso está desativado. Fale com o administrador da sua empresa.',
                'unverified' => 'Confirme seu e-mail antes de entrar. Enviamos o link para sua caixa de entrada.',
                default => 'E-mail ou senha incorretos.',
            };
            if ($result['error'] === 'unverified' && $result['user'] !== null && !RateLimiter::tooMany('verify_resend', $email . '|' . $request->ip(), 3, 15)) {
                RateLimiter::hit('verify_resend', $email . '|' . $request->ip());
                \Isis\Services\AccountService::sendVerification($result['user']);
            }
            Session::flash('_old', ['email' => $email]);
            return $this->failure('/entrar', $message);
        }

        Auth::login($result['user']);
        if (random_int(1, 50) === 1) {
            RateLimiter::prune();
        }
        $intended = Session::get('intended');
        Session::forget('intended');
        return $this->redirect(is_string($intended) && str_starts_with($intended, '/') ? $intended : '/painel');
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        return $this->success('/entrar', 'Você saiu do ÍSIS com segurança.');
    }
}
