<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Auth\TenantContext;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Validator;
use Isis\Repositories\PlanRepository;
use Isis\Repositories\PlatformRepository;
use Isis\Repositories\SubscriptionRepository;
use Isis\Services\SubscriptionService;

/**
 * Plano e assinatura da empresa: situação, uso dos limites, faturas e pedido de mudança de plano.
 */
final class SubscriptionController extends Controller
{
    public function show(Request $request): Response
    {
        $subscription = SubscriptionService::current();
        return $this->view('subscription/show', [
            'title' => 'Plano e assinatura',
            's' => $subscription,
            'state' => SubscriptionService::state($subscription),
            'price' => SubscriptionService::price($subscription),
            'usage' => SubscriptionService::usage(TenantContext::id()),
            'invoices' => (new SubscriptionRepository())->invoices(TenantContext::id()),
            'plans' => (new PlanRepository())->all(true),
            'instructions' => (new PlatformRepository())->setting('payment_instructions'),
            'billingEmail' => (new PlatformRepository())->setting('billing_email'),
        ]);
    }

    public function request(Request $request): Response
    {
        $plans = (new PlanRepository())->options();
        $v = Validator::make($request->all(), [
            'plan_code' => 'required|in:' . implode(',', array_keys($plans)),
            'billing_cycle' => 'required|in:mensal,anual',
            'message' => 'nullable|max:500',
        ], ['plan_code' => 'o plano', 'billing_cycle' => 'o ciclo', 'message' => 'a mensagem']);
        if ($v->fails()) {
            return $this->backWithErrors('/plano#mudar', $v->errors(), $request->all());
        }
        $data = $v->validated();
        SubscriptionService::requestChange($data['plan_code'], $data['billing_cycle'], $data['message']);
        return $this->success('/plano', "Pedido enviado: plano {$plans[$data['plan_code']]}, ciclo {$data['billing_cycle']}. A equipe AiCode vai confirmar e enviar a cobrança.");
    }
}
