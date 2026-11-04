<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Core\HttpException;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Validator;
use Isis\Repositories\ActionRepository;
use Isis\Repositories\DepartmentRepository;
use Isis\Repositories\RiskRepository;
use Isis\Repositories\UserRepository;
use Isis\Support\Labels;
use Isis\Support\ModelLibrary;

/**
 * Modelos prontos por situação: a biblioteca (/modelos) e a criação de um pacote de ações a partir de um modelo de plano.
 * Os demais tipos são aplicados nos próprios formulários (?modelo=...), sem gravar nada.
 */
final class ModelController extends Controller
{
    public function index(Request $request): Response
    {
        $types = ModelLibrary::allowedTypes();
        $type = (string) $request->query('tipo', '');
        if (!isset($types[$type])) {
            $type = (string) array_key_first($types);
        }
        $riskId = ctype_digit((string) $request->query('risco')) ? (int) $request->query('risco') : null;
        return $this->view('models/index', [
            'title' => 'Modelos prontos',
            'types' => $types,
            'type' => $type,
            'models' => $type !== '' ? ModelLibrary::ofType($type, $type === 'inventario' ? (Auth::tenant()['segment_code'] ?? null) : null) : [],
            'segment' => Auth::tenant()['segment_code'] ?? null,
            'riskId' => $riskId,
        ]);
    }

    /** Revisão do pacote de ações antes de criar: títulos, prioridade, prazo e responsável editáveis. */
    public function plan(Request $request, string $slug): Response
    {
        $model = $this->planOrFail($slug);
        $riskId = ctype_digit((string) $request->query('risco')) ? (int) $request->query('risco') : null;
        $risk = $riskId !== null ? (new RiskRepository())->find($riskId) : null;
        return $this->view('models/plan', [
            'title' => 'Plano de ação: ' . $model['title'],
            'model' => $model,
            'risk' => $risk,
            'risks' => (new RiskRepository())->options(),
            'users' => (new UserRepository())->options(),
            'departments' => (new DepartmentRepository())->options(),
        ]);
    }

    public function createPlan(Request $request, string $slug): Response
    {
        $model = $this->planOrFail($slug);
        $path = '/planos/modelo/' . $slug;
        $v = Validator::make($request->all(), [
            'risk_id' => 'nullable|int',
            'department_id' => 'nullable|int',
        ]);
        $common = $v->validated();
        if ($common['risk_id'] !== null && !isset((new RiskRepository())->options()[(int) $common['risk_id']])) {
            $v->addError('risk_id', 'Risco inválido.');
        }
        if ($common['department_id'] !== null && !isset((new DepartmentRepository())->options()[(int) $common['department_id']])) {
            $v->addError('department_id', 'Setor inválido.');
        }
        $users = array_column((new UserRepository())->options(), 'name', 'id');
        $include = $request->inputArray('include');
        $titles = $request->inputArray('action_title');
        $priorities = $request->inputArray('action_priority');
        $dues = $request->inputArray('action_due');
        $owners = $request->inputArray('action_owner');

        $actions = [];
        foreach (array_keys($model['actions']) as $i) {
            if (($include[$i] ?? '') !== '1') {
                continue;
            }
            $title = trim($titles[$i] ?? '');
            $priority = $priorities[$i] ?? 'media';
            $due = trim($dues[$i] ?? '');
            $owner = trim($owners[$i] ?? '');
            if (mb_strlen($title) < 3 || mb_strlen($title) > 190) {
                $v->addError('action_title_' . $i, 'Informe o título da ação (3 a 190 caracteres).');
            }
            if (!isset(Labels::PRIORITY[$priority])) {
                $v->addError('action_priority_' . $i, 'Prioridade inválida.');
            }
            if ($due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
                $v->addError('action_due_' . $i, 'Data inválida.');
            }
            if ($owner !== '' && !isset($users[(int) $owner])) {
                $v->addError('action_owner_' . $i, 'Responsável inválido.');
            }
            $actions[] = [
                'title' => $title,
                'description' => $model['actions'][$i][1],
                'priority' => $priority,
                'status' => 'pendente',
                'due_date' => $due !== '' ? $due : null,
                'owner_user_id' => $owner !== '' ? (int) $owner : null,
                'risk_id' => $common['risk_id'] !== null ? (int) $common['risk_id'] : null,
                'department_id' => $common['department_id'] !== null ? (int) $common['department_id'] : null,
            ];
        }
        if ($actions === [] && !$v->fails()) {
            $v->addError('include', 'Marque pelo menos uma ação para criar.');
        }
        if ($v->fails()) {
            return $this->backWithErrors($path . ($common['risk_id'] ? '?risco=' . (int) $common['risk_id'] : ''), $v->errors(), $request->all());
        }

        $repo = new ActionRepository();
        foreach ($actions as $data) {
            $id = $repo->create($data + ['created_by' => Auth::id()]);
            $repo->addUpdate($id, Auth::id(), null, 'pendente', 'Ação criada a partir do modelo "' . $model['title'] . '".');
            AuditLogger::log('action.created', 'action', $id, null, $data + ['modelo' => $slug]);
        }
        $target = $common['risk_id'] !== null ? '/riscos/' . (int) $common['risk_id'] : '/planos';
        return $this->success($target, plural(count($actions), 'ação criada', 'ações criadas') . ' a partir do modelo "' . $model['title'] . '".');
    }

    private function planOrFail(string $slug): array
    {
        $model = ModelLibrary::find('plano', $slug);
        if ($model === null) {
            throw new HttpException(404, 'Modelo não encontrado.');
        }
        return $model;
    }
}
