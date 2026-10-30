<?php

use Isis\Auth\Permission as P;
use Isis\Support\Labels;

$statusBadge = ['rascunho' => 'badge', 'em_aprovacao' => 'badge-warn', 'aprovada' => 'badge-ok', 'rejeitada' => 'badge-danger', 'substituida' => 'badge'];
?>
<div class="page-head">
    <div>
        <h1>Políticas e documentos</h1>
        <p><?= $editor ? 'Políticas internas, avisos e procedimentos, com versões, aprovação, validade e ciência dos colaboradores.' : 'Políticas vigentes da empresa. Leia e registre sua ciência quando solicitado.' ?></p>
    </div>
    <?php if (can(P::POLICIES_EDIT)): ?>
    <div class="actions">
        <form method="post" action="/politicas/modelos" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-ghost"><?= icon('download') ?> Usar modelos do ÍSIS</button></form>
        <a class="btn btn-primary" href="/politicas/nova"><?= icon('plus') ?> Nova política</a>
    </div>
    <?php endif; ?>
</div>

<?php if ($stats !== null): $ackPct = $stats['ack_expected'] > 0 ? (int) round($stats['ack_done'] / $stats['ack_expected'] * 100) : null; ?>
<div class="grid grid-4">
    <div class="card kpi"><span class="kpi-icon teal"><?= icon('file', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['published'] ?><span class="kpi-sub">/<?= (int) $stats['total'] ?></span></div><div class="kpi-label">Vigentes</div></div></div>
    <div class="card kpi"><span class="kpi-icon warn"><?= icon('clock', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['awaiting'] ?></div><div class="kpi-label">Aguardando aprovação</div></div></div>
    <div class="card kpi"><span class="kpi-icon"><?= icon('alert', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['expired'] ?></div><div class="kpi-label">Vencidas · <?= (int) $stats['expiring'] ?> vencendo em 30 dias</div></div></div>
    <div class="card kpi"><span class="kpi-icon info"><?= icon('users', 'icon-lg') ?></span><div><div class="kpi-value"><?= $ackPct === null ? '—' : $ackPct . '%' ?></div><div class="kpi-label">Ciência dos colaboradores</div></div></div>
</div>
<?php endif; ?>

<section class="card section-gap" aria-label="Políticas">
    <?php if (!$policies): ?>
    <p class="empty"><?= $editor ? 'Nenhuma política cadastrada. Comece pelos modelos do ÍSIS e adapte à sua empresa.' : 'Nenhuma política vigente no momento.' ?></p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table-stack">
            <thead><tr><th>Código</th><th>Documento</th><th>Versão vigente</th><th>Validade</th><?= $editor ? '<th>Situação</th><th>Ciência</th>' : '<th>Sua ciência</th>' ?></tr></thead>
            <tbody>
            <?php foreach ($policies as $p): $expired = $p['valid_until'] && $p['valid_until'] < date('Y-m-d'); ?>
            <tr>
                <td data-label="Código"><span class="code"><?= e(code('POL', (int) $p['seq'])) ?></span></td>
                <td data-label="Documento"><a class="title-link" href="/politicas/<?= (int) $p['id'] ?>"><?= e($p['title']) ?></a><div class="small muted"><?= e(label(Labels::POLICY_CATEGORY, $p['category'])) ?></div></td>
                <td data-label="Versão vigente"><?= $p['current_number'] ? 'v' . (int) $p['current_number'] . ' · ' . e(fmt_date($p['approved_at'])) : '—' ?></td>
                <td data-label="Validade" class="<?= $expired ? 'text-danger' : '' ?>"><?= e(fmt_date($p['valid_until'])) ?><?= $expired ? ' · vencida' : '' ?></td>
                <?php if ($editor): ?>
                <td data-label="Situação"><?= $p['latest_status'] && $p['latest_status'] !== 'aprovada' ? '<span class="badge ' . $statusBadge[$p['latest_status']] . '">v' . (int) $p['latest_number'] . ' ' . e(mb_strtolower(Labels::POLICY_VERSION_STATUS[$p['latest_status']])) . '</span>' : '<span class="badge badge-ok">vigente</span>' ?></td>
                <td data-label="Ciência"><?= $p['requires_ack'] && $p['current_version_id'] ? (int) $p['ack_count'] . '/' . (int) $p['active_users'] : '<span class="muted">—</span>' ?></td>
                <?php else: ?>
                <td data-label="Sua ciência"><?= !$p['requires_ack'] ? '<span class="muted">não exigida</span>' : ($p['acked_by_me'] ? '<span class="badge badge-ok">registrada</span>' : '<span class="badge badge-warn">pendente</span>') ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
