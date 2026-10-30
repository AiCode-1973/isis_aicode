<?php

use Isis\Services\SubscriptionService;

$toneBadge = ['success' => 'badge-ok', 'info' => 'badge-brand', 'warning' => 'badge-warn', 'danger' => 'badge-danger'];
$invoiceBadge = ['aberta' => 'badge-warn', 'paga' => 'badge-ok', 'cancelada' => ''];
$amountDefault = $price !== null ? number_format($price / 100, 2, ',', '.') : '';
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/plataforma">Empresas</a></p>
        <h1><?= e($t['trade_name'] ?: $t['legal_name']) ?></h1>
        <p><?= e($t['legal_name']) ?> · CNPJ <?= e(fmt_cnpj($t['cnpj'])) ?> · <?= e($t['segment_name']) ?> · cadastrada em <?= e(fmt_date($t['created_at'])) ?></p>
    </div>
    <span class="badge <?= $toneBadge[$state['tone']] ?>"><?= e($state['label']) ?></span>
</div>

<?php if ($s['requested_at']): ?>
<div class="alert alert-warn" role="status">
    <?= icon('inbox') ?>
    <p><strong>Pedido de mudança em <?= e(fmt_datetime($s['requested_at'])) ?>:</strong> plano <?= e($s['requested_plan_name']) ?>, ciclo <?= e($s['requested_cycle']) ?>.<?= $s['requested_message'] ? ' Mensagem: “' . e($s['requested_message']) . '”' : '' ?> Ao salvar a assinatura com esse plano, o pedido é dado como atendido.</p>
</div>
<?php endif; ?>

