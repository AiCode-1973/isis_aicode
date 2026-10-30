<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Auth\Role;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Validator;
use Isis\Repositories\DepartmentRepository;
use Isis\Repositories\InvitationRepository;
use Isis\Repositories\UserRepository;
use Isis\Services\AccountService;
use Isis\Services\MfaService;
use Isis\Services\SubscriptionService;

/**
 * Usuários da empresa: listagem, convites e alteração de perfil/setor/situação.
 */
final class UserController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('users/index', [
            'title' => 'Usuários e permissões',
            'users' => (new UserRepository())->listWithDepartment(),
            'invitations' => (new InvitationRepository())->pending(),
            'departments' => (new DepartmentRepository())->options(),
            'roles' => Role::cases(),
        ]);
    }

    public function invite(Request $request): Response
    {
        $departments = (new DepartmentRepository())->options();
        $v = Validator::make($request->all(), [
            'name' => 'required|min:3|max:150',
            'email' => 'required|email',
            'role' => 'required|in:' . implode(',', array_column(Role::cases(), 'value')),
            'department_id' => 'nullable|int',
        ], ['name' => 'o nome', 'email' => 'o e-mail', 'role' => 'o perfil', 'department_id' => 'o setor']);

        $data = $v->validated();
        $departmentId = $data['department_id'] !== null ? (int) $data['department_id'] : null;
        if ($departmentId !== null && !isset($departments[$departmentId])) {
            $v->addError('department_id', 'Setor inválido.');
        }
        if ($v->fails()) {
            return $this->backWithErrors('/usuarios#convidar', $v->errors(), $request->all());
        }

        $error = AccountService::invite([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'department_id' => $departmentId,
        ]);
        if ($error !== null) {
            return $this->backWithErrors('/usuarios#convidar', ['email' => $error], $request->all(), $error);
        }
        return $this->success('/usuarios', "Convite enviado para {$data['email']}.");
    }

    public function resendInvite(Request $request, int $id): Response
    {
        AccountService::resendInvitation($id);
        return $this->success('/usuarios', 'Convite reenviado com novo prazo de validade.');
    }

    public function revokeInvite(Request $request, int $id): Response
    {
        AccountService::revokeInvitation($id);
        return $this->success('/usuarios', 'Convite cancelado.');
    }

    public function edit(Request $request, int $id): Response
    {
        $user = (new UserRepository())->findOrFail($id);
        return $this->view('users/edit', [
            'title' => 'Editar usuário',
            'editUser' => $user,
            'departments' => (new DepartmentRepository())->options(),
            'roles' => Role::cases(),
            'isSelf' => $id === Auth::id(),
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $repo = new UserRepository();
        $user = $repo->findOrFail($id);
        $departments = (new DepartmentRepository())->options();

        $v = Validator::make($request->all(), [
            'name' => 'required|min:3|max:150',
            'job_title' => 'nullable|max:120',
            'role' => 'required|in:' . implode(',', array_column(Role::cases(), 'value')),
            'department_id' => 'nullable|int',
            'status' => 'required|in:active,inactive',
        ], ['name' => 'o nome', 'job_title' => 'o cargo', 'role' => 'o perfil', 'department_id' => 'o setor', 'status' => 'a situação']);
        $data = $v->validated();
        $departmentId = $data['department_id'] !== null ? (int) $data['department_id'] : null;
        if ($departmentId !== null && !isset($departments[$departmentId])) {
            $v->addError('department_id', 'Setor inválido.');
        }

        $isSelf = $id === Auth::id();
        if ($isSelf && ($data['role'] !== $user['role'] || $data['status'] !== $user['status'])) {
            $v->addError('role', 'Você não pode alterar o próprio perfil ou desativar a própria conta.');
        }
        $losesAdmin = $user['role'] === Role::Admin->value && $user['status'] === 'active'
            && ($data['role'] !== Role::Admin->value || $data['status'] !== 'active');
        if ($losesAdmin && $repo->countActiveAdmins() <= 1) {
            $v->addError('role', 'A empresa precisa de pelo menos um administrador ativo.');
        }
        if ($user['status'] !== 'active' && $data['status'] === 'active' && ($limit = SubscriptionService::limitReached('users')) !== null) {
            $v->addError('status', $limit);
        }
        if ($v->fails()) {
            return $this->backWithErrors("/usuarios/{$id}/editar", $v->errors(), $request->all());
        }

        $changes = [
            'name' => $data['name'],
            'job_title' => $data['job_title'],
            'role' => $data['role'],
            'department_id' => $departmentId,
            'status' => $data['status'],
        ];
        $repo->save($id, $changes);
        if ($data['status'] === 'inactive' || $data['role'] !== $user['role']) {
            $repo->bumpSessionEpoch($id); // força novo login com o perfil atualizado
        }
        AuditLogger::log('user.updated', 'user', $id, $user, $changes);
        return $this->success('/usuarios', 'Usuário atualizado.');
    }

    /** Para quem perdeu o celular: apaga o aplicativo cadastrado; a pessoa ativa de novo no próximo acesso. */
    public function resetMfa(Request $request, int $id): Response
    {
        $repo = new UserRepository();
        $user = $repo->findOrFail($id);
        if ($id === Auth::id()) {
            return $this->failure("/usuarios/{$id}/editar", 'Para a sua própria conta, use Meu perfil > Verificação em duas etapas.');
        }
        if (!MfaService::isEnabled($user)) {
            return $this->failure("/usuarios/{$id}/editar", 'Este usuário não tem a verificação em duas etapas ativada.');
        }
        MfaService::disable($user);
        $repo->bumpSessionEpoch($id);
        AuditLogger::log('mfa.reset', 'user', $id);
        return $this->success("/usuarios/{$id}/editar", 'Verificação em duas etapas redefinida. No próximo acesso, a pessoa entra só com a senha e cadastra o aplicativo de novo.');
    }
}
