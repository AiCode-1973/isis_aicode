<?php

declare(strict_types=1);

namespace Isis\Services;

use Isis\Audit\AuditLogger;
use Isis\Auth\TenantContext;
use Isis\Core\Config;
use Isis\Core\Database;
use Isis\Core\Mailer;
use Isis\Repositories\PlanRepository;
use Isis\Repositories\PlatformRepository;
use Isis\Repositories\SubscriptionRepository;

/**
 * Planos, teste grátis, limites de uso e cobrança manual.
 *
 * Situação efetiva (calculada na hora, sem depender de rotina agendada):
 *  - teste            dentro do período de teste
 *  - teste_encerrado  teste terminou sem pagamento → somente leitura
 *  - ativa            período pago em vigor
 *  - em_atraso        período venceu, dentro da tolerância → acesso normal com aviso
 *  - vencida          venceu e passou da tolerância → somente leitura
 *  - suspensa / cancelada  definidas pela plataforma → somente leitura
 *
 * Em somente leitura a empresa consulta e exporta tudo, e continua atendendo titulares e
 * tratando incidentes (obrigações legais não ficam bloqueadas por cobrança).
 */
final class SubscriptionService
{
    public const array STATUS_LABELS = [
        'teste' => 'Teste grátis',
        'teste_encerrado' => 'Teste encerrado',
        'ativa' => 'Ativa',
        'em_atraso' => 'Pagamento em atraso',
        'vencida' => 'Vencida',
        'suspensa' => 'Suspensa',
        'cancelada' => 'Cancelada',
    ];

    public const array STORED_STATUSES = ['teste' => 'Teste grátis', 'ativa' => 'Ativa', 'suspensa' => 'Suspensa', 'cancelada' => 'Cancelada'];
    public const array CYCLES = ['mensal' => 'Mensal', 'anual' => 'Anual'];
    public const array PAYMENT_METHODS = ['pix' => 'Pix', 'boleto' => 'Boleto', 'transferencia' => 'Transferência', 'cartao' => 'Cartão', 'outro' => 'Outro'];

    /** Recursos com limite por plano: chave => [coluna do plano, rótulo] */
    public const array LIMITS = [
        'users' => ['max_users', 'usuários'],
        'vendors' => ['max_vendors', 'fornecedores'],
        'trainings' => ['max_trainings', 'treinamentos'],
    ];

    /** Rotas que continuam aceitando alterações em somente leitura (obrigações legais). */
    public const array ALWAYS_WRITABLE = ['/titulares', '/incidentes'];

    /** @var array<int, array<string, mixed>> */
    private static array $cache = [];

    public static function startTrial(int $tenantId, ?string $plan = null, ?string $today = null): int
    {
        $today ??= date('Y-m-d');
        return (new SubscriptionRepository())->create([
            'tenant_id' => $tenantId,
            'plan_code' => $plan ?? (string) Config::get('billing.trial_plan', 'profissional'),
            'billing_cycle' => 'mensal',
            'status' => 'teste',
            'trial_ends_at' => self::addDays($today, (int) Config::get('billing.trial_days', 14)),
        ]);
    }

    /** Assinatura da empresa (cria um teste grátis se ainda não houver). */
    public static function forTenant(int $tenantId): array
    {
        if (isset(self::$cache[$tenantId])) {
            return self::$cache[$tenantId];
        }
        $repo = new SubscriptionRepository();
        $subscription = $repo->forTenant($tenantId);
        if ($subscription === null) {
            self::startTrial($tenantId);
            $subscription = $repo->forTenant($tenantId);
        }
        return self::$cache[$tenantId] = $subscription ?? throw new \LogicException('Assinatura não encontrada.');
    }

    public static function current(): array
    {
        return self::forTenant(TenantContext::id());
    }

