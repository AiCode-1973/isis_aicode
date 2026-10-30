<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Auth\Auth;
use Isis\Auth\Role;
use Isis\Core\Crypto;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Validator;
use Isis\Repositories\InvitationRepository;
use Isis\Services\AccountService;

/**
 * Aceite de convite pelo colaborador (cria a senha e entra na empresa que convidou).
 */
final class InvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        if (Auth::check()) {
            return $this->failure('/painel', 'Saia da sua conta atual para aceitar um convite.');
        }
        $invitation = InvitationRepository::findValidByTokenHash(Crypto::hashToken($token));
        if ($invitation === null) {
            return $this->failure('/entrar', 'Convite inválido, expirado ou já utilizado. Peça um novo ao administrador.');
        }
        return $this->view('auth/invitation', [
            'title' => 'Aceitar convite',
            'token' => $token,
            'invitation' => $invitation,
            'roleLabel' => Role::tryFrom($invitation['role'])?->label() ?? $invitation['role'],
        ], 'guest');
    }

    public function accept(Request $request, string $token): Response
    {
        if (Auth::check()) {
            return $this->redirect('/painel');
        }
        $v = Validator::make($request->all(), [
            'name' => 'required|min:3|max:150',
            'password' => 'required|password',
            'password_confirmation' => 'required|same:password',
            'accept_terms' => 'accepted',
        ], ['name' => 'seu nome', 'password' => 'uma senha', 'password_confirmation' => 'a confirmação', 'accept_terms' => 'a leitura dos termos']);
        if ($v->fails()) {
            return $this->backWithErrors('/convite/' . $token, $v->errors(), $request->all());
        }

        $user = AccountService::acceptInvitation($token, (string) $v->validated()['name'], (string) $request->input('password'));
        if ($user === null) {
            return $this->failure('/entrar', 'Convite inválido, expirado ou já utilizado. Peça um novo ao administrador.');
        }
        Auth::login($user);
        return $this->success('/painel', 'Bem-vindo(a) ao ISIS! Sua conta foi criada.');
    }
}
