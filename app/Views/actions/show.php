<?php

use Isis\Support\Labels;

$statusBadge = ['pendente' => 'badge', 'em_andamento' => 'badge-brand', 'concluida' => 'badge-ok', 'cancelada' => 'badge'];
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/planos">Planos de ação</a> · <?= e(code('ACT', (int) $x['seq'])) ?></p>
        <h1><?= e($x['title']) ?></h1>
        <p>
            <span class="badge <?= $statusBadge[$x['status']] ?>"><?= e(label(Labels::ACTION_STATUS, $x['status'])) ?></span>
            <span class="badge">Prioridade <?= e(mb_strtolower(label(Labels::PRIORITY, $x['priority']))) ?></span>
            <?php if ($x['overdue']): ?><span class="badge badge-danger">vencida</span><?php endif; ?>
        </p>
    </div>
    <?php if ($canEdit): ?><a class="btn btn-primary" href="/planos/<?= (int) $x['id'] ?>/editar">Editar</a><?php endif; ?>
</div>

<div class="grid grid-main">
    <div class="stack">
        <section class="card">
            <h2>Detalhes</h2>
            <div class="detail-grid section-gap-sm">
                <div class="detail full"><span class="label">Descrição e critério de conclusão</span><div class="value"><?= $x['description'] ? e($x['description']) : '<span class="muted">—</span>' ?></div></div>
<?php
$riskText = $x['risk_id'] ? e(code('RSK', (int) $x['risk_seq']) . ' · ' . $x['risk_title']) : null;
$activityText = $x['activity_id'] ? e(code('OP', (int) $x['activity_seq']) . ' · ' . $x['activity_name']) : null;
?>
                <div class="detail"><span class="label">Risco tratado</span><div class="value"><?= $riskText === null ? '—' : (can(Isis\Auth\Permission::RISKS_VIEW) ? '<a href="/riscos/' . (int) $x['risk_id'] . '">' . $riskText . '</a>' : $riskText) . ' ' . risk_badge((int) $x['risk_likelihood'], (int) $x['risk_impact']) ?></div></div>
                <?php if ($x['incident_id']): ?>
                <div class="detail"><span class="label">Incidente</span><div class="value"><?= can(Isis\Auth\Permission::INCIDENTS_VIEW) ? '<a href="/incidentes/' . (int) $x['incident_id'] . '">' . e(code('INC', (int) $x['incident_seq']) . ' · ' . $x['incident_title']) . '</a>' : e(code('INC', (int) $x['incident_seq']) . ' · ' . $x['incident_title']) ?></div></div>
                <?php endif; ?>
                <div class="detail"><span class="label">Operação</span><div class="value"><?= $activityText === null ? '—' : (can(Isis\Auth\Permission::INVENTORY_VIEW) ? '<a href="/inventario/' . (int) $x['activity_id'] . '">' . $activityText . '</a>' : $activityText) ?></div></div>
            </div>
        </section>

        <?php if ($canProgress): ?>
        <section class="card" id="andamento">
            <h2>Registrar andamento</h2>
            <form method="post" action="/planos/<?= (int) $x['id'] ?>/andamento" class="form-grid section-gap-sm" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="f-status">Situação</label>
                    <select id="f-status" name="status">
                        <?php foreach (Labels::ACTION_STATUS as $k => $v): ?><option value="<?= e($k) ?>"<?= old('status', $x['status']) === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="field full">
                    <label for="f-note">Observação</label>
                    <textarea id="f-note" name="note" maxlength="3000" placeholder="O que foi feito, dificuldades, próximos passos."<?= error_attrs('note') ?>><?= e(old('note')) ?></textarea>
                    <?= field_error('note') ?>
                </div>
                <div class="full"><button type="submit" class="btn btn-primary" data-loading="Salvando...">Registrar</button></div>
            </form>
        </section>
        <?php endif; ?>

        <?= Isis\Core\View::partial('partials/evidences', ['entityType' => 'action', 'entityId' => (int) $x['id'], 'evidences' => $evidences, 'canUpload' => $canProgress]) ?>

        <section class="card">
            <h2>Histórico</h2>
            <?php if (!$history): ?><p class="empty">Sem registros.</p><?php else: ?>
            <ul class="timeline section-gap-sm">
                <?php foreach ($history as $h): ?>
                <li>
                    <div class="meta"><?= e(fmt_datetime($h['created_at'])) ?> · <?= e($h['user_name'] ?? 'Sistema') ?></div>
                    <?php if ($h['status_from'] !== $h['status_to']): ?>
                    <div><?= $h['status_from'] ? e(label(Labels::ACTION_STATUS, $h['status_from'])) . ' → ' : '' ?><strong><?= e(label(Labels::ACTION_STATUS, $h['status_to'])) ?></strong></div>
                    <?php endif; ?>
                    <?php if ($h['note']): ?><div class="note"><?= e($h['note']) ?></div><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    </div>

    <div class="stack">
        <section class="card">
            <h2>Responsabilidade e prazo</h2>
            <dl class="dl section-gap-sm">
                <dt>Responsável</dt><dd><?= e($x['owner_name'] ?? 'Sem responsável') ?></dd>
                <dt>Setor</dt><dd><?= e($x['department_name'] ?? '—') ?></dd>
                <dt>Prazo</dt><dd class="<?= $x['overdue'] ? 'text-danger' : '' ?>"><?= e(fmt_date($x['due_date'])) ?></dd>
                <dt>Concluída em</dt><dd><?= e(fmt_datetime($x['completed_at'])) ?></dd>
                <dt>Criada em</dt><dd><?= e(fmt_datetime($x['created_at'])) ?></dd>
            </dl>
        </section>
        <?php if ($canEdit): ?>
        <form method="post" action="/planos/<?= (int) $x['id'] ?>/excluir" data-confirm="Excluir esta ação, seu histórico e suas evidências?">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-block"><?= icon('trash') ?> Excluir ação</button>
        </form>
        <?php endif; ?>
    </div>
</div>