    public static function forget(?int $tenantId = null): void
    {
        if ($tenantId === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$tenantId]);
        }
    }

    /**
     * @return array{status: string, label: string, read_only: bool, days_left: ?int, ends_at: ?string, tone: string}
     */
    public static function state(array $subscription, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $grace = (int) Config::get('billing.grace_days', 7);
        $endsAt = null;
        $status = (string) $subscription['status'];

        if ($status === 'teste') {
            $endsAt = $subscription['trial_ends_at'];
            $status = $endsAt !== null && $endsAt >= $today ? 'teste' : 'teste_encerrado';
        } elseif ($status === 'ativa') {
            $endsAt = $subscription['current_period_end'];
            if ($endsAt === null || $endsAt >= $today) {
                $status = 'ativa';
            } else {
                $status = self::addDays($endsAt, $grace) >= $today ? 'em_atraso' : 'vencida';
            }
        } else {
            $endsAt = $subscription['current_period_end'] ?? $subscription['trial_ends_at'];
        }

        $daysLeft = $endsAt !== null ? self::daysBetween($today, (string) $endsAt) : null;
        $tone = match ($status) {
            'ativa' => 'success',
            'teste' => $daysLeft !== null && $daysLeft <= 3 ? 'warning' : 'info',
            'em_atraso' => 'warning',
            default => 'danger',
        };
        return [
            'status' => $status,
            'label' => self::STATUS_LABELS[$status],
            'read_only' => in_array($status, ['teste_encerrado', 'vencida', 'suspensa', 'cancelada'], true),
            'days_left' => $daysLeft,
            'ends_at' => $endsAt,
            'tone' => $tone,
        ];
    }

    public static function isReadOnly(?int $tenantId = null): bool
    {
        return self::state(self::forTenant($tenantId ?? TenantContext::id()))['read_only'];
    }

    public static function writableInReadOnly(string $path): bool
    {
        foreach (self::ALWAYS_WRITABLE as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }
        return false;
    }

    /** Valor cobrado por período (preço combinado ou de tabela). Null = sob consulta sem preço combinado. */
    public static function price(array $subscription): ?int
    {
        if ($subscription['custom_price_cents'] !== null) {
            return (int) $subscription['custom_price_cents'];
        }
        $column = $subscription['billing_cycle'] === 'anual' ? 'price_yearly_cents' : 'price_monthly_cents';
        return $subscription[$column] === null ? null : (int) $subscription[$column];
    }

    /** @return array<string, array{used: int, limit: ?int, label: string}> */
    public static function usage(int $tenantId): array
    {
        $subscription = self::forTenant($tenantId);
        $used = [
            'users' => (int) Database::value("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'active'", [$tenantId])
                + (int) Database::value('SELECT COUNT(*) FROM invitations WHERE tenant_id = ? AND accepted_at IS NULL AND revoked_at IS NULL AND expires_at > NOW()', [$tenantId]),
            'vendors' => (int) Database::value("SELECT COUNT(*) FROM vendors WHERE tenant_id = ? AND status <> 'encerrado'", [$tenantId]),
            'trainings' => (int) Database::value("SELECT COUNT(*) FROM training_modules WHERE tenant_id = ? AND status <> 'arquivado'", [$tenantId]),
        ];
        $result = [];
        foreach (self::LIMITS as $key => [$column, $label]) {
            $result[$key] = ['used' => $used[$key], 'limit' => $subscription[$column] === null ? null : (int) $subscription[$column], 'label' => $label];
        }
        return $result;
    }

    /** Mensagem de bloqueio se a empresa atual já atingiu o limite do plano para o recurso; null se pode criar. */
    public static function limitReached(string $resource, int $adding = 1): ?string
    {
        $usage = self::usage(TenantContext::id())[$resource] ?? null;
        if ($usage === null || $usage['limit'] === null || $usage['used'] + $adding <= $usage['limit']) {
            return null;
        }
        $plan = self::current()['plan_name'];
        $hint = $resource === 'users' ? ' (contando usuários ativos e convites pendentes)' : '';
        return "O plano {$plan} permite até {$usage['limit']} {$usage['label']}{$hint}. Para incluir mais, mude de plano em Plano e assinatura.";
    }

    /** Pedido de mudança de plano feito pela empresa; avisa a equipe da plataforma. */
    public static function requestChange(string $planCode, string $cycle, ?string $message): void
    {
        $subscription = self::current();
        $plan = (new PlanRepository())->find($planCode) ?? throw new \InvalidArgumentException('Plano inválido.');
        (new SubscriptionRepository())->update((int) $subscription['id'], [
            'requested_plan_code' => $planCode,
            'requested_cycle' => $cycle,
            'requested_message' => $message,
            'requested_at' => date('Y-m-d H:i:s'),
        ]);
        self::forget();
        AuditLogger::log('subscription.change_requested', 'subscription', (int) $subscription['id'], null, [
            'plano' => $plan['name'], 'ciclo' => $cycle, 'mensagem' => $message,
        ]);

        $tenant = Database::fetch('SELECT legal_name, trade_name, cnpj FROM tenants WHERE id = ?', [TenantContext::id()]) ?? [];
        $html = '<p>A empresa <strong>' . e($tenant['trade_name'] ?: $tenant['legal_name']) . '</strong> (CNPJ ' . e(fmt_cnpj($tenant['cnpj'] ?? '')) . ') pediu o plano <strong>'
            . e($plan['name']) . '</strong>, ciclo ' . e($cycle) . '.</p>'
            . ($message ? '<p>Mensagem: ' . nl2br(e($message)) . '</p>' : '')
            . '<p><a href="' . e(absolute_url('/plataforma/empresas/' . TenantContext::id())) . '">Abrir no painel da plataforma</a></p>';
        foreach (self::platformRecipients() as $email) {
            Mailer::send($email, 'ÍSIS · Pedido de plano: ' . ($tenant['trade_name'] ?: $tenant['legal_name']), $html);
        }
    }

    /**
     * Lança uma cobrança (fatura) para a empresa. Retorna o id da fatura.
     *
     * @param array{amount_cents: int, due_date: string, description?: ?string, notes?: ?string} $data
     */
    public static function createInvoice(int $tenantId, array $data, string $actorName): int
    {
        $subscription = self::forTenant($tenantId);
        [$start, $end] = self::nextPeriod($subscription);
        $repo = new SubscriptionRepository();
        $id = $repo->createInvoice([
            'tenant_id' => $tenantId,
            'subscription_id' => (int) $subscription['id'],
            'description' => $data['description'] ?: sprintf('ÍSIS %s · %s (%s a %s)', $subscription['plan_name'], self::CYCLES[$subscription['billing_cycle']], fmt_date($start), fmt_date($end)),
            'period_start' => $start,
            'period_end' => $end,
            'amount_cents' => $data['amount_cents'],
            'due_date' => $data['due_date'],
            'notes' => $data['notes'] ?? null,
        ]);
        AuditLogger::log('invoice.created', 'invoice', $id, null, [
            'valor' => money($data['amount_cents']), 'vencimento' => $data['due_date'], 'periodo' => "{$start} a {$end}",
        ], self::platformActor($tenantId, $actorName));
        return $id;
    }

    /**
     * Registra o pagamento de uma fatura: marca como paga e estende o período pago da assinatura.
     *
     * @param array{paid_at: string, payment_method: string, payment_reference?: ?string} $data
     */
    public static function markPaid(int $tenantId, int $invoiceId, array $data, string $actorName): void
    {
        Database::transaction(function () use ($tenantId, $invoiceId, $data, $actorName): void {
            $repo = new SubscriptionRepository();
            $invoice = $repo->invoice($tenantId, $invoiceId);
            if ($invoice === null || $invoice['status'] !== 'aberta') {
                throw new \DomainException('Fatura não encontrada ou já encerrada.');
            }
            $subscription = self::forTenant($tenantId);
            $repo->updateInvoice($invoiceId, [
                'status' => 'paga',
                'paid_at' => $data['paid_at'],
                'payment_method' => $data['payment_method'],
                'payment_reference' => $data['payment_reference'] ?? null,
            ]);
            // Estende até o fim do período da fatura (ou mais, se já pago além dele).
            $end = max((string) $invoice['period_end'], (string) ($subscription['current_period_end'] ?? ''));
            $changes = [
                'status' => 'ativa',
                'current_period_start' => $subscription['status'] === 'ativa' && $subscription['current_period_start'] !== null
                    ? $subscription['current_period_start'] : $invoice['period_start'],
                'current_period_end' => $end,
            ];
            $repo->update((int) $subscription['id'], $changes);
            self::forget($tenantId);
            AuditLogger::log('invoice.paid', 'invoice', $invoiceId, null, [
                'fatura' => $invoice['number'], 'valor' => money((int) $invoice['amount_cents']),
                'forma' => self::PAYMENT_METHODS[$data['payment_method']] ?? $data['payment_method'], 'pago_ate' => $end,
            ], self::platformActor($tenantId, $actorName));
        });
    }

    public static function cancelInvoice(int $tenantId, int $invoiceId, string $actorName): void
    {
        $repo = new SubscriptionRepository();
        $invoice = $repo->invoice($tenantId, $invoiceId);
        if ($invoice === null || $invoice['status'] !== 'aberta') {
            throw new \DomainException('Fatura não encontrada ou já encerrada.');
        }
        $repo->updateInvoice($invoiceId, ['status' => 'cancelada']);
        AuditLogger::log('invoice.canceled', 'invoice', $invoiceId, null, ['fatura' => $invoice['number']], self::platformActor($tenantId, $actorName));
    }

    /**
     * Alteração da assinatura pela plataforma (plano, ciclo, situação, datas, preço combinado).
     *
     * @param array<string, mixed> $changes
     */
    public static function updateByPlatform(int $tenantId, array $changes, string $actorName): void
    {
        $subscription = self::forTenant($tenantId);
        $fields = ['plan_code', 'billing_cycle', 'status', 'trial_ends_at', 'current_period_end', 'custom_price_cents', 'notes'];
        $before = array_intersect_key($subscription, array_flip($fields));
        $changes = array_intersect_key($changes, array_flip($fields));
        if (($changes['plan_code'] ?? null) === $subscription['requested_plan_code']) {
            $changes += ['requested_plan_code' => null, 'requested_cycle' => null, 'requested_message' => null, 'requested_at' => null];
        }
        (new SubscriptionRepository())->update((int) $subscription['id'], $changes);
        self::forget($tenantId);
        AuditLogger::log('subscription.updated', 'subscription', (int) $subscription['id'], $before, array_intersect_key($changes, array_flip($fields)), self::platformActor($tenantId, $actorName));
    }

    /**
     * Próximo período a cobrar: continua do fim do período pago ou começa hoje
     * (teste, assinatura nova ou vencida há mais tempo que a tolerância).
     *
     * @return array{0: string, 1: string}
     */
    public static function nextPeriod(array $subscription, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $end = $subscription['current_period_end'];
        $grace = (int) Config::get('billing.grace_days', 7);
        $start = $subscription['status'] === 'ativa' && $end !== null && self::addDays((string) $end, $grace) >= $today
            ? self::addDays((string) $end, 1)
            : $today;
        $months = $subscription['billing_cycle'] === 'anual' ? 12 : 1;
        $finish = (new \DateTimeImmutable($start))->modify("+{$months} months")->modify('-1 day')->format('Y-m-d');
        return [$start, $finish];
    }

    /**
     * Avisos por e-mail aos administradores: teste terminando (7, 3 e 1 dia), teste encerrado,
     * vencimento próximo (5 dias) e pagamento em atraso. Um aviso de cada tipo por assinatura.
     *
     * @return int Quantidade de e-mails enviados
     */
    public static function sendNotices(?string $today = null): int
    {
        $today ??= date('Y-m-d');
        $sent = 0;
        $repo = new SubscriptionRepository();
        foreach ($repo->allSubscriptions() as $subscription) {
            $state = self::state($subscription, $today);
            $days = $state['days_left'];
            $notice = match (true) {
                $state['status'] === 'teste' && in_array($days, [7, 3, 1], true) => "teste-{$days}",
                $state['status'] === 'teste_encerrado' => 'teste-fim',
                $state['status'] === 'ativa' && $days === 5 => 'vence-5',
                $state['status'] === 'em_atraso' => 'atraso',
                $state['status'] === 'vencida' => 'vencida',
                default => null,
            };
            if ($notice === null) {
                continue;
            }
            $key = $notice . ':' . $state['ends_at'];
            if ($subscription['last_notice'] === $key) {
                continue;
            }
            $tenantId = (int) $subscription['tenant_id'];
            $tenant = Database::fetch("SELECT legal_name, trade_name, status FROM tenants WHERE id = ?", [$tenantId]);
            if ($tenant === null || $tenant['status'] !== 'active') {
                continue;
            }
            $admins = Database::fetchAll("SELECT name, email FROM users WHERE tenant_id = ? AND role = 'admin' AND status = 'active' AND email_verified_at IS NOT NULL", [$tenantId]);
            foreach ($admins as $admin) {
                Mailer::sendTemplate($admin['email'], self::noticeSubject($notice, $days), 'billing-notice', [
                    'name' => $admin['name'],
                    'company' => $tenant['trade_name'] ?: $tenant['legal_name'],
                    'notice' => $notice,
                    'days' => $days,
                    'endsAt' => $state['ends_at'],
                    'plan' => $subscription['plan_name'],
                    'link' => absolute_url('/plano'),
                ]);
                $sent++;
            }
            $repo->update((int) $subscription['id'], ['last_notice' => $key]);
        }
        self::forget();
        return $sent;
    }

    private static function noticeSubject(string $notice, ?int $days): string
    {
        return match (true) {
            str_starts_with($notice, 'teste-') && $notice !== 'teste-fim' => 'Seu teste do ÍSIS termina em ' . $days . ($days === 1 ? ' dia' : ' dias'),
            $notice === 'teste-fim' => 'Seu teste do ÍSIS terminou',
            $notice === 'vence-5' => 'Sua assinatura do ÍSIS vence em 5 dias',
            $notice === 'atraso' => 'Pagamento do ÍSIS em atraso',
            default => 'Assinatura do ÍSIS vencida: acesso em modo somente leitura',
        };
    }

    /** @return list<string> */
    public static function platformRecipients(): array
    {
        $platform = new PlatformRepository();
        $emails = array_column($platform->activeAdmins(), 'email');
        $billing = $platform->setting('billing_email');
        if ($billing !== null) {
            $emails[] = $billing;
        }
        return array_values(array_unique($emails));
    }

    /** @return array{tenant_id: int, user_id: null, user_name: string} */
    private static function platformActor(int $tenantId, string $actorName): array
    {
        return ['tenant_id' => $tenantId, 'user_id' => null, 'user_name' => 'AiCode · ' . $actorName];
    }

    public static function addDays(string $date, int $days): string
    {
        return (new \DateTimeImmutable($date))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
    }

    private static function daysBetween(string $from, string $to): int
    {
        return (int) (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->format('%r%a');
    }
}
