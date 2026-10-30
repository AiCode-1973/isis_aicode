<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Auth\Permission as P;
use Isis\Core\HttpException;
use Isis\Core\Mailer;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Validator;
use Isis\Repositories\ActionRepository;
use Isis\Repositories\ActivityRepository;
use Isis\Repositories\AttachmentRepository;
use Isis\Repositories\DepartmentRepository;
use Isis\Repositories\IncidentRepository;
use Isis\Repositories\TenantRepository;
use Isis\Repositories\UserRepository;
use Isis\Support\Deadline;
use Isis\Support\Labels;

/**
 * Incidentes de segurança: comunicação interna, classificação, investigação, providências,
 * comunicação à ANPD e aos titulares (art. 48) e histórico.
 */
final class IncidentController extends Controller
{
    public function index(Request $request): Response
    {
        $onlyMine = !Auth::can(P::INCIDENTS_VIEW);
        $filters = [
            'status' => isset(Labels::INCIDENT_STATUS[(string) $request->query('status')]) ? $request->query('status') : null,
            'severity' => isset(Labels::SEVERITY[(string) $request->query('gravidade')]) ? $request->query('gravidade') : null,
            'open' => $request->query('abertos') === '1',
            'reported_by' => $onlyMine ? Auth::id() : null,
        ];
        $repo = new IncidentRepository();
        return $this->view('incidents/index', [
            'title' => $onlyMine ? 'Meus comunicados de incidente' : 'Incidentes de segurança',
            'incidents' => $repo->search($filters),
            'stats' => $onlyMine ? null : $repo->stats(),
            'filters' => $filters,
            'onlyMine' => $onlyMine,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('incidents/form', [
            'title' => 'Comunicar incidente',
            'departments' => (new DepartmentRepository())->options(),
        ]);
    }

    public function store(Request $request): Response
    {
        $v = Validator::make($request->all(), [
            'title' => 'required|min:5|max:190',
            'category' => 'required|' . Labels::rule(Labels::INCIDENT_CATEGORY),
            'description' => 'required|min:10|max:10000',
            'occurred_at' => 'nullable|date',
            'detected_at' => 'required|date',
            'personal_data' => 'required|in:sim,nao,nao_sei',
            'department_id' => 'nullable|int',
        ], [
            'title' => 'um título curto', 'category' => 'o tipo', 'description' => 'o que aconteceu',
            'detected_at' => 'quando foi percebido', 'occurred_at' => 'quando ocorreu', 'personal_data' => 'se há dados pessoais envolvidos',
        ]);
        $data = $v->validated();
        if (!$v->fails() && $data['detected_at'] > date('Y-m-d')) {
            $v->addError('detected_at', 'A data não pode estar no futuro.');
        }
        if ($data['department_id'] !== null && !isset((new DepartmentRepository())->options()[(int) $data['department_id']])) {
            $v->addError('department_id', 'Setor inválido.');
        }
        if ($v->fails()) {
            return $this->backWithErrors('/incidentes/novo', $v->errors(), $request->all());
        }

        $repo = new IncidentRepository();
        $id = $repo->create([
            'title' => $data['title'],
            'category' => $data['category'],
            'description' => $data['description'],
            'occurred_at' => $data['occurred_at'] !== null ? $data['occurred_at'] . ' 00:00:00' : null,
            'detected_at' => $data['detected_at'] . ' ' . ($data['detected_at'] === date('Y-m-d') ? date('H:i:s') : '00:00:00'),
            'personal_data' => ['sim' => 1, 'nao' => 0, 'nao_sei' => null][$data['personal_data']],
            'department_id' => $data['department_id'] !== null ? (int) $data['department_id'] : null,
            'reported_by' => Auth::id(),
            'status' => 'comunicado',
        ]);
        $repo->addEvent($id, Auth::id(), 'registro', 'Incidente comunicado por ' . Auth::user()['name'] . '.', null, 'comunicado');
        AuditLogger::log('incident.reported', 'incident', $id, null, ['titulo' => $data['title'], 'tipo' => $data['category'], 'dados_pessoais' => $data['personal_data']]);

        $tenant = (new TenantRepository())->current();
        if ($tenant['dpo_email']) {
            Mailer::sendTemplate($tenant['dpo_email'], 'Novo incidente de segurança comunicado', 'incident-new', [
                'company' => $tenant['trade_name'] ?: $tenant['legal_name'],
                'code' => code('INC', (int) $repo->find($id)['seq']),
                'title' => $data['title'],
                'reporter' => Auth::user()['name'],
                'link' => absolute_url('/incidentes/' . $id),
            ]);
        }
        return $this->success("/incidentes/{$id}", 'Incidente comunicado. O encarregado foi avisado e vai analisar. Obrigado por informar rapidamente.');
    }

    public function show(Request $request, int $id): Response
    {
        $repo = new IncidentRepository();
        $incident = $repo->findFullOrFail($id);
        if (!Auth::can(P::INCIDENTS_VIEW) && (int) $incident['reported_by'] !== Auth::id()) {
            throw new HttpException(404);
        }
        $actions = Auth::can(P::ACTIONS_VIEW)
            ? array_values(array_filter((new ActionRepository())->search([]), static fn (array $x): bool => (int) $x['incident_id'] === $id))
            : [];
        return $this->view('incidents/show', [
            'title' => code('INC', (int) $incident['seq']) . ' · ' . $incident['title'],
            'i' => $incident,
            'events' => $repo->events($id),
            'evidences' => (new AttachmentRepository())->forEntity('incident', $id),
            'actions' => $actions,
            'canManage' => Auth::can(P::INCIDENTS_MANAGE),
            'users' => (new UserRepository())->options(),
            'departments' => (new DepartmentRepository())->options(),
            'activities' => (new ActivityRepository())->options(),
        ]);
    }

    /** Classificação: gravidade, dados envolvidos, risco aos titulares e responsáveis. */
    public function classify(Request $request, int $id): Response
    {
        $repo = new IncidentRepository();
        $before = $repo->findOrFail($id);
        $v = Validator::make($request->all(), [
            'title' => 'required|min:5|max:190',
            'category' => 'required|' . Labels::rule(Labels::INCIDENT_CATEGORY),
            'severity' => 'required|' . Labels::rule(Labels::SEVERITY),
            'personal_data' => 'required|in:sim,nao,nao_sei',
            'subjects_affected' => 'nullable|int',
            'risk_to_subjects' => 'required|' . Labels::rule(Labels::RISK_TO_SUBJECTS),
            'owner_user_id' => 'nullable|int',
            'department_id' => 'nullable|int',
            'activity_id' => 'nullable|int',
            'occurred_at' => 'nullable|date',
        ], ['severity' => 'a gravidade', 'risk_to_subjects' => 'a avaliação de risco aos titulares']);
        $data = $v->validated();
        $allowed = [
            'owner_user_id' => array_column((new UserRepository())->options(), 'name', 'id'),
            'department_id' => (new DepartmentRepository())->options(),
            'activity_id' => (new ActivityRepository())->options(),
        ];
        foreach ($allowed as $field => $options) {
            if ($data[$field] !== null && !isset($options[(int) $data[$field]])) {
                $v->addError($field, 'Opção inválida.');
            }
            $data[$field] = $data[$field] !== null ? (int) $data[$field] : null;
        }
        if ($v->fails()) {
            return $this->backWithErrors("/incidentes/{$id}#classificacao", $v->errors(), $request->all());
        }

        $changes = [
            'title' => $data['title'],
            'category' => $data['category'],
            'severity' => $data['severity'],
            'personal_data' => ['sim' => 1, 'nao' => 0, 'nao_sei' => null][$data['personal_data']],
            'sensitive_data' => $request->input('sensitive_data') === '1' ? 1 : 0,
            'subjects_affected' => $data['subjects_affected'] !== null ? (int) $data['subjects_affected'] : null,
            'risk_to_subjects' => $data['risk_to_subjects'],
            'owner_user_id' => $data['owner_user_id'],
            'department_id' => $data['department_id'],
            'activity_id' => $data['activity_id'],
            'occurred_at' => $data['occurred_at'] !== null ? $data['occurred_at'] . ' 00:00:00' : null,
        ];
        // Prazo de referência para comunicar a ANPD quando há risco ou dano relevante.
        $changes['anpd_deadline'] = $data['risk_to_subjects'] === 'relevante'
            ? ($before['anpd_deadline'] ?? Deadline::addBusinessDays(new \DateTimeImmutable((string) $before['detected_at']), Deadline::INCIDENT_BUSINESS_DAYS))
            : null;
        if ($before['status'] === 'comunicado') {
            $changes['status'] = 'em_analise';
        }
        $repo->save($id, $changes);
        $repo->addEvent($id, Auth::id(), 'classificacao', sprintf(
            'Classificado: gravidade %s; %s.',
            mb_strtolower(Labels::SEVERITY[$data['severity']]),
            mb_strtolower(Labels::RISK_TO_SUBJECTS[$data['risk_to_subjects']]),
        ), $before['status'], $changes['status'] ?? $before['status']);
        AuditLogger::log('incident.classified', 'incident', $id, $before, $changes);
        return $this->success("/incidentes/{$id}", 'Classificação salva.' . ($changes['anpd_deadline'] ? ' Prazo de referência para comunicar a ANPD: ' . fmt_date($changes['anpd_deadline']) . '.' : ''));
    }

    /** Contenção, causa e lições aprendidas (texto criptografado). */
    public function treatment(Request $request, int $id): Response
    {
        $repo = new IncidentRepository();
        $before = $repo->findOrFail($id);
        $v = Validator::make($request->all(), [
            'containment' => 'nullable|max:10000',
            'root_cause' => 'nullable|max:10000',
            'lessons' => 'nullable|max:10000',
        ]);
        if ($v->fails()) {
            return $this->backWithErrors("/incidentes/{$id}#tratamento", $v->errors(), $request->all());
        }
        $repo->save($id, $v->validated());
        AuditLogger::log('incident.treatment', 'incident', $id, null, ['campos' => 'contenção, causa e lições (conteúdo protegido)']);
        return $this->success("/incidentes/{$id}", 'Investigação e providências salvas.');
    }

    public function progress(Request $request, int $id): Response
    {
        $repo = new IncidentRepository();
        $incident = $repo->findOrFail($id);
        $v = Validator::make($request->all(), [
            'status' => 'required|' . Labels::rule(Labels::INCIDENT_STATUS),
            'note' => 'nullable|max:3000',
        ], ['status' => 'a situação', 'note' => 'a providência']);
        $data = $v->validated();
        if (!$v->fails() && $data['status'] === $incident['status'] && $data['note'] === null) {
            $v->addError('note', 'Descreva a providência ou mude a situação.');
        }
        if (!$v->fails() && $data['status'] === 'encerrado' && $incident['risk_to_subjects'] === 'relevante' && $incident['anpd_notified_at'] === null) {
            $v->addError('status', 'Registre a comunicação à ANPD antes de encerrar um incidente com risco relevante.');
        }
        if ($v->fails()) {
            return $this->backWithErrors("/incidentes/{$id}#andamento", $v->errors(), $request->all());
        }
        if ($data['status'] !== $incident['status']) {
            $repo->save($id, ['status' => $data['status'], 'closed_at' => $data['status'] === 'encerrado' ? date('Y-m-d H:i:s') : null]);
        }
        $repo->addEvent($id, Auth::id(), $data['status'] !== $incident['status'] ? 'status' : 'providencia', $data['note'], $incident['status'], $data['status']);
        AuditLogger::log('incident.progress', 'incident', $id, ['status' => $incident['status']], ['status' => $data['status']]);
        return $this->success("/incidentes/{$id}", 'Andamento registrado.');
    }

    /** Registro das comunicações à ANPD e aos titulares. */
    public function communication(Request $request, int $id): Response
    {
        $repo = new IncidentRepository();
        $before = $repo->findOrFail($id);
        $v = Validator::make($request->all(), [
            'anpd_notified_at' => 'nullable|date',
            'anpd_protocol' => 'nullable|max:80',
            'subjects_notified_at' => 'nullable|date',
            'note' => 'nullable|max:3000',
        ], ['anpd_notified_at' => 'a data de comunicação à ANPD', 'subjects_notified_at' => 'a data de comunicação aos titulares']);
        $data = $v->validated();
        foreach (['anpd_notified_at', 'subjects_notified_at'] as $field) {
            if ($data[$field] !== null && $data[$field] > date('Y-m-d')) {
                $v->addError($field, 'A data não pode estar no futuro.');
            }
        }
        if ($v->fails()) {
            return $this->backWithErrors("/incidentes/{$id}#comunicacao", $v->errors(), $request->all());
        }
        $changes = [
            'anpd_notified_at' => $data['anpd_notified_at'] !== null ? $data['anpd_notified_at'] . ' 12:00:00' : null,
            'anpd_protocol' => $data['anpd_protocol'],
            'subjects_notified_at' => $data['subjects_notified_at'] !== null ? $data['subjects_notified_at'] . ' 12:00:00' : null,
        ];
        $repo->save($id, $changes);
        $parts = [];
        if ($changes['anpd_notified_at'] && substr((string) $before['anpd_notified_at'], 0, 10) !== $data['anpd_notified_at']) {
            $parts[] = 'ANPD comunicada em ' . fmt_date($data['anpd_notified_at']) . ($data['anpd_protocol'] ? ' (protocolo ' . $data['anpd_protocol'] . ')' : '') . '.';
        }
        if ($changes['subjects_notified_at'] && substr((string) $before['subjects_notified_at'], 0, 10) !== $data['subjects_notified_at']) {
            $parts[] = 'Titulares comunicados em ' . fmt_date($data['subjects_notified_at']) . '.';
        }
        $note = trim(implode(' ', $parts) . ' ' . ($data['note'] ?? ''));
        if ($note !== '') {
            $repo->addEvent($id, Auth::id(), 'comunicacao', $note);
        }
        AuditLogger::log('incident.communication', 'incident', $id, $before, $changes);
        return $this->success("/incidentes/{$id}", 'Comunicações registradas.');
    }
}
