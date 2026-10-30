<?php

use Isis\Services\SubscriptionService;

$toneBadge = ['success' => 'badge-ok', 'info' => 'badge-brand', 'warning' => 'badge-warn', 'danger' => 'badge-danger'];
$filtersOn = $filters['q'] !== '' || $filters['situacao'] !== '';
?>
<div class="page-head">
    <div>
        <h1>Empresas e assinaturas</h1>
        <p>Planos, testes, vencimentos e cobranças manuais de cada empresa cadastrada no ÍSIS.</p>
    </div>
</div>

<div class="grid grid-4">
    <div class="card kpi"><span class="kpi-icon teal"><?= icon('check', 'icon-lg') ?></span><div><div class="kpi-value"><?= money($totals['mrr']) ?></div><div class="kpi-label">Receita mensal recorrente · <?= (int) $totals['ativa'] ?> assinatura(s) paga(s)</div></div></div>
    <a class="card kpi" href="/plataforma?situacao=teste"><span class="kpi-icon info"><?= icon('clock', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $totals['teste'] ?></div><div class="kpi-label">Em teste grátis</div></div></a>
    <a class="card kpi" href="/plataforma?situacao=em_atraso"><span class="kpi-icon warn"><?= icon('alert', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $totals['atencao'] ?></div><div class="kpi-label">Pedem atenção (atraso, vencida ou teste encerrado)</div></div></a>
    <a class="card kpi" href="/plataforma?situacao=pedido"><span class="kpi-icon"><?= icon('inbox', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $totals['pedidos'] ?></div><div class="kpi-label">Pedidos de mudança de plano</div></div></a>
</div>

<form method="get" action="/plataforma" class="card filters section-gap" aria-label="Filtros">
    <div class="field"><label for="f-q">Buscar</label><input type="search" id="f-q" name="q" value="<?= e($filters['q']) ?>" placeholder="Razão social, nome fantasia ou CNPJ"></div>
    <div class="field">
        <label for="f-situacao">Situação</label>
        <select id="f-situacao" name="situacao">
            <option value="">Todas</option>
            <?php foreach (SubscriptionService::STATUS_LABELS + ['pedido' => 'Com pedido de plano'] as $k => $v): ?>
            <option value="<?= e($k) ?>"<?= $filters['situacao'] === $k ? ' selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="actions"><button type="submit" class="btn btn-primary">Filtrar</button><?php if ($filtersOn): ?><a class="btn btn-ghost" href="/plataforma">Limpar</a><?php endif; ?></div>
</form>

<section class="card section-gap" aria-label="Empresas">
    <?php if (!$rows): ?>
    <p class="empty"><?= $filtersOn ? 'Nenhuma empresa com esses filtros.' : 'Nenhuma empresa cadastrada ainda.' ?></p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table-stack">
            <thead><tr><th>Empresa</th><th>Plano</th><th>Situação</th><th>Válido até</th><th>Valor</th><th>Usuários</th><th>Último acesso</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $state = $r['state']; ?>
            <tr>
                <td data-label="Empresa">
                    <a class="title-link" href="/plataforma/empresas/<?= (int) $r['tenant_id'] ?>"><?= e($r['trade_name'] ?: $r['legal_name']) ?></a>
                    <div class="small muted"><?= e(fmt_cnpj($r['cnpj'])) ?> · desde <?= e(fmt_date($r['tenant_created_at'])) ?><?= $r['tenant_status'] !== 'active' ? ' · <span class="text-danger">empresa desativada</span>' : '' ?></div>
                    <?php if ($r['requested_at']): ?><span class="badge badge-orange badge-sm">pediu mudança de plano</span><?php endif; ?>
                </td>
                <td data-label="Plano"><?= e($r['plan_name'] ?? '—') ?><?= $r['billing_cycle'] ? ' <span class="muted small">· ' . e($r['billing_cycle']) . '</span>' : '' ?></td>
                <td data-label="Situação"><?= $state ? '<span class="badge ' . $toneBadge[$state['tone']] . '">' . e($state['label']) . '</span>' : '<span class="badge">sem assinatura</span>' ?></td>
                <td data-label="Válido até"><?= $state ? e(fmt_date($state['ends_at'])) . ($state['days_left'] !== null && $state['days_left'] >= 0 ? ' <span class="muted small">(' . (int) $state['days_left'] . ' d)</span>' : '') : '—' ?></td>
                <td data-label="Valor"><?= $r['id'] ? e(money(SubscriptionService::price($r))) : '—' ?></td>
                <td data-label="Usuários"><?= (int) $r['active_users'] ?><?= $r['max_users'] !== null ? ' / ' . (int) $r['max_users'] : '' ?></td>
                <td data-label="Último acesso"><?= e(fmt_datetime($r['last_login_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php if ($payments): ?>
<section class="card section-gap" aria-label="Pagamentos recentes">
    <h2>Pagamentos recentes</h2>
    <div class="table-wrap">
        <table class="table-stack">
            <thead><tr><th>Fatura</th><th>Empresa</th><th>Valor</th><th>Forma</th><th>Pago em</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $p): ?>
            <tr>
                <td data-label="Fatura"><span class="code"><?= e($p['number']) ?></span></td>
                <td data-label="Empresa"><a href="/plataforma/empresas/<?= (int) $p['tenant_id'] ?>"><?= e($p['trade_name'] ?: $p['legal_name']) ?></a></td>
                <td data-label="Valor"><?= e(money((int) $p['amount_cents'])) ?></td>
                <td data-label="Forma"><?= e(SubscriptionService::PAYMENT_METHODS[$p['payment_method']] ?? (string) $p['payment_method']) ?></td>
                <td data-label="Pago em"><?= e(fmt_date($p['paid_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
