<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Auth\Permission as P;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Validator;
use Isis\Repositories\ActivityRepository;
use Isis\Repositories\AttachmentRepository;
use Isis\Repositories\RequestRepository;
use Isis\Repositories\TenantRepository;
use Isis\Repositories\UserRepository;
use Isis\Services\RequestService;
use Isis\Support\Labels;

/**
 * Solicitações de titulares (art. 18): registro, verificação de identidade, atendimento, resposta e prazos.
 */
final class RequestController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'status' => isset(Labels::REQUEST_STATUS[(string) $request->query('status')]) ? $request->query('status') : null,
            'right_type' => isset(Labels::RIGHT_TYPE[(string) $request->query('direito')]) ? $request->query('direito') : null,
            'overdue' => $request->query('vencidas') === '1',
            'open' => $request->query('abertas') === '1',
            'email' => filter_var((string) $request->query('email'), FILTER_VALIDATE_EMAIL) ?: null,
        ];
        $repo = new RequestRepository();
        return $this->view('requests/index', [
            'title' => 'Solicitações de titulares',
            'requests' => $repo->search($filters),
            'stats' => $repo->stats(),
            'filters' => $filters,
            'tenant' => (new TenantRepository())->current(),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('requests/form', [
            'title' => 'Registrar solicitação',
            'activities' => (new ActivityRepository())->options(),
        ]);
    }

    public function store(Request $request): Response
    {
        $v = Validator::make($request->all(), [
            'right_type' => 'required|' . Labels::rule(Labels::RIGHT_TYPE),
            'channel' => 'required|' . Labels::rule(Labels::REQUEST_CHANNEL),
            'requester_relation' => 'required|in:titular,representante',
            'requester_name' => 'required|min:3|max:150',
            'requester_email' => 'nullable|email',
            'requester_phone' => 'nullable|max:40',
            'requester_document' => 'nullable|max:40',
            'description' => 'nullable|max:5000',
            'received_at' => 'nullable|date',
            'activity_id' => 'nullable|int',
        ], [
            'right_type' => 'o tipo de pedido', 'channel' => 'o canal', 'requester_name' => 'o nome do titular',
            'requester_email' => 'o e-mail', 'received_at' => 'a data de recebimento',
        ]);
        $data = $v->validated();
        if (!$v->fails() && $data['requester_email'] === null && $data['requester_phone'] === null) {
            $v->addError('requester_email', 'Informe ao menos um contato (e-mail ou telefone) para responder ao titular.');
        }
        if (!$v->fails() && $data['received_at'] !== null && $data['received_at'] > date('Y-m-d')) {
            $v->addError('received_at', 'A data de recebimento não pode estar no futuro.');
        }
        $data['activity_id'] = $this->ownActivity($data['activity_id'], $v);
        if ($v->fails()) {
            return $this->backWithErrors('/titulares/nova', $v->errors(), $request->all());
        }
        $created = RequestService::register([
            'right_type' => $data['right_type'],
            'channel' => $data['channel'],
            'requester_relation' => $data['requester_relation'],
            'requester_name' => $data['requester_name'],
            'requester_email' => $data['requester_email'],
            'requester_phone' => $data['requester_phone'],
            'requester_document' => $data['requester_document'],
            'description' => $data['description'],
            'received_at' => $data['received_at'] !== null ? $data['received_at'] . ' ' . date('H:i:s') : null,
            'activity_id' => $data['activity_id'],
        ], false);
        return $this->success("/titulares/{$created['id']}", "Solicitação registrada com o protocolo {$created['protocol']}.");
    }

    public function show(Request $request, int $id): Response
    {
        $repo = new RequestRepository();
        $item = $repo->findFullOrFail($id);
        // Acesso a dados pessoais do titular fica registrado.
        AuditLogger::log('request.viewed', 'request', $id, null, ['protocolo' => $item['protocol']]);
        return $this->view('requests/show', [
            'title' => 'Solicitação ' . $item['protocol'],
            'r' => $item,
            'events' => $repo->events($id),
            'evidences' => (new AttachmentRepository())->forEntity('request', $id),
            'users' => (new UserRepository())->options(),
            'activities' => (new ActivityRepository())->options(),
            'canManage' => Auth::can(P::REQUESTS_MANAGE),
            'tenant' => (new TenantRepository())->current(),
        ]);
    }

    /** Responsável, verificação de identidade e operação relacionada. */
    public function handling(Request $request, int $id): Response
    {
        $repo = new RequestRepository();
        $before = $repo->findOrFail($id);
        $v = Validator::make($request->all(), [
            'assigned_user_id' => 'nullable|int',
            'identity_notes' => 'nullable|max:400',
            'activity_id' => 'nullable|int',
        ]);
        $data = $v->validated();
        $users = array_column((new UserRepository())->options(), 'name', 'id');
        if ($data['assigned_user_id'] !== null && !isset($users[(int) $data['assigned_user_id']])) {
            $v->addError('assigned_user_id', 'Responsável inválido.');
        }
        $activityId = $this->ownActivity($data['activity_id'], $v);
        if ($v->fails()) {
            return $this->backWithErrors("/titulares/{$id}#atendimento", $v->errors(), $request->all());
        }
        $changes = [
            'assigned_user_id' => $data['assigned_user_id'] !== null ? (int) $data['assigned_user_id'] : null,
            'identity_verified' => $request->input('identity_verified') === '1' ? 1 : 0,
            'identity_notes' => $data['identity_notes'],
            'activity_id' => $activityId,
        ];
        $repo->save($id, $changes);
        if ((int) $before['assigned_user_id'] !== (int) $changes['assigned_user_id'] && $changes['assigned_user_id'] !== null) {
            $repo->addEvent($id, Auth::id(), 'atribuicao', 'Responsável: ' . $users[$changes['assigned_user_id']] . '.');
        }
        if ((int) $before['identity_verified'] !== $changes['identity_verified']) {
            $repo->addEvent($id, Auth::id(), 'verificacao', $changes['identity_verified'] ? 'Identidade do titular confirmada.' : 'Verificação de identidade desfeita.');
        }
        AuditLogger::log('request.updated', 'request', $id, $before, $changes);
        return $this->success("/titulares/{$id}", 'Atendimento atualizado.');
    }

    /** Mudança de situação e notas (visíveis ou não para o titular). */
    public function progress(Request $request, int $id): Response
    {
        $repo = new RequestRepository();
        $item = $repo->findOrFail($id);
        $v = Validator::make($request->all(), [
            'status' => 'required|in:recebida,em_verificacao,em_atendimento,cancelada',
            'note' => 'nullable|max:3000',
        ], ['status' => 'a situação', 'note' => 'a observação']);
        $data = $v->validated();
        if (!$v->fails() && $data['status'] === $item['status'] && $data['note'] === null) {
            $v->addError('note', 'Registre uma observação ou mude a situação.');
        }
        if ($v->fails()) {
            return $this->backWithErrors("/titulares/{$id}#andamento", $v->errors(), $request->all());
        }
        $visible = $request->input('visible') === '1';
        if ($data['status'] !== $item['status']) {
            $repo->save($id, ['status' => $data['status']]);
        }
        $repo->addEvent($id, Auth::id(), $data['status'] !== $item['status'] ? 'status' : 'nota', $data['note'], $item['status'], $data['status'], $visible);
        AuditLogger::log('request.progress', 'request', $id, ['status' => $item['status']], ['status' => $data['status'], 'visivel_ao_titular' => $visible]);
        return $this->success("/titulares/{$id}", 'Andamento registrado.');
    }

    public function respond(Request $request, int $id): Response
    {
        $item = (new RequestRepository())->findFullOrFail($id);
        $v = Validator::make($request->all(), [
            'response' => 'required|min:10|max:10000',
            'final_status' => 'required|in:respondida,indeferida',
        ], ['response' => 'a resposta', 'final_status' => 'o resultado']);
        if ($v->fails()) {
            return $this->backWithErrors("/titulares/{$id}#resposta", $v->errors(), $request->all());
        }
        $data = $v->validated();
        RequestService::respond($item, (string) $data['response'], (string) $data['final_status'], $request->input('notify') === '1');
        return $this->success("/titulares/{$id}", 'Resposta registrada' . ($request->input('notify') === '1' && $item['requester_email'] ? ' e enviada ao titular por e-mail.' : '.'));
    }

    private function ownActivity(mixed $value, Validator $v): ?int
    {
        if ($value === null) {
            return null;
        }
        if (!isset((new ActivityRepository())->options()[(int) $value])) {
            $v->addError('activity_id', 'Operação inválida.');
            return null;
        }
        return (int) $value;
    }
}
