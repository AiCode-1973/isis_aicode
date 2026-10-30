<?php

use Isis\Services\SubscriptionService;

$toneAlert = ['success' => 'alert-success', 'info' => 'alert-info', 'warning' => 'alert-warn', 'danger' => 'alert-error'];
$invoiceBadge = ['aberta' => 'badge-warn', 'paga' => 'badge-ok', 'cancelada' => ''];
$days = $state['days_left'];
$message = match ($state['status']) {
    'teste' => "Teste grátis do plano {$s['plan_name']} até " . fmt_date($state['ends_at']) . " ({$days} " . ($days === 1 ? 'dia' : 'dias') . '). Escolha um plano abaixo para continuar sem interrupção.',
    'teste_encerrado' => 'O teste grátis terminou em ' . fmt_date($state['ends_at']) . '. O ÍSIS está em modo somente leitura: tudo pode ser consultado e exportado, mas inclusões e alterações ficam bloqueadas até a contratação.',
    'ativa' => "Assinatura ativa até " . fmt_date($state['ends_at']) . '.',
    'em_atraso' => 'O período pago terminou em ' . fmt_date($state['ends_at']) . '. O acesso continua normal por alguns dias; regularize o pagamento para evitar o modo somente leitura.',
    'vencida' => 'A assinatura venceu em ' . fmt_date($state['ends_at']) . ' e o ÍSIS está em modo somente leitura. Regularize o pagamento para voltar a incluir e alterar dados.',
    default => 'A assinatura está ' . mb_strtolower($state['label']) . ' e o ÍSIS está em modo somente leitura. Fale com a AiCode.',
};
?>
<div class="page-head">
    <div>
        <h1>Plano e assinatura</h1>
        <p>Plano contratado, uso dos limites, faturas e mudança de plano.</p>
    </div>
    <a class="btn btn-ghost" href="/precos"><?= icon('eye') ?> Comparar planos</a>
</div>

<div class="alert <?= $toneAlert[$state['tone']] ?>" role="status">
    <?= icon($state['read_only'] ? 'lock' : 'info') ?>
    <p><?= e($message) ?><?= $state['read_only'] ? ' Solicitações de titulares e incidentes de segurança continuam funcionando normalmente.' : '' ?></p>
</div>

<div class="grid grid-2 wide-split section-gap">
    <section class="card" aria-label="Plano atual">
        <h2>Plano atual</h2>
        <dl class="dl section-gap-sm">
            <dt>Plano</dt><dd><?= e($s['plan_name']) ?> · <?= e(SubscriptionService::CYCLES[$s['billing_cycle']]) ?></dd>
            <dt>Situação</dt><dd><?= e($state['label']) ?></dd>
            <dt><?= $s['status'] === 'teste' ? 'Teste até' : 'Válido até' ?></dt><dd><?= e(fmt_date($state['ends_at'])) ?></dd>
            <dt>Valor</dt><dd><?= e(money($price)) ?><?= $price !== null ? ' por ' . ($s['billing_cycle'] === 'anual' ? 'ano' : 'mês') : '' ?></dd>
        </dl>
        <p class="section-title">Uso dos limites do plano</p>
        <ul class="bar-list">
            <?php foreach ($usage as $key => $u): $pct = $u['limit'] ? min(100, $u['used'] / max(1, $u['limit']) * 100) : 0; ?>
            <li>
                <div class="progress-label"><span><?= e(ucfirst($u['label'])) ?><?= $key === 'users' ? ' (ativos + convites pendentes)' : '' ?></span><strong><?= (int) $u['used'] ?><?= $u['limit'] !== null ? ' de ' . (int) $u['limit'] : ' · sem limite' ?></strong></div>
                <?php if ($u['limit'] !== null): ?><div class="progress"><span class="<?= width_class($pct) ?>"></span></div><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="card" aria-label="Como pagar">
        <h2>Como pagar</h2>
        <p class="section-gap-sm"><?= nl2br(e((string) $instructions)) ?></p>
        <?php if ($billingEmail): ?><p>Dúvidas e comprovantes: <a href="mailto:<?= e($billingEmail) ?>"><?= e($billingEmail) ?></a></p><?php endif; ?>
        <p class="hint">A cobrança é lançada pela AiCode e aparece em “Faturas”. Depois que o pagamento é confirmado, a validade da assinatura é atualizada aqui.</p>
    </section>