<div class="grid grid-2 wide-split">
    <section class="card" aria-label="Situação">
        <h2>Situação atual</h2>
        <dl class="dl section-gap-sm">
            <dt>Plano</dt><dd><?= e($s['plan_name']) ?> · <?= e(SubscriptionService::CYCLES[$s['billing_cycle']]) ?></dd>
            <dt>Situação</dt><dd><?= e($state['label']) ?><?= $state['read_only'] ? ' · <span class="text-danger">somente leitura</span>' : '' ?></dd>
            <dt><?= $s['status'] === 'teste' ? 'Teste até' : 'Pago até' ?></dt><dd><?= e(fmt_date($state['ends_at'])) ?><?= $state['days_left'] !== null ? ' (' . ($state['days_left'] >= 0 ? 'faltam ' . (int) $state['days_left'] : 'há ' . abs((int) $state['days_left'])) . ' dias)' : '' ?></dd>
            <dt>Valor por período</dt><dd><?= e(money($price)) ?><?= $s['custom_price_cents'] !== null ? ' <span class="muted small">(preço combinado)</span>' : '' ?></dd>
            <dt>Próximo período</dt><dd><?= e(fmt_date($nextPeriod[0])) ?> a <?= e(fmt_date($nextPeriod[1])) ?></dd>
            <?php if ($s['notes']): ?><dt>Observações</dt><dd><?= e($s['notes']) ?></dd><?php endif; ?>
        </dl>
        <p class="section-title">Uso dos limites</p>
        <ul class="bar-list">
            <?php foreach ($usage as $u): $pct = $u['limit'] ? min(100, $u['used'] / max(1, $u['limit']) * 100) : 0; ?>
            <li>
                <div class="progress-label"><span><?= e(ucfirst($u['label'])) ?></span><strong><?= (int) $u['used'] ?><?= $u['limit'] !== null ? ' de ' . (int) $u['limit'] : ' · sem limite' ?></strong></div>
                <?php if ($u['limit'] !== null): ?><div class="progress"><span class="<?= width_class($pct) ?>"></span></div><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <p class="section-title">Administradores</p>
        <ul class="stack">
            <?php foreach ($admins as $a): ?>
            <li><?= e($a['name']) ?> · <a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a> <span class="muted small">· último acesso <?= e(fmt_datetime($a['last_login_at'])) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <form method="post" action="/plataforma/empresas/<?= (int) $t['id'] ?>/assinatura" class="card" id="assinatura" novalidate>
        <?= csrf_field() ?>
        <h2>Alterar assinatura</h2>
        <div class="form-grid section-gap-sm">
            <div class="field">
                <label for="f-plan_code">Plano</label>
                <select id="f-plan_code" name="plan_code"<?= error_attrs('plan_code') ?>>
                    <?php foreach ($plans as $code => $name): ?><option value="<?= e($code) ?>"<?= old('plan_code', $s['plan_code']) === $code ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
                </select>
                <?= field_error('plan_code') ?>
            </div>
            <div class="field">
                <label for="f-billing_cycle">Ciclo</label>
                <select id="f-billing_cycle" name="billing_cycle">
                    <?php foreach (SubscriptionService::CYCLES as $k => $v): ?><option value="<?= e($k) ?>"<?= old('billing_cycle', $s['billing_cycle']) === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-status">Situação</label>
                <select id="f-status" name="status"<?= error_attrs('status') ?>>
                    <?php foreach (SubscriptionService::STORED_STATUSES as $k => $v): ?><option value="<?= e($k) ?>"<?= old('status', $s['status']) === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select>
                <p class="hint">Atraso e vencimento são calculados pelas datas. “Suspensa” e “Cancelada” deixam a empresa em somente leitura.</p>
            </div>
            <div class="field">
                <label for="f-custom_price">Preço combinado (R$)</label>
                <input type="text" id="f-custom_price" name="custom_price" inputmode="decimal" value="<?= e(old('custom_price', $s['custom_price_cents'] !== null ? number_format($s['custom_price_cents'] / 100, 2, ',', '.') : '')) ?>" placeholder="Tabela do plano"<?= error_attrs('custom_price') ?>>
                <?= field_error('custom_price') ?>
            </div>
            <div class="field">
                <label for="f-trial_ends_at">Teste até</label>
                <input type="date" id="f-trial_ends_at" name="trial_ends_at" value="<?= e(old('trial_ends_at', (string) $s['trial_ends_at'])) ?>"<?= error_attrs('trial_ends_at') ?>>
                <?= field_error('trial_ends_at') ?>
            </div>
            <div class="field">
                <label for="f-current_period_end">Pago até</label>
                <input type="date" id="f-current_period_end" name="current_period_end" value="<?= e(old('current_period_end', (string) $s['current_period_end'])) ?>"<?= error_attrs('current_period_end') ?>>
                <p class="hint">Normalmente é preenchido ao registrar um pagamento.</p>
            </div>
            <div class="field full">
                <label for="f-notes">Observações internas</label>
                <textarea id="f-notes" name="notes" rows="2" maxlength="500"><?= e(old('notes', (string) $s['notes'])) ?></textarea>
            </div>
        </div>
        <div class="actions section-gap-sm"><button type="submit" class="btn btn-primary">Salvar assinatura</button></div>
    </form>
</div>

