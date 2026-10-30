<?php

use Isis\Support\Labels;

$statusBadge = ['pendente' => 'badge-warn', 'em_andamento' => 'badge-brand', 'concluido' => 'badge-ok', 'reprovado' => 'badge-danger'];
?>
<div class="page-head">
    <div>
        <h1><?= $manager ? 'Treinamentos' : 'Meus treinamentos' ?></h1>
        <p><?= $manager ? 'Módulos de capacitação em privacidade e segurança, com avaliação, acompanhamento e certificados.' : 'Treinamentos designados a você. Leia o conteúdo, faça a avaliação e baixe seu certificado.' ?></p>
    </div>
    <?php if ($manager): ?>
    <div class="actions">
        <form method="post" action="/treinamentos/modelo" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-ghost"><?= icon('download') ?> Usar treinamento modelo de LGPD</button></form>
        <a class="btn btn-primary" href="/treinamentos/novo"><?= icon('plus') ?> Novo treinamento</a>
    </div>
    <?php endif; ?>
</div>

<?php if ($stats !== null): $pct = $stats['enrolled'] > 0 ? (int) round($stats['completed'] / $stats['enrolled'] * 100) : null; ?>
<div class="grid grid-4">
    <div class="card kpi"><span class="kpi-icon"><?= icon('graduation', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['modules'] ?></div><div class="kpi-label">Treinamentos publicados</div></div></div>
    <div class="card kpi"><span class="kpi-icon teal"><?= icon('check', 'icon-lg') ?></span><div><div class="kpi-value"><?= $pct === null ? '—' : $pct . '%' ?></div><div class="kpi-label">Conclusão (<?= (int) $stats['completed'] ?> de <?= (int) $stats['enrolled'] ?>)</div></div></div>
    <div class="card kpi"><span class="kpi-icon info"><?= icon('clock', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['pending'] ?></div><div class="kpi-label">Pendentes</div></div></div>
    <div class="card kpi"><span class="kpi-icon warn"><?= icon('alert', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['overdue'] ?></div><div class="kpi-label">Com prazo vencido</div></div></div>
</div>
<?php endif; ?>

<?php if ($mine || !$manager): ?>
<section class="card section-gap" aria-labelledby="mine-title">
    <h2 id="mine-title"><?= $manager ? 'Meus treinamentos' : 'Treinamentos designados' ?></h2>
    <?php if (!$mine): ?>
    <p class="empty">Nenhum treinamento designado a você no momento.</p>
    <?php else: ?>
    <div class="grid grid-cards section-gap-sm">
        <?php foreach ($mine as $t): $overdue = $t['enrollment_status'] !== 'concluido' && $t['due_date'] && $t['due_date'] < date('Y-m-d'); ?>
        <article class="training-card">
            <div class="actions"><span class="badge <?= $statusBadge[$t['enrollment_status']] ?>"><?= e(Labels::ENROLLMENT_STATUS[$t['enrollment_status']]) ?></span><?php if ($overdue): ?><span class="badge badge-danger">prazo vencido</span><?php endif; ?></div>
            <h3><?= e($t['title']) ?></h3>
            <p class="small muted"><?= e($t['summary'] ?? '') ?></p>
            <p class="small"><?= (int) $t['duration_minutes'] ?> min · nota mínima <?= (int) $t['passing_score'] ?><?= $t['due_date'] ? ' · prazo ' . e(fmt_date($t['due_date'])) : '' ?><?= $t['best_score'] !== null ? ' · sua nota ' . (int) $t['best_score'] : '' ?></p>
            <div class="actions">
                <?php if ($t['certificate_code']): ?>
                <a class="btn btn-teal btn-sm" href="/treinamentos/certificado/<?= e($t['certificate_code']) ?>"><?= icon('graduation') ?> Certificado</a>
                <a class="btn btn-ghost btn-sm" href="/treinamentos/<?= (int) $t['id'] ?>">Rever conteúdo</a>
                <?php else: ?>
                <a class="btn btn-primary btn-sm" href="/treinamentos/<?= (int) $t['id'] ?>"><?= $t['enrollment_status'] === 'pendente' ? 'Começar' : 'Continuar' ?></a>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($manager): ?>
<section class="card section-gap" aria-labelledby="mod-title">
    <h2 id="mod-title">Módulos</h2>
    <?php if (!$modules): ?>
    <p class="empty">Nenhum treinamento cadastrado. Use o treinamento modelo de LGPD para começar.</p>
    <?php else: ?>
    <div class="table-wrap section-gap-sm">
        <table class="table-stack">
            <thead><tr><th>Código</th><th>Treinamento</th><th>Perguntas</th><th>Participantes</th><th>Conclusão</th><th>Situação</th></tr></thead>
            <tbody>
            <?php foreach ($modules as $m): $pct = $m['enrolled'] > 0 ? (int) round($m['completed'] / $m['enrolled'] * 100) : 0; ?>
            <tr>
                <td data-label="Código"><span class="code"><?= e(code('TRE', (int) $m['seq'])) ?></span></td>
                <td data-label="Treinamento"><a class="title-link" href="/treinamentos/<?= (int) $m['id'] ?>"><?= e($m['title']) ?></a><div class="small muted"><?= (int) $m['duration_minutes'] ?> min · nota mínima <?= (int) $m['passing_score'] ?></div></td>
                <td data-label="Perguntas"><?= (int) $m['questions_count'] ?></td>
                <td data-label="Participantes"><?= (int) $m['enrolled'] ?><?= (int) $m['overdue'] ? ' · <span class="text-danger">' . (int) $m['overdue'] . ' vencido(s)</span>' : '' ?></td>
                <td data-label="Conclusão"><div class="bar-head small"><span><?= (int) $m['completed'] ?>/<?= (int) $m['enrolled'] ?></span><strong><?= $pct ?>%</strong></div><div class="bar"><span class="<?= width_class($pct) ?>"></span></div></td>
                <td data-label="Situação"><span class="badge <?= $m['status'] === 'publicado' ? 'badge-ok' : '' ?>"><?= e(Labels::TRAINING_STATUS[$m['status']]) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>