</div>

<form method="post" action="/plano/solicitar" class="card section-gap" id="mudar" novalidate>
    <?= csrf_field() ?>
    <h2>Contratar ou mudar de plano</h2>
    <?php if ($s['requested_at']): ?>
    <p class="alert alert-info"><?= icon('clock') ?> <span>Pedido enviado em <?= e(fmt_datetime($s['requested_at'])) ?>: plano <?= e($s['requested_plan_name']) ?>, ciclo <?= e($s['requested_cycle']) ?>. A AiCode vai confirmar e lançar a cobrança.</span></p>
    <?php endif; ?>
    <div class="plan-grid section-gap-sm">
        <?php foreach ($plans as $p): ?>
        <label class="plan-option">
            <input type="radio" name="plan_code" value="<?= e($p['code']) ?>"<?= old('plan_code', $s['requested_plan_code'] ?? $s['plan_code']) === $p['code'] ? ' checked' : '' ?>>
            <span class="plan-option-body">
                <strong><?= e($p['name']) ?></strong>
                <span class="plan-price"><?= $p['contact_only'] ? 'Sob consulta' : e(money($p['price_monthly_cents'])) . '<small>/mês</small>' ?></span>
                <?php if (!$p['contact_only'] && $p['price_yearly_cents'] !== null): ?><span class="muted small">ou <?= e(money($p['price_yearly_cents'])) ?>/ano</span><?php endif; ?>
                <span class="muted small"><?= $p['max_users'] !== null ? 'Até ' . (int) $p['max_users'] . ' usuários' : 'Usuários ilimitados' ?></span>
            </span>
        </label>
        <?php endforeach; ?>
    </div>
    <?= field_error('plan_code') ?>
    <div class="form-grid section-gap-sm">
        <div class="field">
            <label for="f-billing_cycle">Ciclo de pagamento</label>
            <select id="f-billing_cycle" name="billing_cycle">
                <option value="mensal"<?= old('billing_cycle', $s['billing_cycle']) === 'mensal' ? ' selected' : '' ?>>Mensal</option>
                <option value="anual"<?= old('billing_cycle', $s['billing_cycle']) === 'anual' ? ' selected' : '' ?>>Anual (2 meses grátis)</option>
            </select>
        </div>
        <div class="field">
            <label for="f-message">Mensagem (opcional)</label>
            <input type="text" id="f-message" name="message" value="<?= e(old('message')) ?>" maxlength="500" placeholder="Ex.: preferimos boleto; precisamos de nota fiscal">
        </div>
    </div>
    <div class="actions section-gap-sm"><button type="submit" class="btn btn-primary">Enviar pedido</button></div>
</form>

<section class="card section-gap" aria-label="Faturas">
    <h2>Faturas</h2>
    <?php if (!$invoices): ?>
    <p class="empty">Nenhuma fatura até agora.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table-stack">
            <thead><tr><th>Número</th><th>Descrição</th><th>Valor</th><th>Vencimento</th><th>Situação</th></tr></thead>
            <tbody>
            <?php foreach ($invoices as $i): ?>
            <tr>
                <td data-label="Número"><span class="code"><?= e($i['number']) ?></span></td>
                <td data-label="Descrição"><?= e($i['description']) ?></td>
                <td data-label="Valor"><?= e(money((int) $i['amount_cents'])) ?></td>
                <td data-label="Vencimento"><?= e(fmt_date($i['due_date'])) ?></td>
                <td data-label="Situação"><span class="badge <?= $invoiceBadge[$i['status']] ?>"><?= e($i['status']) ?></span><?= $i['status'] === 'paga' ? ' <span class="muted small">em ' . e(fmt_date($i['paid_at'])) . '</span>' : '' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
