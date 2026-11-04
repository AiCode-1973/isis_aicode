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
    <?php
    $quick = array_values(array_filter([
        can(P::INVENTORY_EDIT) ? ['/inventario/nova', 'database', 'Operação de tratamento'] : null,
        can(P::RISKS_EDIT) ? ['/riscos/novo', 'target', 'Risco'] : null,
        can(P::ACTIONS_EDIT) ? ['/planos/nova', 'check', 'Ação do plano'] : null,
        can(P::REQUESTS_MANAGE) ? ['/titulares/nova', 'inbox', 'Solicitação de titular'] : null,
        can(P::INCIDENTS_REPORT) ? ['/incidentes/novo', 'siren', 'Comunicar incidente'] : null,
        can(P::USERS_MANAGE) ? ['/usuarios#convidar', 'users', 'Convidar pessoa'] : null,
    ]));
    ?>
    <?php if (count($quick) === 1): ?>
    <a class="btn btn-primary" href="<?= e($quick[0][0]) ?>"><?= icon($quick[0][1]) ?> <?= e($quick[0][2]) ?></a>
    <?php elseif ($quick): ?>
    <details class="dropdown">
        <summary class="btn btn-primary"><?= icon('plus') ?> Novo</summary>
        <div class="menu-pop">
            <?php foreach ($quick as [$href, $ic, $text]): ?><a href="<?= e($href) ?>"><?= icon($ic) ?> <?= e($text) ?></a><?php endforeach; ?>
        </div>
    </details>
    <?php endif; ?>
</div>

<?php
$urgent = array_values(array_filter($alerts, static fn (array $a): bool => $a[0] === 'danger'));
$attention = array_values(array_filter($alerts, static fn (array $a): bool => $a[0] !== 'danger'));
$alertGroup = static function (string $title, string $kind, array $items): string {
    if (!$items) {
        return '';
    }
    $row = static fn (array $a): string => '<li><a href="' . e($a[2]) . '"><span>' . e($a[1]) . '</span>' . icon('arrow-right') . '</a></li>';
    $html = '<div class="alert-group ' . $kind . '"><h3>' . icon($kind === 'danger' ? 'alert' : 'clock') . ' ' . e($title) . ' <span class="count">' . count($items) . '</span></h3><ul>';
    $html .= implode('', array_map($row, array_slice($items, 0, 3))) . '</ul>';
    if (count($items) > 3) {
        $html .= '<details><summary>Ver mais ' . (count($items) - 3) . '</summary><ul>' . implode('', array_map($row, array_slice($items, 3))) . '</ul></details>';
    }
    return $html . '</div>';
};
$kpis = [];
if ($risks !== null) {
    $kpis[] = ['/riscos?abertos=1', 'target', 'warn', (int) $risks['open'], 'Riscos abertos', plural((int) ($risks['critical'] + $risks['high']), 'alto ou crítico', 'altos ou críticos')];
}
if ($actionStats !== null) {
    $kpis[] = ['/planos?abertas=1', 'clock', 'info', (int) ($actionStats['pending'] + $actionStats['in_progress']), $onlyOwn ? 'Minhas tarefas abertas' : 'Ações em aberto', plural((int) $actionStats['overdue'], 'vencida', 'vencidas')];
}
if ($requestStats !== null) {
    $kpis[] = ['/titulares?abertas=1', 'inbox', '', (int) $requestStats['open'], 'Solicitações de titulares', (int) $requestStats['overdue'] . ' fora do prazo'];
}
if ($incidentStats !== null) {
    $kpis[] = ['/incidentes?abertos=1', 'siren', 'warn', (int) $incidentStats['open'], 'Incidentes em aberto', plural((int) $incidentStats['severe'], 'grave', 'graves')];
}
if ($kpis === []) {
    $kpis[] = ['/usuarios', 'users', '', (int) $stats['users'], 'Usuários ativos', 'na empresa'];
}
?>

