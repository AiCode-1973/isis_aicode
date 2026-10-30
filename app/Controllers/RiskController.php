<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Auth\Permission as P;
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
use Isis\Services\RiskMatrix;
use Isis\Support\Labels;

/**
 * Gestão de riscos: avaliação (probabilidade x impacto), classificação, tratamento e responsáveis.
 */
final class RiskController extends Controller
{
    public function index(Request $request): Response
    {
        $cell = null;
        if (preg_match('/^([1-5])-([1-5])$/', (string) $request->query('celula'), $m)) {
            $cell = [(int) $m[1], (int) $m[2]];
        }
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q')), 0, 100) ?: null,
            'status' => isset(Labels::RISK_STATUS[(string) $request->query('status')]) ? $request->query('status') : null,
            'level' => in_array($request->query('nivel'), ['baixo', 'medio', 'alto', 'critico'], true) ? $request->query('nivel') : null,
            'category' => isset(Labels::RISK_CATEGORY[(string) $request->query('categoria')]) ? $request->query('categoria') : null,
            'cell' => $cell,
            'open' => $request->query('abertos') === '1',
        ];
        $repo = new RiskRepository();
        return $this->view('risks/index', [
            'title' => 'Riscos',
            'risks' => $repo->search($filters),
            'filters' => $filters,
            'heatmap' => $repo->heatmap(),
            'stats' => $repo->stats(),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $risk = (new RiskRepository())->findFullOrFail($id);
        $actions = Auth::can(P::ACTIONS_VIEW) ? (new ActionRepository())->search(['risk_id' => $id]) : [];
        if (AccessScope::onlyOwnActions()) {
            $actions = array_values(array_filter($actions, AccessScope::canViewAction(...)));
        }
        return $this->view('risks/show', [
            'title' => code('RSK', (int) $risk['seq']) . ' · ' . $risk['title'],
            'r' => $risk,
            'actions' => $actions,
            'evidences' => (new AttachmentRepository())->forEntity('risk', $id),
            'canEdit' => AccessScope::canEditRisk($risk),
        ]);
    }

    public function create(Request $request): Response
    {
        $activityId = ctype_digit((string) $request->query('operacao')) ? (int) $request->query('operacao') : null;
        $me = Auth::user();
        return $this->form([
            'id' => null, 'activity_id' => $activityId, 'category' => 'seguranca', 'likelihood' => 3, 'impact' => 3,
            'treatment' => 'mitigar', 'status' => 'identificado', 'owner_user_id' => $me['id'], 'department_id' => $me['department_id'],
        ], 'Novo risco');
    }

    public function store(Request $request): Response
    {
        [$data, $errors] = $this->parse($request);
        if ($errors !== []) {
            return $this->backWithErrors('/riscos/novo', $errors, $request->all());
        }
        $id = (new RiskRepository())->create($data + ['created_by' => Auth::id()]);
        AuditLogger::log('risk.created', 'risk', $id, null, $data + ['nivel' => RiskMatrix::levelOf($data['likelihood'], $data['impact'])]);
        return $this->success("/riscos/{$id}", 'Risco registrado. Se for mitigar, crie as ações do plano.');
    }

    public function edit(Request $request, int $id): Response
    {
        $risk = (new RiskRepository())->findFullOrFail($id);
        $this->guard($risk);
        return $this->form($risk, 'Editar ' . code('RSK', (int) $risk['seq']));
    }

    public function update(Request $request, int $id): Response
    {
        $repo = new RiskRepository();
        $before = $repo->findOrFail($id);
        $this->guard($before);
        [$data, $errors] = $this->parse($request);
        if ($errors !== []) {
            return $this->backWithErrors("/riscos/{$id}/editar", $errors, $request->all());
        }
        $repo->save($id, $data);
        AuditLogger::log('risk.updated', 'risk', $id, $before, $data);
        return $this->success("/riscos/{$id}", 'Risco atualizado.');
    }

    public function delete(Request $request, int $id): Response
    {
        $repo = new RiskRepository();
        $risk = $repo->findOrFail($id);
        $this->guard($risk);
        EvidenceService::purgeEntity('risk', $id);
        $repo->remove($id);
        AuditLogger::log('risk.deleted', 'risk', $id, $risk, null);
        return $this->success('/riscos', 'Risco excluído. As ações vinculadas foram mantidas, sem o vínculo.');
    }

    private function form(array $risk, string $title): Response
    {
        return $this->view('risks/form', [
            'title' => $title,
            'r' => $risk,
            'activities' => (new ActivityRepository())->options(),
            'departments' => (new DepartmentRepository())->options(),
            'users' => (new UserRepository())->options(),
        ]);
    }

    private function guard(array $risk): void
    {
        if (!AccessScope::canEditRisk($risk)) {
            AuditLogger::log('access.denied', 'risk', (int) $risk['id'], null, ['motivo' => 'fora do escopo do perfil']);
            throw new HttpException(403, 'Este risco pertence a outro setor.');
        }
    }

    /** @return array{0: array<string, mixed>, 1: array<string, string>} */
    private function parse(Request $request): array
    {
        $scale = 'in:1,2,3,4,5';
        $v = Validator::make($request->all(), [
            'title' => 'required|max:190',
            'description' => 'nullable|max:5000',
            'category' => 'required|' . Labels::rule(Labels::RISK_CATEGORY),
            'activity_id' => 'nullable|int',
            'department_id' => 'nullable|int',
            'cause' => 'nullable|max:5000',
            'consequence' => 'nullable|max:5000',
            'likelihood' => 'required|' . $scale,
            'impact' => 'required|' . $scale,
            'residual_likelihood' => 'nullable|' . $scale,
            'residual_impact' => 'nullable|' . $scale,
            'treatment' => 'required|' . Labels::rule(Labels::RISK_TREATMENT),
            'treatment_notes' => 'nullable|max:5000',
            'status' => 'required|' . Labels::rule(Labels::RISK_STATUS),
            'owner_user_id' => 'nullable|int',
            'review_date' => 'nullable|date',
        ], [
            'title' => 'o título', 'category' => 'a categoria', 'likelihood' => 'a probabilidade', 'impact' => 'o impacto',
            'treatment' => 'o tratamento', 'status' => 'a situação', 'review_date' => 'a data de revisão',
        ]);
        $data = $v->validated();

        $allowed = [
            'activity_id' => (new ActivityRepository())->options(),
            'department_id' => (new DepartmentRepository())->options(),
            'owner_user_id' => array_column((new UserRepository())->options(), 'name', 'id'),
        ];
        foreach ($allowed as $field => $options) {
            if ($data[$field] !== null && !isset($options[(int) $data[$field]])) {
                $v->addError($field, 'Opção inválida.');
            }
            $data[$field] = $data[$field] !== null ? (int) $data[$field] : null;
        }
        foreach (['likelihood', 'impact', 'residual_likelihood', 'residual_impact'] as $field) {
            $data[$field] = $data[$field] !== null ? (int) $data[$field] : null;
        }
        if (($data['residual_likelihood'] === null) !== ($data['residual_impact'] === null)) {
            $v->addError('residual_impact', 'Informe probabilidade e impacto residuais juntos (ou deixe ambos em branco).');
        }
        if ($data['treatment'] === 'aceitar' && trim((string) $data['treatment_notes']) === '') {
            $v->addError('treatment_notes', 'Ao aceitar um risco, registre a justificativa.');
        }
        return [$data, $v->errors()];
    }
}
