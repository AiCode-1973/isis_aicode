<?php

use Isis\Auth\Permission as P;
use Isis\Support\Labels;

$statusBadge = ['rascunho' => 'badge', 'em_aprovacao' => 'badge-warn', 'aprovado' => 'badge-ok'];
?>
<div class="page-head">
    <div>
        <h1>Relatórios de impacto (RIPD)</h1>
        <p>Relatório de Impacto à Proteção de Dados Pessoais: documenta os tratamentos que podem gerar alto risco aos titulares, os riscos e as medidas adotadas (art. 5º, XVII, e art. 38 da LGPD).</p>
    </div>
    <?php if (can(P::DPIA_EDIT)): ?>
    <a class="btn btn-primary" href="/ripd/novo"><?= icon('plus') ?> Novo RIPD</a>
    <?php endif; ?>
</div>

<div class="grid grid-4">
    <div class="card kpi"><span class="kpi-icon teal"><?= icon('scale', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['approved'] ?><span class="kpi-sub">/<?= (int) $stats['total'] ?></span></div><div class="kpi-label">Aprovados</div></div></div>
    <div class="card kpi"><span class="kpi-icon"><?= icon('file', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['draft'] ?></div><div class="kpi-label">Em elaboração</div></div></div>
    <div class="card kpi"><span class="kpi-icon warn"><?= icon('clock', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['pending'] ?></div><div class="kpi-label">Aguardando aprovação</div></div></div>
    <div class="card kpi"><span class="kpi-icon info"><?= icon('alert', 'icon-lg') ?></span><div><div class="kpi-value"><?= count($candidates) ?></div><div class="kpi-label">Operações que podem exigir RIPD · <?= (int) $stats['review_overdue'] ?> revisão(ões) vencida(s)</div></div></div>
</div>

<?php if ($candidates): ?>
<section class="card section-gap" aria-labelledby="cand-title">
    <div class="card-header">
        <div>
            <h2 id="cand-title">Operações que podem exigir RIPD</h2>
            <p>O inventário indica situações de alto risco nestas operações, que ainda não têm relatório. A decisão final é do encarregado.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table-stack">
            <thead><tr><th>Operação</th><th>Indicadores</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($candidates as $c): ?>
            <tr>
                <td data-label="Operação"><span class="code"><?= e(code('OP', $c['seq'])) ?></span> <a class="title-link" href="/inventario/<?= $c['id'] ?>"><?= e($c['name']) ?></a></td>
                <td data-label="Indicadores"><?php foreach ($c['triggers'] as $t): ?><span class="badge badge-warn badge-sm"><?= e(Labels::DPIA_TRIGGER[$t]) ?></span> <?php endforeach; ?></td>
                <td data-label=""><?php if (can(P::DPIA_EDIT)): ?><a class="btn btn-ghost btn-sm" href="/ripd/novo?operacao=<?= $c['id'] ?>">Elaborar RIPD</a><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

<form method="get" action="/ripd" class="card filters section-gap" aria-label="Filtros">
    <div class="field">
        <label for="f-q">Buscar</label>
        <input type="search" id="f-q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Título do relatório">
    </div>
    <div class="field">
        <label for="f-status">Situação</label>
        <select id="f-status" name="status">
            <option value="">Todas</option>
            <?php foreach (Labels::DPIA_STATUS as $k => $v): ?><option value="<?= e($k) ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="actions">
        <button type="submit" class="btn btn-ghost"><?= icon('search') ?> Filtrar</button>
        <?php if ($filters['q'] || $filters['status']): ?><a class="btn btn-link" href="/ripd">Limpar</a><?php endif; ?>
    </div>
</form>

<section class="card section-gap" aria-label="Relatórios">
    <?php if (!$dpias): ?>
    <p class="empty"><?= $filters['q'] || $filters['status'] ? 'Nenhum RIPD encontrado com esses filtros.' : 'Nenhum RIPD elaborado ainda. Comece pelas operações indicadas acima, se houver.' ?></p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table-stack">
            <thead><tr><th>Código</th><th>Relatório</th><th>Situação</th><th>Conclusão</th><th>Próxima revisão</th></tr></thead>
            <tbody>
            <?php foreach ($dpias as $d): $overdue = $d['status'] === 'aprovado' && $d['review_due_date'] && $d['review_due_date'] < date('Y-m-d'); ?>
            <tr>
                <td data-label="Código"><span class="code"><?= e(code('RIPD', (int) $d['seq'])) ?></span></td>
                <td data-label="Relatório"><a class="title-link" href="/ripd/<?= (int) $d['id'] ?>"><?= e($d['title']) ?></a><div class="small muted"><?= e(implode(' · ', $d['activities']) ?: 'Sem operação vinculada') ?></div></td>
                <td data-label="Situação">
                    <span class="badge <?= $statusBadge[$d['status']] ?>">v<?= (int) $d['version'] ?> · <?= e(mb_strtolower(Labels::DPIA_STATUS[$d['status']])) ?></span>
                    <?php if ($d['approved_version'] !== null && $d['status'] !== 'aprovado'): ?><div class="small muted">vigente: v<?= (int) $d['approved_version'] ?></div><?php endif; ?>
                </td>
                <td data-label="Conclusão" class="small"><?= e(Labels::of(Labels::DPIA_CONCLUSION, $d['conclusion'])) ?></td>
                <td data-label="Próxima revisão" class="<?= $overdue ? 'text-danger' : '' ?>"><?= e(fmt_date($d['review_due_date'])) ?><?= $overdue ? ' · vencida' : '' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