<form method="post" action="/plataforma/empresas/<?= (int) $t['id'] ?>/faturas" class="card section-gap" id="cobranca" novalidate>
    <?= csrf_field() ?>
    <h2>Lançar cobrança</h2>
    <p class="muted">A fatura cobre o próximo período (<?= e(fmt_date($nextPeriod[0])) ?> a <?= e(fmt_date($nextPeriod[1])) ?>). Ao registrar o pagamento, a assinatura fica ativa até o fim desse período.</p>
    <div class="form-grid form-grid-3 section-gap-sm">
        <div class="field">
            <label for="f-amount">Valor (R$)</label>
            <input type="text" id="f-amount" name="amount" inputmode="decimal" value="<?= e(old('amount', $amountDefault)) ?>" required<?= error_attrs('amount') ?>>
            <?= field_error('amount') ?>
        </div>
        <div class="field">
            <label for="f-due_date">Vencimento</label>
            <input type="date" id="f-due_date" name="due_date" value="<?= e(old('due_date', max(date('Y-m-d'), $nextPeriod[0]))) ?>" required<?= error_attrs('due_date') ?>>
            <?= field_error('due_date') ?>
        </div>
        <div class="field">
            <label for="f-description">Descrição</label>
            <input type="text" id="f-description" name="description" value="<?= e(old('description')) ?>" maxlength="200" placeholder="Automática: plano, ciclo e período">
        </div>
        <div class="field">
            <span class="label">Pagamento</span>
            <label class="check"><input type="checkbox" name="paid" value="1"<?= old('paid') === '1' ? ' checked' : '' ?>> <span>Já foi pago (registrar agora)</span></label>
        </div>
        <div class="field">
            <label for="f-payment_method">Forma de pagamento</label>
            <select id="f-payment_method" name="payment_method"<?= error_attrs('payment_method') ?>>
                <option value="">—</option>
                <?php foreach (SubscriptionService::PAYMENT_METHODS as $k => $v): ?><option value="<?= e($k) ?>"<?= old('payment_method') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
            </select>
            <?= field_error('payment_method') ?>
        </div>
        <div class="field">
            <label for="f-payment_reference">Referência (comprovante, ID do Pix)</label>
            <input type="text" id="f-payment_reference" name="payment_reference" value="<?= e(old('payment_reference')) ?>" maxlength="120">
        </div>
    </div>
    <div class="actions section-gap-sm"><button type="submit" class="btn btn-primary"><?= icon('plus') ?> Lançar cobrança</button></div>
</form>

<section class="card section-gap" id="faturas" aria-label="Faturas">
    <h2>Faturas</h2>
    <?php if (!$invoices): ?>
    <p class="empty">Nenhuma cobrança lançada para esta empresa.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table-stack">
            <thead><tr><th>Número</th><th>Descrição</th><th>Valor</th><th>Vencimento</th><th>Situação</th><th>Registrar pagamento</th></tr></thead>
            <tbody>
            <?php foreach ($invoices as $i): $late = $i['status'] === 'aberta' && $i['due_date'] < date('Y-m-d'); ?>
            <tr class="<?= $late ? 'row-overdue' : '' ?>">
                <td data-label="Número"><span class="code"><?= e($i['number']) ?></span></td>
                <td data-label="Descrição"><?= e($i['description']) ?><?= $i['notes'] ? '<div class="small muted">' . e($i['notes']) . '</div>' : '' ?></td>
                <td data-label="Valor"><?= e(money((int) $i['amount_cents'])) ?></td>
                <td data-label="Vencimento" class="<?= $late ? 'text-danger' : '' ?>"><?= e(fmt_date($i['due_date'])) ?></td>
                <td data-label="Situação">
                    <span class="badge <?= $invoiceBadge[$i['status']] ?>"><?= e($i['status']) ?></span>
                    <?php if ($i['status'] === 'paga'): ?><div class="small muted"><?= e(fmt_date($i['paid_at'])) ?> · <?= e(SubscriptionService::PAYMENT_METHODS[$i['payment_method']] ?? (string) $i['payment_method']) ?><?= $i['payment_reference'] ? ' · ' . e($i['payment_reference']) : '' ?></div><?php endif; ?>
                </td>
                <td data-label="Registrar pagamento">
                    <?php if ($i['status'] === 'aberta'): ?>
                    <form method="post" action="/plataforma/empresas/<?= (int) $t['id'] ?>/faturas/<?= (int) $i['id'] ?>/pagamento" class="inline-form pay-form">
                        <?= csrf_field() ?>
                        <input type="date" name="paid_on" value="<?= e(date('Y-m-d')) ?>" aria-label="Data do pagamento" required>
                        <select name="payment_method" aria-label="Forma de pagamento"><?php foreach (SubscriptionService::PAYMENT_METHODS as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select>
                        <input type="text" name="payment_reference" placeholder="Referência" aria-label="Referência" maxlength="120">
                        <button type="submit" class="btn btn-sm btn-primary">Pago</button>
                    </form>
                    <form method="post" action="/plataforma/empresas/<?= (int) $t['id'] ?>/faturas/<?= (int) $i['id'] ?>/cancelar" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-link">Cancelar cobrança</button>
                    </form>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
