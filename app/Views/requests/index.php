<?php

use Isis\Auth\Permission as P;
use Isis\Support\Deadline;
use Isis\Support\Labels;

$filtered = $filters['status'] || $filters['right_type'] || $filters['overdue'] || $filters['open'] || $filters['email'];
$statusBadge = ['recebida' => 'badge-warn', 'em_verificacao' => 'badge-brand', 'em_atendimento' => 'badge-brand', 'respondida' => 'badge-ok', 'indeferida' => 'badge', 'cancelada' => 'badge'];
$publicLink = absolute_url('/titular/' . $tenant['slug']);
$onTime = $stats['answered'] > 0 ? (int) round($stats['answered_on_time'] / $stats['answered'] * 100) : null;
?>
<div class="page-head">
    <div>
        <h1>Solicitações de titulares</h1>
        <p>Pedidos de confirmação, acesso, correção, eliminação, portabilidade e demais direitos (art. 18). Prazo de referência: <?= Deadline::REQUEST_DAYS ?> dias.</p>
    </div>
    <?php if (can(P::REQUESTS_MANAGE)): ?>
    <a class="btn btn-primary" href="/titulares/nova"><?= icon('plus') ?> Registrar solicitação</a>
    <?php endif; ?>
</div>

<section class="card public-channel" aria-labelledby="pc-title">
    <div>
        <h2 id="pc-title"><?= icon('inbox') ?> Canal on-line do titular</h2>
        <p class="small muted">Divulgue este endereço no aviso de privacidade e no site. Os pedidos enviados por ele entram aqui automaticamente, com protocolo.</p>
    </div>
    <div class="actions">
        <input type="text" class="mono grow" value="<?= e($publicLink) ?>" readonly aria-label="Endereço do canal do titular" data-select-on-focus>
        <a class="btn btn-ghost" href="<?= e('/titular/' . $tenant['slug']) ?>" target="_blank" rel="noopener">Abrir</a>
    </div>
</section>

<div class="grid grid-4 section-gap">
    <a class="card kpi" href="/titulares?abertas=1"><span class="kpi-icon"><?= icon('inbox', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['open'] ?></div><div class="kpi-label">Em aberto · <?= (int) $stats['due_soon'] ?> vencendo em 3 dias</div></div></a>
    <a class="card kpi" href="/titulares?vencidas=1"><span class="kpi-icon warn"><?= icon('alert', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['overdue'] ?></div><div class="kpi-label">Fora do prazo</div></div></a>
    <div class="card kpi"><span class="kpi-icon teal"><?= icon('check', 'icon-lg') ?></span><div><div class="kpi-value"><?= $onTime === null ? '—' : $onTime . '%' ?></div><div class="kpi-label">Respondidas no prazo</div></div></div>
    <div class="card kpi"><span class="kpi-icon info"><?= icon('clock', 'icon-lg') ?></span><div><div class="kpi-value"><?= $stats['avg_days'] === null ? '—' : e(number_format($stats['avg_days'], 1, ',', '')) ?></div><div class="kpi-label">Dias, em média, para responder</div></div></div>
</div>

<form method="get" action="/titulares" class="card filters section-gap" aria-label="Filtros">
    <div class="field">
        <label for="f-status">Situação</label>
        <select id="f-status" name="status">
            <option value="">Todas</option>
            <?php foreach (Labels::REQUEST_STATUS as $k => $v): ?><option value="<?= e($k) ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="f-direito">Pedido</label>
        <select id="f-direito" name="direito">
            <option value="">Todos</option>
            <?php foreach (Labels::RIGHT_TYPE as $k => $v): ?><option value="<?= e($k) ?>"<?= $filters['right_type'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="f-email">E-mail do titular</label>
        <input type="email" id="f-email" name="email" value="<?= e($filters['email'] ?? '') ?>" placeholder="Busca exata">
    </div>
    <div class="field">
        <span class="label">Prazo</span>
        <label class="check"><input type="checkbox" name="vencidas" value="1"<?= $filters['overdue'] ? ' checked' : '' ?>> <span>Só fora do prazo</span></label>
        <label class="check"><input type="checkbox" name="abertas" value="1"<?= $filters['open'] ? ' checked' : '' ?>> <span>Só em aberto</span></label>
    </div>
    <div class="actions">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <?php if ($filtered): ?><a class="btn btn-ghost" href="/titulares">Limpar</a><?php endif; ?>
    </div>
</form>

<section class="card section-gap" aria-label="Solicitações">
    <?php if (!$requests): ?>
    <p class="empty"><?= $filtered ? 'Nenhuma solicitação com esses filtros.' : 'Nenhuma solicitação recebida ainda. Divulgue o canal on-line do titular.' ?></p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table-stack">
            <thead><tr><th>Protocolo</th><th>Titular</th><th>Pedido</th><th>Recebida</th><th>Prazo</th><th>Responsável</th><th>Situação</th></tr></thead>
            <tbody>
            <?php foreach ($requests as $r): $left = Deadline::daysLeft($r['due_date']); $open = in_array($r['status'], ['recebida', 'em_verificacao', 'em_atendimento'], true); ?>
            <tr class="<?= $r['overdue'] ? 'row-overdue' : '' ?>">
                <td data-label="Protocolo"><a class="title-link mono" href="/titulares/<?= (int) $r['id'] ?>"><?= e($r['protocol']) ?></a></td>
                <td data-label="Titular"><?= e($r['requester_name']) ?><div class="small muted"><?= e(label(Labels::REQUEST_CHANNEL, $r['channel'])) ?></div></td>
                <td data-label="Pedido"><?= e(explode(' (', label(Labels::RIGHT_TYPE, $r['right_type']))[0]) ?></td>
                <td data-label="Recebida"><?= e(fmt_date($r['received_at'])) ?></td>
                <td data-label="Prazo" class="<?= $r['overdue'] ? 'text-danger' : '' ?>">
                    <?= e(fmt_date($r['due_date'])) ?>
                    <?php if ($open && $left !== null): ?><div class="small <?= $left < 0 ? 'text-danger' : ($left <= 3 ? 'text-warn' : 'muted') ?>"><?= $left < 0 ? abs($left) . ' dia(s) de atraso' : ($left === 0 ? 'vence hoje' : 'faltam ' . $left . ' dia(s)') ?></div><?php endif; ?>
                </td>
                <td data-label="Responsável"><?= $r['assigned_name'] ? e($r['assigned_name']) : '<span class="badge badge-warn">sem responsável</span>' ?></td>
                <td data-label="Situação"><span class="badge <?= $statusBadge[$r['status']] ?? '' ?>"><?= e(label(Labels::REQUEST_STATUS, $r['status'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
