<?php

declare(strict_types=1);

namespace Isis\Services;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Core\Mailer;
use Isis\Repositories\RequestRepository;
use Isis\Repositories\TenantRepository;
use Isis\Support\Deadline;
use Isis\Support\Labels;

/**
 * Registro e andamento das solicitações de titulares, inclusive as vindas do canal público.
 */
final class RequestService
{
    /**
     * Registra a solicitação (empresa do contexto). Dados pessoais vão criptografados e nunca para a auditoria.
     *
     * @param array{right_type: string, channel: string, requester_relation: string, requester_name: string, requester_email: ?string,
     *              requester_phone: ?string, requester_document: ?string, description: ?string, received_at?: ?string, activity_id?: ?int} $data
     * @return array{id: int, protocol: string}
     */
    public static function register(array $data, bool $public): array
    {
        $received = new \DateTimeImmutable($data['received_at'] ?? 'now');
        $repo = new RequestRepository();
        $created = $repo->create($data + [
            'received_at' => $received->format('Y-m-d H:i:s'),
            'due_date' => Deadline::requestDue($received),
            'status' => 'recebida',
            'created_by' => Auth::id(),
        ]);
        $note = $public ? 'Solicitação recebida pelo canal on-line.' : 'Solicitação registrada por ' . (Auth::user()['name'] ?? 'usuário') . '.';
        $repo->addEvent($created['id'], Auth::id(), 'criada', $note, null, 'recebida', true);

        AuditLogger::log('request.created', 'request', $created['id'], null, [
            'protocolo' => $created['protocol'], 'direito' => $data['right_type'], 'canal' => $data['channel'],
        ], $public ? ['user_id' => null, 'user_name' => 'Titular (canal público)'] : []);

        $tenant = (new TenantRepository())->current();
        $company = $tenant['trade_name'] ?: $tenant['legal_name'];
        if (!empty($data['requester_email'])) {
            Mailer::sendTemplate((string) $data['requester_email'], "Solicitação recebida - protocolo {$created['protocol']}", 'request-received', [
                'name' => $data['requester_name'],
                'company' => $company,
                'protocol' => $created['protocol'],
                'right' => Labels::of(Labels::RIGHT_TYPE, $data['right_type']),
                'due' => fmt_date(Deadline::requestDue($received)),
                'link' => absolute_url('/titular/' . $tenant['slug'] . '/acompanhar'),
            ]);
        }
        $dpoEmail = $tenant['dpo_email'] ?: null;
        if ($public && $dpoEmail !== null) {
            Mailer::sendTemplate($dpoEmail, "Nova solicitação de titular - {$created['protocol']}", 'request-new-dpo', [
                'company' => $company,
                'protocol' => $created['protocol'],
                'right' => Labels::of(Labels::RIGHT_TYPE, $data['right_type']),
                'due' => fmt_date(Deadline::requestDue($received)),
                'link' => absolute_url('/titulares/' . $created['id']),
            ]);
        }
        return $created;
    }

    /** Envia a resposta ao titular e encerra como respondida (ou indeferida). */
    public static function respond(array $request, string $response, string $finalStatus, bool $notify): void
    {
        $repo = new RequestRepository();
        $repo->save((int) $request['id'], [
            'response' => $response,
            'status' => $finalStatus,
            'responded_at' => date('Y-m-d H:i:s'),
        ]);
        $repo->addEvent((int) $request['id'], Auth::id(), 'resposta', $response, $request['status'], $finalStatus, true);
        AuditLogger::log('request.responded', 'request', (int) $request['id'], ['status' => $request['status']], [
            'status' => $finalStatus, 'protocolo' => $request['protocol'], 'notificado_por_email' => $notify && !empty($request['requester_email']),
        ]);

        if ($notify && !empty($request['requester_email'])) {
            $tenant = (new TenantRepository())->current();
            Mailer::sendTemplate((string) $request['requester_email'], "Resposta à sua solicitação - protocolo {$request['protocol']}", 'request-response', [
                'name' => $request['requester_name'],
                'company' => $tenant['trade_name'] ?: $tenant['legal_name'],
                'protocol' => $request['protocol'],
                'status' => Labels::of(Labels::REQUEST_STATUS, $finalStatus),
                'response' => $response,
                'dpo' => $tenant['dpo_name'],
                'contact' => $tenant['privacy_email'] ?: $tenant['dpo_email'],
            ]);
        }
    }
}
