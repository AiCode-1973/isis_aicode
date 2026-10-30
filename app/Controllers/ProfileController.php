<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Validator;
use Isis\Repositories\UserRepository;

/**
 * Perfil do próprio usuário: nome, senha e encerramento de outras sessões.
 */
final class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->view('profile/index', ['title' => 'Meu perfil', 'me' => Auth::user()]);
    }

    public function update(Request $request): Response
    {
        $v = Validator::make($request->all(), [
            'name' => 'required|min:3|max:150',
            'job_title' => 'nullable|max:120',
        ], ['name' => 'seu nome', 'job_title' => 'o cargo']);
        if ($v->fails()) {
            return $this->backWithErrors('/perfil', $v->errors(), $request->all());
        }
        $me = Auth::user();
        $data = $v->validated();
        (new UserRepository())->save((int) $me['id'], $data);
        AuditLogger::log('profile.updated', 'user', (int) $me['id'], $me, $data);
        return $this->success('/perfil', 'Perfil atualizado.');
    }

    public function password(Request $request): Response
    {
        $me = Auth::user();
        $v = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => 'required|password',
            'password_confirmation' => 'required|same:password',
        ], ['current_password' => 'a senha atual', 'password' => 'a nova senha', 'password_confirmation' => 'a confirmação']);
        if (!$v->fails() && !password_verify((string) $request->input('current_password'), (string) $me['password_hash'])) {
            $v->addError('current_password', 'Senha atual incorreta.');
        }
        if (!$v->fails() && password_verify((string) $request->input('password'), (string) $me['password_hash'])) {
            $v->addError('password', 'A nova senha deve ser diferente da atual.');
        }
        if ($v->fails()) {
            return $this->backWithErrors('/perfil#senha', $v->errors());
        }

        $repo = new UserRepository();
        $repo->save((int) $me['id'], ['password_hash' => Auth::hashPassword((string) $request->input('password'))]);
        Auth::refreshEpoch($repo->bumpSessionEpoch((int) $me['id']));
        AuditLogger::log('profile.password_changed', 'user', (int) $me['id']);
        return $this->success('/perfil', 'Senha alterada. As sessões em outros dispositivos foram encerradas.');
    }

    public function logoutOthers(Request $request): Response
    {
        $id = (int) Auth::id();
        Auth::refreshEpoch((new UserRepository())->bumpSessionEpoch($id));
        AuditLogger::log('sessions.revoked', 'user', $id);
        return $this->success('/perfil', 'Sessões em outros dispositivos encerradas.');
    }
}
