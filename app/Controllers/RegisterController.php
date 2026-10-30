<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Core\Config;
use Isis\Core\RateLimiter;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Session;
use Isis\Core\Validator;
use Isis\Repositories\SegmentRepository;
use Isis\Repositories\TenantRepository;
use Isis\Repositories\UserRepository;
use Isis\Services\AccountService;

/**
 * Cadastro da empresa (conta institucional) e confirmação de e-mail do administrador.
 */
final class RegisterController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->view('auth/register', [
            'title' => 'Criar conta da empresa',
            'segments' => SegmentRepository::options(),
            'wide' => true,
        ], 'guest');
    }

    public function store(Request $request): Response
    {
        if (RateLimiter::tooMany('register_ip', $request->ip(), 5, 60)) {
            return $this->failure('/cadastro', 'Muitos cadastros a partir desta rede. Tente novamente mais tarde.');
        }
        // Campo-isca invisível: robôs costumam preenchê-lo.
        if ($request->input('website', '') !== '') {
            return $this->redirect('/cadastro/confirme-email');
        }

        $segments = SegmentRepository::options();
        $v = Validator::make($request->all(), [
            'legal_name' => 'required|min:3|max:190',
            'trade_name' => 'nullable|max:190',
            'cnpj' => 'required|cnpj',
            'segment_code' => 'required|in:' . implode(',', array_keys($segments)),
            'name' => 'required|min:3|max:150',
            'email' => 'required|email',
            'password' => 'required|password',
            'password_confirmation' => 'required|same:password',
            'accept_terms' => 'accepted',
        ], [
            'legal_name' => 'a razão social',
            'trade_name' => 'o nome fantasia',
            'cnpj' => 'o CNPJ',
            'segment_code' => 'o segmento',
            'name' => 'seu nome',
            'email' => 'seu e-mail',
            'password' => 'uma senha',
            'password_confirmation' => 'a confirmação da senha',
            'accept_terms' => 'a leitura dos termos e do aviso',
        ]);

        $data = $v->validated();
        if (!$v->fails() && (new TenantRepository())->cnpjExists((string) $data['cnpj'])) {
            $v->addError('cnpj', 'Este CNPJ já está cadastrado. Peça um convite ao administrador da sua empresa.');
        }
        if (!$v->fails() && UserRepository::emailExists((string) $data['email'])) {
            $v->addError('email', 'Este e-mail já tem conta. Entre ou recupere o acesso.');
        }
        if ($v->fails()) {
            RateLimiter::hit('register_ip', $request->ip());
            return $this->backWithErrors('/cadastro', $v->errors(), $request->all());
        }

        AccountService::registerCompany([
            'legal_name' => $data['legal_name'],
            'trade_name' => $data['trade_name'],
            'cnpj' => $data['cnpj'],
            'segment_code' => $data['segment_code'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => (string) $request->input('password'),
        ]);
        RateLimiter::hit('register_ip', $request->ip());
        Session::flash('registered_email', $data['email']);
        return $this->redirect('/cadastro/confirme-email');
    }

    public function pending(Request $request): Response
    {
        return $this->view('auth/verify-pending', [
            'title' => 'Confirme seu e-mail',
            'email' => Session::getFlash('registered_email'),
            'mailLog' => Config::get('mail.driver') === 'log',
        ], 'guest');
    }

    public function resend(Request $request): Response
    {
        $email = mb_strtolower(trim((string) $request->input('email')));
        if (!RateLimiter::tooMany('verify_resend', $email . '|' . $request->ip(), 3, 15) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            RateLimiter::hit('verify_resend', $email . '|' . $request->ip());
            $user = UserRepository::findByEmailGlobal($email);
            if ($user !== null && $user['email_verified_at'] === null && $user['status'] === 'active') {
                AccountService::sendVerification($user);
            }
        }
        return $this->success('/entrar', 'Se houver uma conta pendente de confirmação para este e-mail, enviamos um novo link.');
    }

    public function verify(Request $request, string $token): Response
    {
        if (AccountService::verifyEmail($token)) {
            return $this->success('/entrar', 'E-mail confirmado. Entre para concluir a configuração da sua empresa.');
        }
        return $this->failure('/entrar', 'Link de confirmação inválido ou expirado. Entre com seu e-mail e senha para receber outro.');
    }
}
