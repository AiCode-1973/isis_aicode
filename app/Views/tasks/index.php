<?php

use Isis\Services\TaskCenterService as Center;

$badge = [4 => 'badge-danger', 3 => 'badge-warn', 2 => 'badge-brand', 1 => 'badge'];
$when = static function (?int $days): string {
    return match (true) {
        $days === null => 'sem prazo',
        $days < -1 => 'vencida há ' . abs($days) . ' dias',
        $days === -1 => 'venceu ontem',
        $days === 0 => 'vence hoje',
        $days === 1 => 'vence amanhã',
        default => 'em ' . $days . ' dias',
    };
};
$link = static fn (array $change): string => '/tarefas?' . http_build_query(array_filter(array_merge($filters, $change), static fn ($v): bool => $v !== ''));
$groups = [];
foreach ($tasks as $t) {
    $groups[$t['priority']][] = $t;
}
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Central de tarefas</p>
        <h1>O que preciso fazer?</h1>
        <p>Tudo o que está pendente nos módulos, numa lista só: prazo, responsável, prioridade e o próximo passo recomendado.</p>
    </div>
</div>

<div class="grid grid-4">
    <a class="card kpi" href="<?= e($link(['prioridade' => '4', 'prazo' => '', 'escopo' => ''])) ?>"><span class="kpi-icon danger"><?= icon('alert', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $summary['urgent'] ?></div><div class="kpi-label">Urgentes</div></div></a>
    <a class="card kpi" href="<?= e($link(['prazo' => 'vencidas', 'prioridade' => ''])) ?>"><span class="kpi-icon warn"><?= icon('clock', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $summary['overdue'] ?></div><div class="kpi-label">Com prazo vencido</div></div></a>
    <a class="card kpi" href="<?= e($link(['prazo' => '7', 'prioridade' => ''])) ?>"><span class="kpi-icon info"><?= icon('target', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $summary['week'] ?></div><div class="kpi-label">Vencem em 7 dias</div></div></a>
    <a class="card kpi" href="<?= e($link(['escopo' => 'minhas'])) ?>"><span class="kpi-icon teal"><?= icon('user', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $summary['mine'] ?></div><div class="kpi-label">Comigo</div></div></a>
</div>

<form method="get" action="/tarefas" class="card filters section-gap" aria-label="Filtros">
    <div class="field">
        <label for="f-escopo">Mostrar</label>
        <select id="f-escopo" name="escopo">
            <option value="">Todas as tarefas</option>
            <option value="minhas"<?= $filters['escopo'] === 'minhas' ? ' selected' : '' ?>>Só as minhas</option>
            <option value="sem_responsavel"<?= $filters['escopo'] === 'sem_responsavel' ? ' selected' : '' ?>>Sem responsável (<?= (int) $summary['unowned'] ?>)</option>
        </select>
    </div>
    <div class="field">
        <label for="f-prioridade">Prioridade</label>
        <select id="f-prioridade" name="prioridade">
            <option value="">Todas</option>
            <?php foreach (Center::PRIORITIES as $k => $v): ?><option value="<?= $k ?>"<?= $filters['prioridade'] === (string) $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="f-modulo">Módulo</label>
        <select id="f-modulo" name="modulo">
            <option value="">Todos</option>
            <?php foreach ($modules as $k => $v): ?><option value="<?= e($k) ?>"<?= $filters['modulo'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="f-prazo">Prazo</label>
        <select id="f-prazo" name="prazo">
            <option value="">Qualquer prazo</option>
            <option value="vencidas"<?= $filters['prazo'] === 'vencidas' ? ' selected' : '' ?>>Vencidas</option>
            <option value="7"<?= $filters['prazo'] === '7' ? ' selected' : '' ?>>Até 7 dias</option>
            <option value="30"<?= $filters['prazo'] === '30' ? ' selected' : '' ?>>Até 30 dias</option>
        </select>
    </div>
    <div class="actions">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <?php if ($filtered): ?><a class="btn btn-ghost" href="/tarefas">Limpar</a><?php endif; ?>
    </div>
</form>

<?php if (!$tasks): ?>
<section class="card section-gap">
    <p class="empty"><?= $filtered ? 'Nenhuma tarefa com esses filtros.' : 'Nada pendente por aqui. Quando surgir um prazo, um pedido de titular ou uma aprovação, ele aparece nesta lista.' ?></p>
</section>
<?php else: ?>
<p class="small muted section-gap"><?= plural(count($tasks), 'tarefa', 'tarefas') ?><?= $filtered ? ' com os filtros escolhidos' : '' ?>, da mais urgente para a menos urgente.</p>
<?php foreach (Center::PRIORITIES as $level => $label): if (empty($groups[$level])) { continue; } ?>
<section class="card task-group section-gap" aria-labelledby="g-<?= $level ?>">
    <div class="card-header">
        <div><h2 id="g-<?= $level ?>"><span class="badge <?= $badge[$level] ?>"><?= e($label) ?></span> <?= plural(count($groups[$level]), 'tarefa', 'tarefas') ?></h2></div>
    </div>
    <div class="table-wrap">
        <table class="table-stack task-table">
            <thead><tr><th>Tarefa</th><th>Prazo</th><th>Responsável</th><th>Próximo passo</th></tr></thead>
            <tbody>
            <?php foreach ($groups[$level] as $t): $days = $center->daysLeft($t['due']); ?>
            <tr class="<?= $days !== null && $days < 0 ? 'row-overdue' : '' ?>">
                <td data-label="Tarefa">
                    <p class="task-module"><?= icon(Center::ICONS[$t['module']] ?? 'check') ?> <?= e(Center::MODULES[$t['module']]) ?> · <span class="code"><?= e($t['code']) ?></span></p>
                    <a class="title-link" href="<?= e($t['link']) ?>"><?= e($t['title']) ?></a>
                    <div class="small muted"><?= e($t['reason']) ?></div>
                </td>
                <td data-label="Prazo" class="task-due<?= $days !== null && $days < 0 ? ' text-danger' : '' ?>">
                    <?php if ($t['due']): ?><?= e(fmt_date($t['due'])) ?><div class="small<?= $days !== null && $days < 0 ? '' : ' muted' ?>"><?= e($when($days)) ?></div><?php else: ?><span class="muted">sem prazo</span><?php endif; ?>
                </td>
                <td data-label="Responsável"><?= $t['owner'] ? e($t['owner']) . ($t['mine'] ? ' <span class="badge badge-teal">você</span>' : '') : '<span class="badge badge-warn">sem responsável</span>' ?></td>
                <td data-label="Próximo passo" class="task-next"><?= e($t['next']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endforeach; ?>
<?php endif; ?>

<p class="hint section-gap">A prioridade combina o prazo (vencido é urgente; até 3 dias, alta; até 14 dias, média) com a gravidade de cada item. É uma ordem sugerida: a decisão continua com a equipe.</p>
