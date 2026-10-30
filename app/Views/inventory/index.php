<?php

use Isis\Auth\Permission as P;
use Isis\Support\Labels;

$statusBadge = ['rascunho' => 'badge', 'ativa' => 'badge-ok', 'em_revisao' => 'badge-warn', 'descontinuada' => 'badge'];
$filtered = $filters['q'] || $filters['status'] || $filters['department_id'] || $filters['sensitive'];
?>
<div class="page-head">
    <div>
        <h1>Inventário de dados pessoais</h1>
        <p>Operações de tratamento: quais dados, de quem, para quê, com qual base legal, por quanto tempo e com quem são compartilhados.</p>
    </div>
    <div class="actions">
        <a class="btn btn-ghost" href="/inventario/catalogos"><?= icon('layers') ?> Catálogos</a>
        <a class="btn btn-ghost" href="/inventario/exportar"><?= icon('download') ?> Exportar registro (CSV)</a>
        <?php if (can(P::INVENTORY_EDIT)): ?>
        <a class="btn btn-primary" href="/inventario/nova"><?= icon('plus') ?> Nova operação</a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-4">
    <div class="card kpi"><span class="kpi-icon"><?= icon('database', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['total'] ?></div><div class="kpi-label">Operações registradas</div></div></div>
    <div class="card kpi"><span class="kpi-icon teal"><?= icon('check', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['active'] ?></div><div class="kpi-label">Ativas</div></div></div>
    <div class="card kpi"><span class="kpi-icon warn"><?= icon('alert', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['sensitive'] ?></div><div class="kpi-label">Com dados sensíveis</div></div></div>
    <div class="card kpi"><span class="kpi-icon info"><?= icon('clock', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['review_overdue'] ?></div><div class="kpi-label">Revisões vencidas</div></div></div>
</div>

<form method="get" action="/inventario" class="card filters section-gap" aria-label="Filtros">
    <div class="field">
        <label for="f-q">Buscar</label>
        <input type="search" id="f-q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Nome ou finalidade">
    </div>
    <div class="field">
        <label for="f-status">Situação</label>
        <select id="f-status" name="status">
            <option value="">Todas</option>
            <?php foreach (Labels::ACTIVITY_STATUS as $k => $v): ?>
            <option value="<?= e($k) ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="f-setor">Setor</label>
        <select id="f-setor" name="setor">
            <option value="">Todos</option>
            <?php foreach ($departments as $id => $name): ?>
            <option value="<?= (int) $id ?>"<?= $filters['department_id'] === $id ? ' selected' : '' ?>><?= e($name) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <span class="label">Dados sensíveis</span>
        <label class="check"><input type="checkbox" name="sensiveis" value="1"<?= $filters['sensitive'] ? ' checked' : '' ?>> <span>Só com dados sensíveis</span></label>
    </div>
    <div class="actions">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <?php if ($filtered): ?><a class="btn btn-ghost" href="/inventario">Limpar</a><?php endif; ?>
    </div>
</form>

<section class="card section-gap" aria-label="Operações">
    <?php if (!$activities): ?>
    <div class="empty">
        <p><?= $filtered ? 'Nenhuma operação encontrada com esses filtros.' : 'Nenhuma operação registrada ainda.' ?></p>
        <?php if (!$filtered && can(P::INVENTORY_EDIT)): ?>
        <p class="small">Comece pelas atividades que envolvem mais pessoas ou dados sensíveis.</p>
        <a class="btn btn-primary" href="/inventario/nova"><?= icon('plus') ?> Registrar a primeira operação</a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table-stack">
            <thead><tr><th>Código</th><th>Operação</th><th>Setor</th><th>Hipótese legal</th><th>Responsável</th><th>Situação</th><th>Riscos</th></tr></thead>
            <tbody>
            <?php foreach ($activities as $a): ?>
            <tr>
                <td data-label="Código"><span class="code"><?= e(code('OP', (int) $a['seq'])) ?></span></td>
                <td data-label="Operação">
                    <a class="title-link" href="/inventario/<?= (int) $a['id'] ?>"><?= e($a['name']) ?></a>
                    <?php if ($a['has_sensitive']): ?> <span class="badge badge-danger badge-sm">sensíveis</span><?php endif; ?>
                    <?php if ($a['international_transfer']): ?> <span class="badge badge-brand badge-sm">internacional</span><?php endif; ?>
                </td>
                <td data-label="Setor"><?= e($a['department_name'] ?? '—') ?></td>
                <td data-label="Hipótese legal"><?= $a['legal_basis_article'] ? e($a['legal_basis_article']) : '<span class="badge badge-warn">não definida</span>' ?></td>
                <td data-label="Responsável"><?= e($a['owner_name'] ?? '—') ?></td>
                <td data-label="Situação"><span class="badge <?= $statusBadge[$a['status']] ?? '' ?>"><?= e(label(Labels::ACTIVITY_STATUS, $a['status'])) ?></span></td>
                <td data-label="Riscos"><?= (int) $a['risks_count'] ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
