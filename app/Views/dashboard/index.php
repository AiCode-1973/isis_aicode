<?php

use Isis\Auth\Permission as P;
use Isis\Services\RiskMatrix;
use Isis\Support\Labels;

$company = $tenant['trade_name'] ?: $tenant['legal_name'];
$firstName = explode(' ', (string) auth_user()['name'])[0];
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><?= e($tenant['segment_name']) ?></p>
        <h1>Olá, <?= e($firstName) ?></h1>
        <p><?= $onlyOwn ? 'Suas tarefas de proteção de dados em ' . e($company) . '.' : 'Situação da gestão de privacidade de ' . e($company) . '.' ?></p>
    </div>
    <div class="actions">
        <?php if (can(P::INVENTORY_EDIT)): ?><a class="btn btn-ghost" href="/inventario/nova"><?= icon('plus') ?> Operação</a><?php endif; ?>
        <?php if (can(P::RISKS_EDIT)): ?><a class="btn btn-ghost" href="/riscos/novo"><?= icon('plus') ?> Risco</a><?php endif; ?>
        <?php if (can(P::INCIDENTS_REPORT)): ?><a class="btn btn-ghost" href="/incidentes/novo"><?= icon('siren') ?> Comunicar incidente</a><?php endif; ?>
        <?php if (can(P::USERS_MANAGE)): ?><a class="btn btn-primary" href="/usuarios#convidar"><?= icon('users') ?> Convidar</a><?php endif; ?>
    </div>
</div>

<?php if ($alerts): ?>
<section class="card alerts-card" aria-labelledby="alerts-title">
    <h2 id="alerts-title" class="visually-hidden">Pendências</h2>
    <ul class="issues">
        <?php foreach ($alerts as [$kind, $text, $link]): ?>
        <li class="<?= $kind === 'danger' ? 'issue-danger' : '' ?>"><?= icon('alert') ?><a href="<?= e($link) ?>"><?= e($text) ?></a></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if ($myAcks || $myTrainings): ?>