<div class="dash-top<?= $maturity ? '' : ' no-maturity' ?>">
    <?php if ($maturity): ?>
    <section class="card maturity" aria-labelledby="mat-title">
        <div class="maturity-head">
            <?= Isis\Core\View::partial('partials/maturity-ring', ['score' => $maturity['score']]) ?>
            <div>
                <h2 id="mat-title">Índice de maturidade</h2>
                <p class="maturity-level"><?= e($maturity['level']) ?></p>
                <p class="small muted">Calculado com o que o ÍSIS já registra. Indicador interno, não é certificação nem garantia de conformidade.</p>
            </div>
        </div>
        <ul class="pillars-list">
            <?php foreach ($maturity['pillars'] as $p): ?>
            <li>
                <a href="<?= e($p['link']) ?>">
                    <span class="pillar-name"><?= e($p['label']) ?></span>
                    <span class="pillar-score"><?= (int) $p['score'] ?></span>
                </a>
                <div class="bar"><span class="<?= e(width_class($p['score'])) ?>"></span></div>
                <span class="pillar-hint"><?= e($p['hint']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <div class="dash-side">
        <div class="kpi-grid">
            <?php foreach ($kpis as [$link, $ic, $tone, $value, $label, $sub]): ?>
            <a class="card kpi-tile" href="<?= e($link) ?>">
                <span class="kpi-icon <?= e($tone) ?>"><?= icon($ic, 'icon-lg') ?></span>
                <span class="kpi-tile-value"><?= $value ?></span>
                <span class="kpi-tile-label"><?= e($label) ?></span>
                <span class="kpi-tile-sub"><?= e($sub) ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <section class="card alerts-panel" aria-labelledby="alerts-title">
            <div class="card-header alerts-head">
                <h2 id="alerts-title">Pendências</h2>
                <a class="btn btn-ghost btn-sm" href="/tarefas">O que preciso fazer? <?= icon('arrow-right') ?></a>
            </div>
            <?php if (!$alerts): ?>
            <p class="text-ok small section-gap-sm"><?= icon('check') ?> Nenhuma pendência que exija atenção agora.</p>
            <?php else: ?>
            <?= $alertGroup('Urgente', 'danger', $urgent) ?>
            <?= $alertGroup('Atenção', 'warn', $attention) ?>
            <?php endif; ?>
        </section>
    </div>
</div>

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

<?php if ($requestTrend !== null || $actionTrend !== null): ?>
<div class="grid grid-2 section-gap">
    <?php if ($actionTrend !== null): ?>
    <section class="card" aria-labelledby="trend-actions">
        <div class="card-header"><div><h2 id="trend-actions">Ações concluídas</h2><p>Por mês, nos últimos 6 meses.</p></div></div>
        <?= Isis\Core\View::partial('partials/bar-chart', ['series' => $actionTrend, 'caption' => 'Ações concluídas por mês']) ?>
    </section>
    <?php endif; ?>
    <?php if ($requestTrend !== null): ?>
    <section class="card" aria-labelledby="trend-requests">
        <div class="card-header"><div><h2 id="trend-requests">Solicitações de titulares</h2><p>Recebidas por mês, nos últimos 6 meses.</p></div></div>
        <?= Isis\Core\View::partial('partials/bar-chart', ['series' => $requestTrend, 'caption' => 'Solicitações de titulares recebidas por mês']) ?>
    </section>
    <?php endif; ?>
</div>
<?php endif; ?>

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
            <?php if ($planTop): ?>
            <div class="plan-hint section-gap-sm">
                <p class="small"><strong>Comece por aqui</strong> <span class="muted">(sugestão do assistente)</span></p>
                <ol>
                    <?php foreach ($planTop as $item): ?><li><a href="<?= e($item['link']) ?>"><?= e($item['title']) ?></a></li><?php endforeach; ?>
                </ol>
                <a class="small" href="/configuracao-inicial/plano">Ver o plano completo</a>
            </div>
            <?php endif; ?>
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
                    <div class="bar-head"><span><?= e($o['owner_name']) ?></span><strong><?= (int) $o['open_count'] ?><?= (int) $o['overdue_count'] ? ' · <span class="text-danger">' . plural((int) $o['overdue_count'], 'vencida', 'vencidas') . '</span>' : '' ?></strong></div>
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
