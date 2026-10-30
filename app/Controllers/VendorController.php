<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Services\SubscriptionService;
use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Auth\Permission as P;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Validator;
use Isis\Repositories\ActivityRepository;
use Isis\Repositories\AttachmentRepository;
use Isis\Repositories\DepartmentRepository;
use Isis\Repositories\UserRepository;
use Isis\Repositories\VendorRepository;
use Isis\Services\VendorQuestionnaire;
use Isis\Support\Labels;

/**
 * Gestão de fornecedores: cadastro, avaliação de proteção de dados, contratos e documentos.
 */
final class VendorController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q')), 0, 100) ?: null,
            'status' => isset(Labels::VENDOR_STATUS[(string) $request->query('status')]) ? $request->query('status') : null,
            'risk' => isset(Labels::LEVEL[(string) $request->query('risco')]) ? $request->query('risco') : null,
            'unassessed' => $request->query('sem_avaliacao') === '1',
        ];
        $repo = new VendorRepository();
        return $this->view('vendors/index', [
            'title' => 'Fornecedores',
            'vendors' => $repo->search($filters),
            'stats' => $repo->stats(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(['id' => null, 'role' => 'operador', 'data_access' => 1, 'status' => 'em_avaliacao', 'activities' => [],
            'owner_user_id' => Auth::id(), 'department_id' => Auth::user()['department_id']], 'Novo fornecedor');
    }

    public function store(Request $request): Response
    {
        if (($limit = SubscriptionService::limitReached('vendors')) !== null) {
            return $this->failure('/fornecedores', $limit);
        }
        [$data, $activities, $errors] = $this->parse($request);
        if ($errors !== []) {
            return $this->backWithErrors('/fornecedores/novo', $errors, $request->all());
        }
        $id = (new VendorRepository())->create($data + ['created_by' => Auth::id()], $activities);
        AuditLogger::log('vendor.created', 'vendor', $id, null, $data + ['operacoes' => implode(',', $activities)]);
        return $this->success("/fornecedores/{$id}", 'Fornecedor cadastrado. Próximo passo: faça a avaliação de proteção de dados.');
    }

    public function show(Request $request, int $id): Response
    {
        $vendor = (new VendorRepository())->findFullOrFail($id);
        return $this->view('vendors/show', [
            'title' => code('FOR', (int) $vendor['seq']) . ' · ' . $vendor['name'],
            'v' => $vendor,
            'evidences' => (new AttachmentRepository())->forEntity('vendor', $id),
            'canEdit' => Auth::can(P::VENDORS_EDIT),
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $vendor = (new VendorRepository())->findFullOrFail($id);
        return $this->form($vendor, 'Editar ' . $vendor['name']);
    }

    public function update(Request $request, int $id): Response
    {
        $repo = new VendorRepository();
        $before = $repo->findOrFail($id);
        [$data, $activities, $errors] = $this->parse($request);
        if ($errors !== []) {
            return $this->backWithErrors("/fornecedores/{$id}/editar", $errors, $request->all());
        }
        $repo->save($id, $data, $activities);
        AuditLogger::log('vendor.updated', 'vendor', $id, $before, $data);
        return $this->success("/fornecedores/{$id}", 'Fornecedor atualizado.');
    }

    /** Registra uma avaliação (questionário) e recalcula o nível de risco do fornecedor. */
    public function assess(Request $request, int $id): Response
    {
        $repo = new VendorRepository();
        $vendor = $repo->findOrFail($id);
        $answers = [];
        foreach (array_keys(VendorQuestionnaire::QUESTIONS) as $key) {
            $value = (string) $request->input('q_' . $key);
            if (!isset(VendorQuestionnaire::ANSWERS[$value])) {
                return $this->backWithErrors("/fornecedores/{$id}#avaliacao", ['q_' . $key => 'Responda todas as perguntas.'], $request->all(), 'Responda todas as perguntas da avaliação.');
            }
            $answers[$key] = $value;
        }
        $notes = mb_substr(trim((string) $request->input('notes')), 0, 3000) ?: null;
        $result = VendorQuestionnaire::evaluate($answers, (bool) $vendor['sensitive_data']);
        $months = $result['level'] === 'alto' ? 6 : 12;
        $repo->addAssessment($id, $answers, $result['score'], $result['level'], $notes, Auth::id(), date('Y-m-d', strtotime("+{$months} months")));
        AuditLogger::log('vendor.assessed', 'vendor', $id, ['risco' => $vendor['risk_level'], 'nota' => $vendor['last_score']], ['risco' => $result['level'], 'nota' => $result['score']]);
        return $this->success("/fornecedores/{$id}", sprintf('Avaliação registrada: nota %d, risco %s. Próxima revisão em %d meses.', $result['score'], mb_strtolower(Labels::LEVEL[$result['level']]), $months));
    }

    public function addContract(Request $request, int $id): Response
    {
        $repo = new VendorRepository();
        $repo->findOrFail($id);
        $v = Validator::make($request->all(), [
            'title' => 'required|max:190',
            'reference' => 'nullable|max:80',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'notes' => 'nullable|max:500',
        ], ['title' => 'o título do contrato', 'start_date' => 'o início', 'end_date' => 'o fim']);
        $data = $v->validated();
        if (!$v->fails() && $data['start_date'] && $data['end_date'] && $data['end_date'] < $data['start_date']) {
            $v->addError('end_date', 'O fim não pode ser antes do início.');
        }
        if ($v->fails()) {
            return $this->backWithErrors("/fornecedores/{$id}#contratos", $v->errors(), $request->all());
        }
        $contractId = $repo->addContract($id, $data + ['has_data_clauses' => $request->input('has_data_clauses') === '1' ? 1 : 0]);
        AuditLogger::log('vendor.contract_added', 'vendor', $id, null, $data + ['contrato' => $contractId]);
        return $this->success("/fornecedores/{$id}#contratos", 'Contrato registrado. Anexe o documento em Evidências e documentos.');
    }

    public function removeContract(Request $request, int $id, int $item): Response
    {
        if ((new VendorRepository())->removeContract($id, $item) === 0) {
            throw new \Isis\Core\HttpException(404);
        }
        AuditLogger::log('vendor.contract_removed', 'vendor', $id, ['contrato' => $item], null);
        return $this->success("/fornecedores/{$id}#contratos", 'Contrato removido.');
    }

    private function form(array $vendor, string $title): Response
    {
        return $this->view('vendors/form', [
            'title' => $title,
            'v' => $vendor,
            'activities' => (new ActivityRepository())->options(),
            'users' => (new UserRepository())->options(),
            'departments' => (new DepartmentRepository())->options(),
        ]);
    }

    /** @return array{0: array<string, mixed>, 1: list<int>, 2: array<string, string>} */
    private function parse(Request $request): array
    {
        $v = Validator::make($request->all(), [
            'name' => 'required|max:190',
            'cnpj' => 'nullable|cnpj',
            'service' => 'nullable|max:190',
            'role' => 'required|' . Labels::rule(Labels::VENDOR_ROLE),
            'country' => 'nullable|max:80',
            'contact_name' => 'nullable|max:150',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|max:40',
            'status' => 'required|' . Labels::rule(Labels::VENDOR_STATUS),
            'owner_user_id' => 'nullable|int',
            'department_id' => 'nullable|int',
            'next_review_date' => 'nullable|date',
            'notes' => 'nullable|max:5000',
        ], ['name' => 'o nome', 'cnpj' => 'o CNPJ', 'role' => 'o papel', 'status' => 'a situação', 'contact_email' => 'o e-mail de contato']);
        $data = $v->validated();
        foreach (['owner_user_id' => array_column((new UserRepository())->options(), 'name', 'id'), 'department_id' => (new DepartmentRepository())->options()] as $field => $options) {
            if ($data[$field] !== null && !isset($options[(int) $data[$field]])) {
                $v->addError($field, 'Opção inválida.');
            }
            $data[$field] = $data[$field] !== null ? (int) $data[$field] : null;
        }
        foreach (['data_access', 'sensitive_data', 'international'] as $flag) {
            $data[$flag] = $request->input($flag) === '1' ? 1 : 0;
        }
        $activities = array_values(array_intersect(array_map('intval', $request->inputArray('activities')), array_keys((new ActivityRepository())->options())));
        return [$data, $activities, $v->errors()];
    }
}