<section class="card for-you section-gap" aria-labelledby="you-title">
    <h2 id="you-title"><?= icon('user') ?> Para você</h2>
    <ul class="issue-groups section-gap-sm">
        <?php foreach ($myTrainings as $t): $late = $t['due_date'] && $t['due_date'] < date('Y-m-d'); ?>
        <li><?= icon('graduation') ?> Treinamento <a class="title-link" href="/treinamentos/<?= (int) $t['id'] ?>"><?= e($t['title']) ?></a> <span class="small <?= $late ? 'text-danger' : 'muted' ?>"><?= $t['due_date'] ? '· prazo ' . e(fmt_date($t['due_date'])) . ($late ? ' (vencido)' : '') : '' ?></span></li>
        <?php endforeach; ?>
        <?php foreach ($myAcks as $p): ?>
        <li><?= icon('file') ?> Ler e dar ciência: <a class="title-link" href="/politicas/<?= (int) $p['id'] ?>"><?= e($p['title']) ?></a></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<div class="grid grid-4 section-gap">
    <?php if ($inventory !== null): ?>
    <a class="card kpi" href="/inventario"><span class="kpi-icon"><?= icon('database', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $inventory['active'] ?><span class="kpi-sub">/<?= (int) $inventory['total'] ?></span></div><div class="kpi-label">Operações ativas</div></div></a>
    <?php endif; ?>
    <?php if ($risks !== null): ?>
    <a class="card kpi" href="/riscos?abertos=1"><span class="kpi-icon warn"><?= icon('target', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $risks['open'] ?></div><div class="kpi-label">Riscos abertos · <?= (int) ($risks['critical'] + $risks['high']) ?> altos/críticos</div></div></a>
    <?php endif; ?>
    <?php if ($actionStats !== null): ?>
    <a class="card kpi" href="/planos?abertas=1"><span class="kpi-icon info"><?= icon('clock', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) ($actionStats['pending'] + $actionStats['in_progress']) ?></div><div class="kpi-label"><?= $onlyOwn ? 'Minhas tarefas abertas' : 'Ações em aberto' ?> · <?= (int) $actionStats['overdue'] ?> vencida(s)</div></div></a>
    <?php
        $base = max(1, (int) $actionStats['total'] - (int) $actionStats['cancelled']);
        $execution = (int) round((int) $actionStats['done'] / $base * 100);
    ?>
    <a class="card kpi" href="/planos"><span class="kpi-icon teal"><?= icon('check', 'icon-lg') ?></span><div><div class="kpi-value"><?= $execution ?>%</div><div class="kpi-label">Execução dos planos</div></div></a>
    <?php endif; ?>
    <?php if ($requestStats !== null): ?>
    <a class="card kpi" href="/titulares?abertas=1"><span class="kpi-icon"><?= icon('inbox', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $requestStats['open'] ?></div><div class="kpi-label">Solicitações de titulares em aberto · <?= (int) $requestStats['overdue'] ?> fora do prazo</div></div></a>
    <?php endif; ?>
    <?php if ($incidentStats !== null): ?>
    <a class="card kpi" href="/incidentes?abertos=1"><span class="kpi-icon warn"><?= icon('siren', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $incidentStats['open'] ?></div><div class="kpi-label">Incidentes em aberto · <?= (int) $incidentStats['severe'] ?> graves</div></div></a>
    <?php endif; ?>
    <?php if ($vendorStats !== null): ?>
    <a class="card kpi" href="/fornecedores"><span class="kpi-icon"><?= icon('truck', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $vendorStats['active'] ?></div><div class="kpi-label">Fornecedores · <?= (int) $vendorStats['high'] ?> de risco alto</div></div></a>
    <?php endif; ?>
    <?php if ($trainingStats !== null): $tp = $trainingStats['enrolled'] > 0 ? (int) round($trainingStats['completed'] / $trainingStats['enrolled'] * 100) : null; ?>
    <a class="card kpi" href="/treinamentos"><span class="kpi-icon teal"><?= icon('graduation', 'icon-lg') ?></span><div><div class="kpi-value"><?= $tp === null ? '—' : $tp . '%' ?></div><div class="kpi-label">Conclusão dos treinamentos</div></div></a>
    <?php endif; ?>
    <?php if ($inventory === null && $risks === null): ?>
    <div class="card kpi"><span class="kpi-icon"><?= icon('users', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['users'] ?></div><div class="kpi-label">Usuários ativos</div></div></div>
    <?php endif; ?>
</div>

<div class="grid grid-main section-gap">
    <div class="stack">
        <?php if ($actionStats !== null): ?>
        <section class="card" aria-labelledby="due-title">
            <div class="card-header">
                <div><h2 id="due-title">Prazos</h2><p>Ações vencidas e que vencem nos próximos 30 dias.</p></div>
                <a class="btn btn-ghost btn-sm" href="/planos">Ver todas</a>
            </div>
            <?php if (!$upcoming): ?>
            <p class="empty">Nenhum prazo nos próximos 30 dias.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table-stack">
                    <thead><tr><th>Prazo</th><th>Ação</th><th>Responsável</th><th>Situação</th></tr></thead>
                    <tbody>
                    <?php foreach ($upcoming as $x): ?>
                    <tr class="<?= $x['overdue'] ? 'row-overdue' : '' ?>">
                        <td data-label="Prazo" class="<?= $x['overdue'] ? 'text-danger' : '' ?>"><?= e(fmt_date($x['due_date'])) ?></td>
                        <td data-label="Ação"><a class="title-link" href="/planos/<?= (int) $x['id'] ?>"><?= e($x['title']) ?></a></td>
                        <td data-label="Responsável"><?= e($x['owner_name'] ?? '—') ?></td>
                        <td data-label="Situação"><?= $x['overdue'] ? '<span class="badge badge-danger">vencida</span>' : '<span class="badge">' . e(label(Labels::ACTION_STATUS, $x['status'])) . '</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($heatmap !== null): ?>
        <section class="card" aria-labelledby="risk-title">
            <div class="card-header">
                <div><h2 id="risk-title">Riscos</h2><p>Matriz de probabilidade × impacto dos riscos abertos.</p></div>
                <a class="btn btn-ghost btn-sm" href="/riscos">Gestão de riscos</a>
            </div>
            <div class="risk-split">
                <div><?= Isis\Core\View::partial('partials/heatmap', ['heatmap' => $heatmap, 'selected' => null]) ?></div>
                <ul class="bar-list">
                    <?php $open = max(1, (int) $risks['open']); foreach (['critico' => $risks['critical'], 'alto' => $risks['high'], 'medio' => $risks['medium'], 'baixo' => $risks['low']] as $level => $n): ?>
                    <li>
                        <div class="bar-head"><a href="/riscos?nivel=<?= $level ?>&amp;abertos=1"><?= e(RiskMatrix::label($level)) ?></a><strong><?= (int) $n ?></strong></div>
                        <div class="bar <?= $level ?>"><span class="<?= width_class($n / $open * 100) ?>"></span></div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($issues): ?>
        <section class="card" aria-labelledby="inv-title">
            <div class="card-header">
                <div><h2 id="inv-title">Inventário: o que falta completar</h2><p>Orientações automáticas de preenchimento.</p></div>
                <a class="btn btn-ghost btn-sm" href="/inventario">Inventário</a>
            </div>
            <ul class="issue-groups">
                <?php foreach ($issues as $item): ?>
                <li>
                    <a class="title-link" href="/inventario/<?= (int) $item['activity']['id'] ?>"><?= e(code('OP', (int) $item['activity']['seq']) . ' · ' . $item['activity']['name']) ?></a>
                    <span class="small muted">· <?= e(implode(' ', array_slice($item['issues'], 0, 3))) ?><?= count($item['issues']) > 3 ? ' (+' . (count($item['issues']) - 3) . ')' : '' ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php if ($recent): ?>
        <section class="card" aria-labelledby="recent-title">
            <div class="card-header">
                <div><h2 id="recent-title">Atividade recente</h2></div>
                <a class="btn btn-ghost btn-sm" href="/auditoria">Trilha de auditoria</a>
            </div>
            <ul class="timeline">
                <?php foreach ($recent as $row): ?>
                <li><div class="meta"><?= e(fmt_datetime($row['created_at'])) ?> · <?= e($row['user_name'] ?? 'Sistema') ?></div><div><?= e(audit_label($row['action'])) ?></div></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
    </div>

    <div class="stack">
        <?php if (!$onlyOwn): ?>
        <section class="card" aria-labelledby="journey-title">
            <div class="card-header">
                <div><h2 id="journey-title">Jornada de implantação</h2></div>
                <span class="badge badge-teal"><?= (int) $progress ?>%</span>
            </div>
            <div class="progress" role="progressbar" aria-valuenow="<?= (int) $progress ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progresso da implantação">
                <span class="<?= e(width_class($progress)) ?>"></span>
            </div>
            <ul class="steps section-gap-sm">
                <?php foreach ($journey as $i => $step): ?>
                <li class="<?= $step['done'] ? 'done' : '' ?>">
                    <span class="step-dot" aria-hidden="true"><?= $step['done'] ? icon('check', 'icon') : $i + 1 ?></span>
                    <div>
                        <div class="step-title"><?= $step['link'] && !$step['done'] ? '<a href="' . e($step['link']) . '">' . e($step['label']) . '</a>' : e($step['label']) ?></div>
                        <div class="step-hint"><?= e($step['hint']) ?></div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <p class="hint section-gap-sm">Indicador de organização interna. Não representa certificação nem garantia de conformidade legal.</p>
        </section>
        <?php endif; ?>

        <?php if ($byOwner): ?>
        <section class="card" aria-labelledby="owner-title">
            <h2 id="owner-title">Responsáveis</h2>
            <p class="small muted">Ações em aberto por pessoa.</p>
            <ul class="bar-list section-gap-sm">
                <?php $max = max(1, ...array_map(static fn (array $o): int => (int) $o['open_count'], $byOwner)); foreach ($byOwner as $o): ?>
                <li>
                    <div class="bar-head"><span><?= e($o['owner_name']) ?></span><strong><?= (int) $o['open_count'] ?><?= (int) $o['overdue_count'] ? ' · <span class="text-danger">' . (int) $o['overdue_count'] . ' vencida(s)</span>' : '' ?></strong></div>
                    <div class="bar"><span class="<?= width_class((int) $o['open_count'] / $max * 100) ?>"></span></div>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <section class="card" aria-labelledby="dpo-title">
            <h2 id="dpo-title">Encarregado de dados</h2>
            <?php if ($tenant['dpo_name']): ?>
            <dl class="dl section-gap-sm">
                <dt>Nome</dt><dd><?= e($tenant['dpo_name']) ?></dd>
                <dt>E-mail</dt><dd><?= e($tenant['dpo_email']) ?></dd>
                <dt>Canal</dt><dd><?= e($tenant['privacy_email'] ?: $tenant['dpo_email']) ?></dd>
            </dl>
            <?php else: ?><p class="muted">Ainda não definido.</p><?php endif; ?>
        </section>

        <?php if ($guidance): ?>
        <section class="card" aria-labelledby="guide-title">
            <h2 id="guide-title">Orientações para <?= e($tenant['segment_name']) ?></h2>
            <ul class="guidance section-gap-sm">
                <?php foreach ($guidance as $tip): ?><li><?= e($tip) ?></li><?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <section class="card" aria-labelledby="role-title">
            <h2 id="role-title">Seu perfil</h2>
            <p class="section-gap-sm"><span class="badge badge-brand"><?= e($role?->label()) ?></span></p>
            <p class="small muted"><?= e($role?->description()) ?></p>
        </section>
    </div>
</div>
