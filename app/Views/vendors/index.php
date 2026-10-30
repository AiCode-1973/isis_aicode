<?php

use Isis\Auth\Permission as P;
use Isis\Support\Labels;

$levelBadge = ['alto' => 'badge-danger', 'medio' => 'badge-warn', 'baixo' => 'badge-ok'];
$filtered = $filters['q'] || $filters['status'] || $filters['risk'] || $filters['unassessed'];
?>
<div class="page-head">
    <div>
        <h1>Fornecedores</h1>
        <p>Terceiros que tratam ou recebem dados pessoais da empresa: avaliação, contratos e documentos de proteção de dados.</p>
    </div>
    <?php if (can(P::VENDORS_EDIT)): ?><a class="btn btn-primary" href="/fornecedores/novo"><?= icon('plus') ?> Novo fornecedor</a><?php endif; ?>
</div>

<div class="grid grid-4">
    <div class="card kpi"><span class="kpi-icon"><?= icon('truck', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['active'] ?></div><div class="kpi-label">Fornecedores ativos</div></div></div>
    <a class="card kpi" href="/fornecedores?risco=alto"><span class="kpi-icon warn"><?= icon('alert', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['high'] ?></div><div class="kpi-label">De risco alto</div></div></a>
    <a class="card kpi" href="/fornecedores?sem_avaliacao=1"><span class="kpi-icon info"><?= icon('target', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['unassessed'] ?></div><div class="kpi-label">Sem avaliação · <?= (int) $stats['review_overdue'] ?> com revisão vencida</div></div></a>
    <div class="card kpi"><span class="kpi-icon teal"><?= icon('file', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['no_dpa'] ?></div><div class="kpi-label">Sem contrato com cláusulas de dados · <?= (int) $stats['contracts_expiring'] ?> contrato(s) vencendo em 30 dias</div></div></div>
</div>

<form method="get" action="/fornecedores" class="card filters section-gap" aria-label="Filtros">
    <div class="field"><label for="f-q">Buscar</label><input type="search" id="f-q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Nome ou serviço"></div>
    <div class="field">
        <label for="f-status">Situação</label>
        <select id="f-status" name="status"><option value="">Todas</option><?php foreach (Labels::VENDOR_STATUS as $k => $v): ?><option value="<?= e($k) ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    </div>
    <div class="field">
        <label for="f-risk">Risco</label>
        <select id="f-risk" name="risco"><option value="">Todos</option><?php foreach (Labels::LEVEL as $k => $v): ?><option value="<?= e($k) ?>"<?= $filters['risk'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    </div>
    <div class="field"><span class="label">Mostrar</span><label class="check"><input type="checkbox" name="sem_avaliacao" value="1"<?= $filters['unassessed'] ? ' checked' : '' ?>> <span>Só sem avaliação</span></label></div>
    <div class="actions"><button type="submit" class="btn btn-primary">Filtrar</button><?php if ($filtered): ?><a class="btn btn-ghost" href="/fornecedores">Limpar</a><?php endif; ?></div>
</form>

<section class="card section-gap" aria-label="Fornecedores">
    <?php if (!$vendors): ?>
    <p class="empty"><?= $filtered ? 'Nenhum fornecedor com esses filtros.' : 'Nenhum fornecedor cadastrado. Comece pelos que recebem dados de clientes ou dados sensíveis.' ?></p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table-stack">
            <thead><tr><th>Código</th><th>Fornecedor</th><th>Papel</th><th>Risco</th><th>Contrato com cláusulas</th><th>Próxima revisão</th><th>Situação</th></tr></thead>
            <tbody>
            <?php foreach ($vendors as $v): ?>
            <tr>
                <td data-label="Código"><span class="code"><?= e(code('FOR', (int) $v['seq'])) ?></span></td>
                <td data-label="Fornecedor">
                    <a class="title-link" href="/fornecedores/<?= (int) $v['id'] ?>"><?= e($v['name']) ?></a>
                    <div class="small muted"><?= e($v['service'] ?? '') ?><?= $v['sensitive_data'] ? ' · <span class="text-danger">dados sensíveis</span>' : '' ?><?= $v['international'] ? ' · exterior' : '' ?></div>
                </td>
                <td data-label="Papel"><?= e(explode(' (', label(Labels::VENDOR_ROLE, $v['role']))[0]) ?></td>
                <td data-label="Risco"><?= $v['risk_level'] ? '<span class="badge ' . $levelBadge[$v['risk_level']] . '">' . e(Labels::LEVEL[$v['risk_level']]) . ' · ' . (int) $v['last_score'] . '</span>' : '<span class="badge badge-warn">não avaliado</span>' ?></td>
                <td data-label="Contrato com cláusulas"><?= (int) $v['dpa_count'] > 0 ? '<span class="badge badge-ok">sim</span>' : ($v['data_access'] ? '<span class="badge badge-danger">não</span>' : '—') ?></td>
                <td data-label="Próxima revisão" class="<?= $v['next_review_date'] && $v['next_review_date'] < date('Y-m-d') ? 'text-danger' : '' ?>"><?= e(fmt_date($v['next_review_date'])) ?></td>
                <td data-label="Situação"><span class="badge"><?= e(label(Labels::VENDOR_STATUS, $v['status'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
