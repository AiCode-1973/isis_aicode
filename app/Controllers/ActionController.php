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
use Isis\Repositories\ActivityRepository;
use Isis\Repositories\AttachmentRepository;
use Isis\Repositories\DepartmentRepository;
use Isis\Repositories\RiskRepository;
use Isis\Repositories\UserRepository;
use Isis\Services\AccessScope;
use Isis\Services\EvidenceService;
use Isis\Support\Labels;

/**
 * Planos de ação: medidas mitigadoras e tarefas de conformidade, com responsáveis, prazos, andamento e evidências.
 */
final class ActionController extends Controller
{
    public function index(Request $request): Response
    {
        $onlyOwn = AccessScope::onlyOwnActions();
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q')), 0, 100) ?: null,
            'status' => isset(Labels::ACTION_STATUS[(string) $request->query('status')]) ? $request->query('status') : null,
            'owner_id' => ctype_digit((string) $request->query('responsavel')) ? (int) $request->query('responsavel') : null,
            'overdue' => $request->query('vencidas') === '1',
            'open' => $request->query('abertas') === '1',
            'only_owner' => ($onlyOwn || $request->query('minhas') === '1') ? Auth::id() : null,
        ];
        $repo = new ActionRepository();
        return $this->view('actions/index', [
            'title' => $onlyOwn ? 'Minhas tarefas' : 'Planos de ação',
            'actions' => $repo->search($filters),
            'filters' => $filters,
            'stats' => $repo->stats($onlyOwn ? Auth::id() : null),
            'users' => $onlyOwn ? [] : (new UserRepository())->options(),
            'onlyOwn' => $onlyOwn,
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $repo = new ActionRepository();
        $action = $repo->findFullOrFail($id);
        if (!AccessScope::canViewAction($action)) {
            throw new HttpException(404);
        }
        return $this->view('actions/show', [
            'title' => code('ACT', (int) $action['seq']) . ' · ' . $action['title'],
            'x' => $action,
            'history' => $repo->history($id),
            'evidences' => (new AttachmentRepository())->forEntity('action', $id),
            'canEdit' => AccessScope::canEditAction($action),
            'canProgress' => AccessScope::canProgressAction($action),
        ]);
    }

    public function create(Request $request): Response
    {
        $me = Auth::user();
        $riskId = ctype_digit((string) $request->query('risco')) ? (int) $request->query('risco') : null;
        $activityId = ctype_digit((string) $request->query('operacao')) ? (int) $request->query('operacao') : null;
        $incidentId = ctype_digit((string) $request->query('incidente')) ? (int) $request->query('incidente') : null;
        $risk = $riskId !== null ? (new RiskRepository())->find($riskId) : null;
        $incident = $incidentId !== null ? (new \Isis\Repositories\IncidentRepository())->find($incidentId) : null;
        return $this->form([
            'id' => null, 'risk_id' => $risk['id'] ?? null, 'incident_id' => $incident['id'] ?? null,
            'activity_id' => $risk['activity_id'] ?? $incident['activity_id'] ?? $activityId,
            'department_id' => $risk['department_id'] ?? $me['department_id'], 'owner_user_id' => null,
            'priority' => ($risk !== null && $risk['likelihood'] * $risk['impact'] >= 10) ? 'alta' : 'media', 'status' => 'pendente',
        ], 'Nova ação');
    }

    public function store(Request $request): Response
    {
        [$data, $errors] = $this->parse($request);
        if ($errors !== []) {
            return $this->backWithErrors('/planos/nova', $errors, $request->all());
        }
        if ($data['status'] === 'concluida') {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }
        $repo = new ActionRepository();
        $id = $repo->create($data + ['created_by' => Auth::id()]);
        $repo->addUpdate($id, Auth::id(), null, $data['status'], 'Ação criada.');
        AuditLogger::log('action.created', 'action', $id, null, $data);
        return $this->success("/planos/{$id}", 'Ação criada.');
    }

    public function edit(Request $request, int $id): Response
    {
        $action = (new ActionRepository())->findFullOrFail($id);
        $this->guard($action);
        return $this->form($action, 'Editar ' . code('ACT', (int) $action['seq']));
    }

    public function update(Request $request, int $id): Response
    {
        $repo = new ActionRepository();
        $before = $repo->findOrFail($id);
        $this->guard($before);
        [$data, $errors] = $this->parse($request);
        if ($errors !== []) {
            return $this->backWithErrors("/planos/{$id}/editar", $errors, $request->all());
        }
        $data['completed_at'] = $data['status'] === 'concluida' ? ($before['completed_at'] ?? date('Y-m-d H:i:s')) : null;
        $repo->save($id, $data);
        if ($before['status'] !== $data['status']) {
            $repo->addUpdate($id, Auth::id(), $before['status'], $data['status'], 'Situação alterada na edição.');
        }
        AuditLogger::log('action.updated', 'action', $id, $before, $data);
        return $this->success("/planos/{$id}", 'Ação atualizada.');
    }

    /** Registro de andamento pelo responsável (ou por quem pode editar). */
    public function progress(Request $request, int $id): Response
    {
        $repo = new ActionRepository();
        $action = $repo->findOrFail($id);
        if (!AccessScope::canProgressAction($action)) {
            throw new HttpException(403);
        }
        $v = Validator::make($request->all(), [
            'status' => 'required|' . Labels::rule(Labels::ACTION_STATUS),
            'note' => 'nullable|max:3000',
        ], ['status' => 'a situação', 'note' => 'a observação']);
        $data = $v->validated();
        if (!$v->fails() && $data['status'] === $action['status'] && $data['note'] === null) {
            $v->addError('note', 'Registre uma observação ou mude a situação.');
        }
        if ($v->fails()) {
            return $this->backWithErrors("/planos/{$id}#andamento", $v->errors(), $request->all());
        }
        $changes = ['status' => $data['status']];
        if ($data['status'] !== $action['status']) {
            $changes['completed_at'] = $data['status'] === 'concluida' ? date('Y-m-d H:i:s') : null;
        }
        $repo->save($id, $changes);
        $repo->addUpdate($id, Auth::id(), $action['status'], $data['status'], $data['note']);
        AuditLogger::log('action.progress', 'action', $id, $action, $changes + ['observacao' => $data['note']]);
        return $this->success("/planos/{$id}", 'Andamento registrado.');
    }

    public function delete(Request $request, int $id): Response
    {
        $repo = new ActionRepository();
        $action = $repo->findOrFail($id);
        $this->guard($action);
        EvidenceService::purgeEntity('action', $id);
        $repo->remove($id);
        AuditLogger::log('action.deleted', 'action', $id, $action, null);
        return $this->success('/planos', 'Ação excluída.');
    }

    private function form(array $action, string $title): Response
    {
        return $this->view('actions/form', [
            'title' => $title,
            'x' => $action,
            'risks' => (new RiskRepository())->options() + ($action['risk_id'] ? [(int) $action['risk_id'] => code('RSK', (int) ($action['risk_seq'] ?? 0)) . ' · ' . ($action['risk_title'] ?? '')] : []),
            'activities' => (new ActivityRepository())->options(),
            'incidents' => (new \Isis\Repositories\IncidentRepository())->options(),
            'departments' => (new DepartmentRepository())->options(),
            'users' => (new UserRepository())->options(),
        ]);
    }

    private function guard(array $action): void
    {
        if (!AccessScope::canEditAction($action)) {
            AuditLogger::log('access.denied', 'action', (int) $action['id'], null, ['motivo' => 'fora do escopo do perfil']);
            throw new HttpException(403, 'Esta ação pertence a outro setor.');
        }
    }

    /** @return array{0: array<string, mixed>, 1: array<string, string>} */
    private function parse(Request $request): array
    {
        $v = Validator::make($request->all(), [
            'title' => 'required|max:190',
            'description' => 'nullable|max:5000',
            'risk_id' => 'nullable|int',
            'activity_id' => 'nullable|int',
            'incident_id' => 'nullable|int',
            'department_id' => 'nullable|int',
            'owner_user_id' => 'nullable|int',
            'priority' => 'required|' . Labels::rule(Labels::PRIORITY),
            'status' => 'required|' . Labels::rule(Labels::ACTION_STATUS),
            'due_date' => 'nullable|date',
        ], [
            'title' => 'o título', 'priority' => 'a prioridade', 'status' => 'a situação', 'due_date' => 'o prazo',
            'owner_user_id' => 'o responsável',
        ]);
        $data = $v->validated();
        $riskIds = array_column((new RiskRepository())->search([]), 'id');
        $allowed = [
            'risk_id' => array_flip(array_map('intval', $riskIds)),
            'activity_id' => (new ActivityRepository())->options(),
            'incident_id' => (new \Isis\Repositories\IncidentRepository())->options(),
            'department_id' => (new DepartmentRepository())->options(),
            'owner_user_id' => array_column((new UserRepository())->options(), 'name', 'id'),
        ];
        foreach ($allowed as $field => $options) {
            if ($data[$field] !== null && !isset($options[(int) $data[$field]])) {
                $v->addError($field, 'Opção inválida.');
            }
            $data[$field] = $data[$field] !== null ? (int) $data[$field] : null;
        }
        return [$data, $v->errors()];
    }
}
