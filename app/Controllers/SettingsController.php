<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Validator;
use Isis\Repositories\DepartmentRepository;
use Isis\Repositories\SegmentRepository;
use Isis\Repositories\TenantRepository;
use Isis\Services\MfaService;
use Isis\Support\Labels;

/**
 * Configurações da empresa: dados cadastrais, segmento, encarregado e setores.
 */
final class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('settings/index', [
            'title' => 'Configurações da empresa',
            'tenant' => (new TenantRepository())->current(),
            'segments' => SegmentRepository::options(),
            'departments' => (new DepartmentRepository())->all(),
            'mfaAdoption' => MfaService::adoption((int) Auth::tenant()['id']),
        ]);
    }

    public function updateCompany(Request $request): Response
    {
        $segments = SegmentRepository::options();
        $v = Validator::make($request->all(), [
            'legal_name' => 'required|min:3|max:190',
            'trade_name' => 'nullable|max:190',
            'segment_code' => 'required|in:' . implode(',', array_keys($segments)),
            'dpo_name' => 'required|min:3|max:150',
            'dpo_email' => 'required|email',
            'privacy_email' => 'nullable|email',
            'mfa_policy' => 'required|' . Labels::rule(Labels::MFA_POLICY),
        ], [
            'legal_name' => 'a razão social', 'trade_name' => 'o nome fantasia', 'segment_code' => 'o segmento',
            'dpo_name' => 'o nome do encarregado', 'dpo_email' => 'o e-mail do encarregado', 'privacy_email' => 'o e-mail de privacidade',
        ]);
        if ($v->fails()) {
            return $this->backWithErrors('/configuracoes', $v->errors(), $request->all());
        }
        $repo = new TenantRepository();
        $before = $repo->current();
        $data = $v->validated();
        $repo->updateCurrent($data);
        AuditLogger::log('tenant.updated', 'tenant', (int) $before['id'], $before, $data);
        return $this->success('/configuracoes', 'Dados da empresa atualizados.');
    }

    public function addDepartment(Request $request): Response
    {
        $repo = new DepartmentRepository();
        $v = Validator::make($request->all(), ['name' => 'required|max:120'], ['name' => 'o nome do setor']);
        $name = (string) ($v->validated()['name'] ?? '');
        if (!$v->fails() && $repo->nameExists($name)) {
            $v->addError('name', 'Já existe um setor com este nome.');
        }
        if ($v->fails()) {
            return $this->backWithErrors('/configuracoes#setores', $v->errors(), $request->all());
        }
        $id = $repo->create($name);
        AuditLogger::log('department.created', 'department', $id, null, ['name' => $name]);
        return $this->success('/configuracoes#setores', "Setor \"{$name}\" criado.");
    }

    public function updateDepartment(Request $request, int $id): Response
    {
        $repo = new DepartmentRepository();
        $department = $repo->findOrFail($id);
        $name = trim((string) $request->input('name'));
        if ($name === '' || mb_strlen($name) > 120 || $repo->nameExists($name, $id)) {
            return $this->failure('/configuracoes#setores', 'Nome de setor inválido ou já existente.');
        }
        $repo->rename($id, $name);
        AuditLogger::log('department.updated', 'department', $id, $department, ['name' => $name]);
        return $this->success('/configuracoes#setores', 'Setor renomeado.');
    }

    public function deleteDepartment(Request $request, int $id): Response
    {
        $repo = new DepartmentRepository();
        $department = $repo->findOrFail($id);
        $repo->remove($id);
        AuditLogger::log('department.deleted', 'department', $id, $department, null);
        return $this->success('/configuracoes#setores', "Setor \"{$department['name']}\" excluído. Usuários vinculados ficaram sem setor.");
    }
}
