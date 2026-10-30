<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Auth\TenantContext;
use Isis\Core\Database;
use Isis\Core\HttpException;
use Isis\Core\RateLimiter;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Session;
use Isis\Core\Validator;
use Isis\Repositories\RequestRepository;
use Isis\Services\RequestService;
use Isis\Support\Labels;

/**
 * Canal público do titular, no mesmo domínio: /titular/{empresa}.
 * Sem login: a empresa vem do endereço; o acompanhamento exige protocolo + e-mail.
 */
final class PublicRequestController extends Controller
{
    public function form(Request $request, string $slug): Response
    {
        $tenant = $this->tenant($slug);
        return $this->view('public-requests/form', [
            'title' => 'Canal do titular · ' . ($tenant['trade_name'] ?: $tenant['legal_name']),
            'tenant' => $tenant,
        ], 'public');
    }

    public function store(Request $request, string $slug): Response
    {
        $tenant = $this->tenant($slug);
        $base = "/titular/{$slug}";
        if ($request->input('website', '') !== '') {
            return $this->redirect($base . '/enviado'); // campo-isca preenchido: robô
        }
        if (RateLimiter::tooMany('dsar_public_ip', $request->ip(), 5, 60)) {
            return $this->failure($base, 'Muitas solicitações enviadas desta rede. Tente novamente mais tarde ou use o e-mail de privacidade da empresa.');
        }
        $v = Validator::make($request->all(), [
            'right_type' => 'required|' . Labels::rule(Labels::RIGHT_TYPE),
            'requester_relation' => 'required|in:titular,representante',
            'requester_name' => 'required|min:3|max:150',
            'requester_email' => 'required|email',
            'requester_phone' => 'nullable|max:40',
            'description' => 'required|min:10|max:3000',
            'accept' => 'accepted',
        ], [
            'right_type' => 'o tipo de pedido', 'requester_name' => 'seu nome completo', 'requester_email' => 'seu e-mail',
            'description' => 'a descrição do pedido', 'accept' => 'a ciência sobre o uso dos dados informados',
        ]);
        if ($v->fails()) {
            return $this->backWithErrors($base, $v->errors(), $request->all());
        }
        RateLimiter::hit('dsar_public_ip', $request->ip());
        $data = $v->validated();
        $created = TenantContext::runAs((int) $tenant['id'], static fn (): array => RequestService::register([
            'right_type' => $data['right_type'],
            'channel' => 'portal',
            'requester_relation' => $data['requester_relation'],
            'requester_name' => $data['requester_name'],
            'requester_email' => $data['requester_email'],
            'requester_phone' => $data['requester_phone'],
            'requester_document' => null,
            'description' => $data['description'],
        ], true));
        Session::flash('protocol', $created['protocol']);
        return $this->redirect($base . '/enviado');
    }

    public function sent(Request $request, string $slug): Response
    {
        $tenant = $this->tenant($slug);
        return $this->view('public-requests/sent', [
            'title' => 'Solicitação enviada',
            'tenant' => $tenant,
            'protocol' => Session::getFlash('protocol'),
        ], 'public');
    }

    public function track(Request $request, string $slug): Response
    {
        $tenant = $this->tenant($slug);
        $result = null;
        $error = null;
        if ($request->isPost()) {
            $protocol = strtoupper(trim((string) $request->input('protocol')));
            $email = trim((string) $request->input('email'));
            if (RateLimiter::tooMany('dsar_track_ip', $request->ip(), 10, 15)) {
                $error = 'Muitas consultas. Aguarde alguns minutos.';
            } elseif (!preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $protocol) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Informe o protocolo (formato XXXX-XXXX) e o e-mail usado na solicitação.';
            } else {
                $result = TenantContext::runAs((int) $tenant['id'], static function () use ($protocol, $email): ?array {
                    $repo = new RequestRepository();
                    $found = $repo->findForRequester($protocol, $email);
                    return $found === null ? null : ['request' => $found, 'events' => $repo->events((int) $found['id'], true)];
                });
                if ($result === null) {
                    RateLimiter::hit('dsar_track_ip', $request->ip());
                    $error = 'Não encontramos solicitação com esse protocolo e e-mail.';
                }
            }
        }
        return $this->view('public-requests/track', [
            'title' => 'Acompanhar solicitação',
            'tenant' => $tenant,
            'result' => $result,
            'error' => $error,
            'protocol' => (string) $request->input('protocol', ''),
        ], 'public');
    }

    /** Empresa ativa e configurada, pelo endereço público. */
    private function tenant(string $slug): array
    {
        $tenant = Database::fetch(
            "SELECT * FROM tenants WHERE slug = ? AND status = 'active' AND onboarding_completed_at IS NOT NULL",
            [$slug],
        );
        if ($tenant === null) {
            throw new HttpException(404, 'Canal de privacidade não encontrado. Confira o endereço informado pela empresa.');
        }
        return $tenant;
    }
}
