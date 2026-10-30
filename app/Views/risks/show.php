<?php

use Isis\Auth\Permission as P;
use Isis\Services\RiskMatrix;
use Isis\Support\Labels;

$text = static fn (?string $v): string => $v !== null && trim($v) !== '' ? e($v) : '<span class="muted">—</span>';
$level = RiskMatrix::levelOf((int) $r['likelihood'], (int) $r['impact']);
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/riscos">Riscos</a> · <?= e(code('RSK', (int) $r['seq'])) ?></p>
        <h1><?= e($r['title']) ?></h1>
        <p><?= risk_badge((int) $r['likelihood'], (int) $r['impact']) ?> <span class="badge"><?= e(label(Labels::RISK_STATUS, $r['status'])) ?></span> · <?= e(label(Labels::RISK_CATEGORY, $r['category'])) ?></p>
    </div>
    <div class="actions">
        <?php if (can(P::ACTIONS_EDIT)): ?><a class="btn btn-ghost" href="/planos/nova?risco=<?= (int) $r['id'] ?>"><?= icon('plus') ?> Nova ação</a><?php endif; ?>
        <?php if ($canEdit): ?><a class="btn btn-primary" href="/riscos/<?= (int) $r['id'] ?>/editar">Editar</a><?php endif; ?>
    </div>
</div>

<div class="grid grid-main">
    <div class="stack">
        <section class="card">
            <h2>Descrição</h2>
            <div class="detail-grid section-gap-sm">
                <div class="detail full"><span class="label">Descrição</span><div class="value"><?= $text($r['description']) ?></div></div>
                <div class="detail"><span class="label">Causa / vulnerabilidade</span><div class="value"><?= $text($r['cause']) ?></div></div>
                <div class="detail"><span class="label">Consequência</span><div class="value"><?= $text($r['consequence']) ?></div></div>
                <div class="detail"><span class="label">Operação</span><div class="value"><?= $r['activity_id'] ? '<a href="/inventario/' . (int) $r['activity_id'] . '">' . e(code('OP', (int) $r['activity_seq']) . ' · ' . $r['activity_name']) . '</a>' : 'Risco geral' ?></div></div>
                <div class="detail"><span class="label">Setor</span><div class="value"><?= e($r['department_name'] ?? '—') ?></div></div>
            </div>
        </section>

        <section class="card">
            <h2>Tratamento</h2>
            <div class="detail-grid section-gap-sm">
                <div class="detail"><span class="label">Estratégia</span><div class="value"><?= e(label(Labels::RISK_TREATMENT, $r['treatment'])) ?></div></div>
                <div class="detail"><span class="label">Responsável</span><div class="value"><?= e($r['owner_name'] ?? '—') ?></div></div>
                <div class="detail full"><span class="label">Medidas e justificativa</span><div class="value"><?= $text($r['treatment_notes']) ?></div></div>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <div><h2>Plano de ação</h2><p><?= (int) $r['actions_done'] ?> de <?= (int) $r['actions_count'] ?> ação(ões) concluída(s).</p></div>
                <?php if (can(P::ACTIONS_EDIT)): ?><a class="btn btn-ghost btn-sm" href="/planos/nova?risco=<?= (int) $r['id'] ?>"><?= icon('plus') ?> Nova ação</a><?php endif; ?>
            </div>
            <?php if (!$actions): ?>
            <p class="empty"><?= $r['treatment'] === 'mitigar' ? 'Nenhuma ação ainda. Para mitigar o risco, crie as medidas com responsável e prazo.' : 'Nenhuma ação vinculada.' ?></p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table-stack">
                    <thead><tr><th>Ação</th><th>Responsável</th><th>Prazo</th><th>Situação</th></tr></thead>
                    <tbody>
                    <?php foreach ($actions as $x): ?>
                    <tr class="<?= $x['overdue'] ? 'row-overdue' : '' ?>">
                        <td data-label="Ação"><span class="code"><?= e(code('ACT', (int) $x['seq'])) ?></span> <a class="title-link" href="/planos/<?= (int) $x['id'] ?>"><?= e($x['title']) ?></a></td>
                        <td data-label="Responsável"><?= e($x['owner_name'] ?? '—') ?></td>
                        <td data-label="Prazo" class="<?= $x['overdue'] ? 'text-danger' : '' ?>"><?= e(fmt_date($x['due_date'])) ?></td>
                        <td data-label="Situação"><span class="badge"><?= e(label(Labels::ACTION_STATUS, $x['status'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <?= Isis\Core\View::partial('partials/evidences', ['entityType' => 'risk', 'entityId' => (int) $r['id'], 'evidences' => $evidences, 'canUpload' => $canEdit]) ?>
    </div>

    <div class="stack">
        <section class="card">
            <h2>Avaliação</h2>
            <dl class="dl section-gap-sm">
                <dt>Probabilidade</dt><dd><?= (int) $r['likelihood'] ?> · <?= e(Labels::LIKELIHOOD[(int) $r['likelihood']]) ?></dd>
                <dt>Impacto</dt><dd><?= (int) $r['impact'] ?> · <?= e(Labels::IMPACT[(int) $r['impact']]) ?></dd>
                <dt>Nível inerente</dt><dd><?= risk_badge((int) $r['likelihood'], (int) $r['impact']) ?></dd>
                <dt>Nível residual</dt><dd><?= $r['residual_likelihood'] ? risk_badge((int) $r['residual_likelihood'], (int) $r['residual_impact']) : '<span class="muted">não avaliado</span>' ?></dd>
                <dt>Próxima revisão</dt><dd class="<?= $r['review_date'] && $r['review_date'] < date('Y-m-d') ? 'text-danger' : '' ?>"><?= e(fmt_date($r['review_date'])) ?></dd>
            </dl>
            <?php if (in_array($level, ['alto', 'critico'], true) && $r['treatment'] === 'aceitar'): ?>
            <div class="alert alert-warn section-gap"><?= icon('alert') ?><p>Risco <?= e(RiskMatrix::label($level)) ?> aceito: confirme que a decisão foi aprovada pela direção e registrada.</p></div>
            <?php endif; ?>
        </section>

        <?php if ($canEdit): ?>
        <form method="post" action="/riscos/<?= (int) $r['id'] ?>/excluir" data-confirm="Excluir este risco? As ações vinculadas serão mantidas, sem o vínculo.">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-block"><?= icon('trash') ?> Excluir risco</button>
        </form>
        <?php endif; ?>
    </div>
</div>
