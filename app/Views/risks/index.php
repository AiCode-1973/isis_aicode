<?php

use Isis\Auth\Permission as P;
use Isis\Services\RiskMatrix;
use Isis\Support\Labels;

$filtered = $filters['q'] || $filters['status'] || $filters['level'] || $filters['category'] || $filters['cell'] || $filters['open'];
$open = max(1, (int) $stats['open']);
?>
<div class="page-head">
    <div>
        <h1>Riscos</h1>
        <p>Avaliação por probabilidade e impacto (escala de 1 a 5), tratamento, responsáveis e revisão.</p>
    </div>
    <?php if (can(P::RISKS_EDIT)): ?>
    <a class="btn btn-primary" href="/riscos/novo"><?= icon('plus') ?> Novo risco</a>
    <?php endif; ?>
</div>

<div class="grid grid-main">
    <section class="card" aria-labelledby="hm-title">
        <div class="card-header">
            <div><h2 id="hm-title">Matriz de riscos abertos</h2><p>Clique numa célula para filtrar.</p></div>
        </div>
        <?= Isis\Core\View::partial('partials/heatmap', ['heatmap' => $heatmap, 'selected' => $filters['cell']]) ?>
    </section>
    <section class="card" aria-labelledby="lv-title">
        <h2 id="lv-title">Riscos abertos por nível</h2>
        <ul class="bar-list section-gap-sm">
            <?php foreach (['critico' => $stats['critical'], 'alto' => $stats['high'], 'medio' => $stats['medium'], 'baixo' => $stats['low']] as $level => $n): ?>
            <li>
                <div class="bar-head"><a href="/riscos?nivel=<?= $level ?>&amp;abertos=1"><?= e(RiskMatrix::label($level)) ?></a><strong><?= (int) $n ?></strong></div>
                <div class="bar <?= $level ?>"><span class="<?= width_class($n / $open * 100) ?>"></span></div>
            </li>
            <?php endforeach; ?>
        </ul>
        <dl class="dl section-gap">
            <dt>Abertos</dt><dd><?= (int) $stats['open'] ?> de <?= (int) $stats['total'] ?></dd>
            <dt>Sem responsável</dt><dd><?= (int) $stats['unowned'] ?></dd>
            <dt>Revisão vencida</dt><dd><?= (int) $stats['review_overdue'] ?></dd>
        </dl>
    </section>
</div>

<form method="get" action="/riscos" class="card filters section-gap" aria-label="Filtros">
    <div class="field">
        <label for="f-q">Buscar</label>
        <input type="search" id="f-q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Título ou descrição">
    </div>
    <div class="field">
        <label for="f-nivel">Nível</label>
        <select id="f-nivel" name="nivel">
            <option value="">Todos</option>
            <?php foreach (['critico', 'alto', 'medio', 'baixo'] as $lv): ?><option value="<?= $lv ?>"<?= $filters['level'] === $lv ? ' selected' : '' ?>><?= e(RiskMatrix::label($lv)) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="f-status">Situação</label>
        <select id="f-status" name="status">
            <option value="">Todas</option>
            <?php foreach (Labels::RISK_STATUS as $k => $v): ?><option value="<?= e($k) ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="f-cat">Categoria</label>
        <select id="f-cat" name="categoria">
            <option value="">Todas</option>
            <?php foreach (Labels::RISK_CATEGORY as $k => $v): ?><option value="<?= e($k) ?>"<?= $filters['category'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="actions">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <?php if ($filtered): ?><a class="btn btn-ghost" href="/riscos">Limpar</a><?php endif; ?>
    </div>
</form>

<section class="card section-gap" aria-label="Lista de riscos">
    <?php if ($filters['cell']): ?>
    <p class="small muted">Mostrando a célula probabilidade <strong><?= e(Labels::LIKELIHOOD[$filters['cell'][0]]) ?></strong> × impacto <strong><?= e(Labels::IMPACT[$filters['cell'][1]]) ?></strong>.</p>
    <?php endif; ?>
    <?php if (!$risks): ?>
    <p class="empty"><?= $filtered ? 'Nenhum risco com esses filtros.' : 'Nenhum risco registrado. Comece pelas operações com dados sensíveis ou grande volume de titulares.' ?></p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table-stack">
            <thead><tr><th>Código</th><th>Risco</th><th>Nível</th><th>Residual</th><th>Tratamento</th><th>Responsável</th><th>Ações</th><th>Situação</th></tr></thead>
            <tbody>
            <?php foreach ($risks as $r): ?>
            <tr>
                <td data-label="Código"><span class="code"><?= e(code('RSK', (int) $r['seq'])) ?></span></td>
                <td data-label="Risco">
                    <a class="title-link" href="/riscos/<?= (int) $r['id'] ?>"><?= e($r['title']) ?></a>
                    <div class="small muted"><?= e(label(Labels::RISK_CATEGORY, $r['category'])) ?><?= $r['activity_name'] ? ' · ' . e(code('OP', (int) $r['activity_seq']) . ' ' . $r['activity_name']) : '' ?></div>
                </td>
                <td data-label="Nível"><?= risk_badge((int) $r['likelihood'], (int) $r['impact']) ?></td>
                <td data-label="Residual"><?= $r['residual_likelihood'] ? risk_badge((int) $r['residual_likelihood'], (int) $r['residual_impact']) : '<span class="muted">—</span>' ?></td>
                <td data-label="Tratamento"><?= e(label(Labels::RISK_TREATMENT, $r['treatment'])) ?></td>
                <td data-label="Responsável"><?= e($r['owner_name'] ?? '—') ?></td>
                <td data-label="Ações"><?= (int) $r['actions_done'] ?>/<?= (int) $r['actions_count'] ?></td>
                <td data-label="Situação"><span class="badge"><?= e(label(Labels::RISK_STATUS, $r['status'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
