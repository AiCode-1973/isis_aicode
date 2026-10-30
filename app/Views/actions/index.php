<?php

use Isis\Auth\Permission as P;
use Isis\Support\Labels;

$filtered = $filters['q'] || $filters['status'] || $filters['owner_id'] || $filters['overdue'] || $filters['open'] || (!$onlyOwn && $filters['only_owner']);
$total = max(1, (int) $stats['total'] - (int) $stats['cancelled']);
$priorityBadge = ['alta' => 'badge-danger', 'media' => 'badge-warn', 'baixa' => 'badge'];
$statusBadge = ['pendente' => 'badge', 'em_andamento' => 'badge-brand', 'concluida' => 'badge-ok', 'cancelada' => 'badge'];
?>
<div class="page-head">
    <div>
        <h1><?= $onlyOwn ? 'Minhas tarefas' : 'Planos de ação' ?></h1>
        <p><?= $onlyOwn ? 'Ações de proteção de dados atribuídas a você. Registre o andamento e anexe evidências.' : 'Medidas mitigadoras e tarefas de conformidade, com responsáveis, prazos e evidências.' ?></p>
    </div>
    <?php if (can(P::ACTIONS_EDIT)): ?>
    <a class="btn btn-primary" href="/planos/nova"><?= icon('plus') ?> Nova ação</a>
    <?php endif; ?>
</div>

<div class="grid grid-4">
    <div class="card kpi"><span class="kpi-icon"><?= icon('clock', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['pending'] + (int) $stats['in_progress'] ?></div><div class="kpi-label">Em aberto</div></div></div>
    <a class="card kpi" href="/planos?vencidas=1"><span class="kpi-icon warn"><?= icon('alert', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['overdue'] ?></div><div class="kpi-label">Vencidas</div></div></a>
    <div class="card kpi"><span class="kpi-icon teal"><?= icon('check', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['done'] ?></div><div class="kpi-label">Concluídas</div></div></div>
    <div class="card kpi"><span class="kpi-icon info"><?= icon('target', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) round((int) $stats['done'] / $total * 100) ?>%</div><div class="kpi-label">Execução do plano</div></div></div>
</div>

<form method="get" action="/planos" class="card filters section-gap" aria-label="Filtros">
    <div class="field">
        <label for="f-q">Buscar</label>
        <input type="search" id="f-q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Título ou descrição">
    </div>
    <div class="field">
        <label for="f-status">Situação</label>
        <select id="f-status" name="status">
            <option value="">Todas</option>
            <?php foreach (Labels::ACTION_STATUS as $k => $v): ?><option value="<?= e($k) ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php if (!$onlyOwn): ?>
    <div class="field">
        <label for="f-owner">Responsável</label>
        <select id="f-owner" name="responsavel">
            <option value="">Todos</option>
            <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= $filters['owner_id'] === (int) $u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="field">
        <span class="label">Prazo</span>
        <label class="check"><input type="checkbox" name="vencidas" value="1"<?= $filters['overdue'] ? ' checked' : '' ?>> <span>Só vencidas</span></label>
        <?php if (!$onlyOwn): ?><label class="check"><input type="checkbox" name="minhas" value="1"<?= $filters['only_owner'] ? ' checked' : '' ?>> <span>Só as minhas</span></label><?php endif; ?>
    </div>
    <div class="actions">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <?php if ($filtered): ?><a class="btn btn-ghost" href="/planos">Limpar</a><?php endif; ?>
    </div>
</form>

<section class="card section-gap" aria-label="Ações">
    <?php if (!$actions): ?>
    <p class="empty"><?= $filtered ? 'Nenhuma ação com esses filtros.' : ($onlyOwn ? 'Nenhuma tarefa atribuída a você no momento.' : 'Nenhuma ação cadastrada. Crie ações a partir dos riscos ou diretamente aqui.') ?></p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table-stack">
            <thead><tr><th>Código</th><th>Ação</th><th>Responsável</th><th>Prazo</th><th>Prioridade</th><th>Situação</th></tr></thead>
            <tbody>
            <?php foreach ($actions as $x): ?>
            <tr class="<?= $x['overdue'] ? 'row-overdue' : '' ?>">
                <td data-label="Código"><span class="code"><?= e(code('ACT', (int) $x['seq'])) ?></span></td>
                <td data-label="Ação">
                    <a class="title-link" href="/planos/<?= (int) $x['id'] ?>"><?= e($x['title']) ?></a>
                    <div class="small muted">
                        <?= $x['risk_title'] ? e(code('RSK', (int) $x['risk_seq']) . ' ' . $x['risk_title']) : ($x['activity_name'] ? e(code('OP', (int) $x['activity_seq']) . ' ' . $x['activity_name']) : 'Tarefa geral') ?>
                        <?= (int) $x['evidence_count'] > 0 ? ' · ' . (int) $x['evidence_count'] . ' evidência(s)' : '' ?>
                    </div>
                </td>
                <td data-label="Responsável"><?= $x['owner_name'] ? e($x['owner_name']) : '<span class="badge badge-warn">sem responsável</span>' ?></td>
                <td data-label="Prazo" class="<?= $x['overdue'] ? 'text-danger' : '' ?>"><?= e(fmt_date($x['due_date'])) ?><?= $x['overdue'] ? ' · vencida' : '' ?></td>
                <td data-label="Prioridade"><span class="badge <?= $priorityBadge[$x['priority']] ?>"><?= e(label(Labels::PRIORITY, $x['priority'])) ?></span></td>
                <td data-label="Situação"><span class="badge <?= $statusBadge[$x['status']] ?>"><?= e(label(Labels::ACTION_STATUS, $x['status'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
