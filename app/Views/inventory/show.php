<?php

use Isis\Auth\Permission as P;
use Isis\Support\Labels;

$text = static fn (?string $v): string => $v !== null && trim($v) !== '' ? e($v) : '<span class="muted">—</span>';
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/inventario">Inventário</a> · <?= e(code('OP', (int) $a['seq'])) ?></p>
        <h1><?= e($a['name']) ?></h1>
        <p>
            <span class="badge"><?= e(label(Labels::ACTIVITY_STATUS, $a['status'])) ?></span>
            <?php if ($a['has_sensitive']): ?><span class="badge badge-danger">dados sensíveis</span><?php endif; ?>
            <?php if ($a['international_transfer']): ?><span class="badge badge-brand">transferência internacional</span><?php endif; ?>
            · atualizado em <?= e(fmt_datetime($a['updated_at'] ?? $a['created_at'])) ?>
        </p>
    </div>
    <div class="actions">
        <?php if (can(P::RISKS_EDIT)): ?><a class="btn btn-ghost" href="/riscos/novo?operacao=<?= (int) $a['id'] ?>"><?= icon('target') ?> Registrar risco</a><?php endif; ?>
        <?php if ($canEdit): ?>
        <a class="btn btn-primary" href="/inventario/<?= (int) $a['id'] ?>/editar">Editar</a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-main">
    <div class="stack">
        <section class="card">
            <h2>Finalidade e hipótese legal</h2>
            <div class="detail-grid section-gap-sm">
                <div class="detail full"><span class="label">Finalidade</span><div class="value"><?= $text($a['purpose']) ?></div></div>
                <div class="detail"><span class="label">Hipótese legal</span><div class="value"><?= $a['legal_basis_article'] ? e($a['legal_basis_article'] . ' · ' . $a['legal_basis_name']) : '<span class="badge badge-warn">não definida</span>' ?></div></div>
                <div class="detail"><span class="label">Dados sensíveis (art. 11)</span><div class="value"><?= $a['sensitive_basis_article'] ? e($a['sensitive_basis_article'] . ' · ' . $a['sensitive_basis_name']) : '<span class="muted">—</span>' ?></div></div>
                <div class="detail"><span class="label">Papel da empresa</span><div class="value"><?= e(label(Labels::CONTROLLER_ROLE, $a['controller_role'])) ?></div></div>
                <div class="detail"><span class="label">Justificativa e salvaguardas</span><div class="value"><?= $text($a['legal_basis_notes']) ?></div></div>
                <?php if ($a['description']): ?><div class="detail"><span class="label">Descrição</span><div class="value"><?= e($a['description']) ?></div></div><?php endif; ?>
            </div>
        </section>

        <section class="card">
            <h2>Dados e titulares</h2>
            <div class="detail-grid section-gap-sm">
                <div class="detail">
                    <span class="label">Categorias de dados</span>
                    <div class="chips"><?php foreach ($a['data_categories'] as $c): ?><span class="chip<?= $c['is_sensitive'] ? ' sensitive' : '' ?>"><?= e($c['name']) ?></span><?php endforeach; ?><?= $a['data_categories'] ? '' : '<span class="muted">—</span>' ?></div>
                </div>
                <div class="detail">
                    <span class="label">Titulares</span>
                    <div class="chips"><?php foreach ($a['subject_categories'] as $c): ?><span class="chip<?= $c['vulnerable'] ? ' vulnerable' : '' ?>"><?= e($c['name']) ?></span><?php endforeach; ?><?= $a['subject_categories'] ? '' : '<span class="muted">—</span>' ?></div>
                </div>
                <div class="detail"><span class="label">Volume</span><div class="value"><?= e(label(Labels::SUBJECTS_VOLUME, $a['subjects_volume'])) ?></div></div>
                <div class="detail"><span class="label">Origem</span><div class="value"><?= e(label(Labels::DATA_SOURCE, $a['data_source'])) ?><?= $a['source_details'] ? ' · ' . e($a['source_details']) : '' ?></div></div>
            </div>
        </section>

        <section class="card">
            <h2>Sistemas, setores e compartilhamentos</h2>
            <div class="detail-grid section-gap-sm">
                <div class="detail">
                    <span class="label">Sistemas</span>
                    <div class="chips"><?php foreach ($a['systems'] as $s): ?><span class="chip"><?= e($s['name']) ?> · <?= e(label(Labels::SYSTEM_HOSTING, $s['hosting'])) ?></span><?php endforeach; ?><?= $a['systems'] ? '' : '<span class="muted">—</span>' ?></div>
                </div>
                <div class="detail">
                    <span class="label">Setores envolvidos</span>
                    <div class="chips"><?php foreach ($a['departments'] as $d): ?><span class="chip"><?= e($d['name']) ?></span><?php endforeach; ?><?= $a['departments'] ? '' : '<span class="muted">—</span>' ?></div>
                </div>
            </div>
            <?php if ($a['sharings']): ?>
            <div class="table-wrap section-gap">
                <table class="table-stack">
                    <thead><tr><th>Destinatário</th><th>Tipo</th><th>Finalidade</th><th>Local</th></tr></thead>
                    <tbody>
                    <?php foreach ($a['sharings'] as $s): ?>
                    <tr>
                        <td data-label="Destinatário"><?= e($s['recipient']) ?></td>
                        <td data-label="Tipo"><?= e(label(Labels::RECIPIENT_TYPE, $s['recipient_type'])) ?></td>
                        <td data-label="Finalidade"><?= e($s['purpose'] ?? '—') ?></td>
                        <td data-label="Local"><?= $s['international'] ? '<span class="badge badge-brand">Exterior</span>' : 'Brasil' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?><p class="muted small section-gap-sm">Sem compartilhamentos registrados.</p><?php endif; ?>
            <?php if ($a['international_transfer']): ?>
            <p class="section-gap-sm"><strong>Transferência internacional:</strong> <?= $text($a['international_details']) ?></p>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Retenção e segurança</h2>
            <div class="detail-grid section-gap-sm">
                <div class="detail"><span class="label">Prazo de retenção</span><div class="value"><?= $text($a['retention_period']) ?></div></div>
                <div class="detail"><span class="label">Descarte</span><div class="value"><?= $text($a['disposal_method']) ?></div></div>
                <div class="detail full"><span class="label">Medidas de segurança</span><div class="value"><?= $text($a['security_measures']) ?></div></div>
            </div>
        </section>

        <?php if (can(P::RISKS_VIEW)): ?>
        <section class="card">
            <div class="card-header">
                <div><h2>Riscos desta operação</h2></div>
                <?php if (can(P::RISKS_EDIT)): ?><a class="btn btn-ghost btn-sm" href="/riscos/novo?operacao=<?= (int) $a['id'] ?>"><?= icon('plus') ?> Novo risco</a><?php endif; ?>
            </div>
            <?php if (!$risks): ?><p class="empty">Nenhum risco registrado para esta operação.</p><?php else: ?>
            <div class="table-wrap">
                <table class="table-stack">
                    <thead><tr><th>Código</th><th>Risco</th><th>Nível</th><th>Situação</th></tr></thead>
                    <tbody>
                    <?php foreach ($risks as $r): ?>
                    <tr>
                        <td data-label="Código"><span class="code"><?= e(code('RSK', (int) $r['seq'])) ?></span></td>
                        <td data-label="Risco"><a class="title-link" href="/riscos/<?= (int) $r['id'] ?>"><?= e($r['title']) ?></a></td>
                        <td data-label="Nível"><?= risk_badge((int) $r['likelihood'], (int) $r['impact']) ?></td>
                        <td data-label="Situação"><?= e(label(Labels::RISK_STATUS, $r['status'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?= Isis\Core\View::partial('partials/evidences', ['entityType' => 'activity', 'entityId' => (int) $a['id'], 'evidences' => $evidences, 'canUpload' => $canEdit]) ?>
    </div>

    <div class="stack">
        <section class="card">
            <h2>Pontos de atenção</h2>
            <?php if (!$issues): ?>
            <p class="text-ok small section-gap-sm"><?= icon('check') ?> Registro completo nos campos principais.</p>
            <?php else: ?>
            <ul class="issues section-gap-sm">
                <?php foreach ($issues as $issue): ?><li><?= icon('alert') ?><span><?= e($issue) ?></span></li><?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <p class="hint section-gap-sm">Orientações automáticas de preenchimento; não substituem a análise jurídica.</p>
        </section>

        <?php if ($dpias !== null && ($dpias || $dpiaTriggers)): ?>
        <section class="card">
            <h2>Relatório de impacto (RIPD)</h2>
            <?php if ($dpias): ?>
            <ul class="bar-list section-gap-sm">
                <?php foreach ($dpias as $dp): ?>
                <li><a class="title-link" href="/ripd/<?= (int) $dp['id'] ?>"><?= e(code('RIPD', (int) $dp['seq']) . ' · ' . $dp['title']) ?></a>
                    <span class="badge badge-sm <?= $dp['approved_version'] !== null ? 'badge-ok' : 'badge-warn' ?>"><?= $dp['approved_version'] !== null ? 'aprovado v' . (int) $dp['approved_version'] : 'em elaboração' ?></span></li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="small section-gap-sm">Esta operação tem indicadores de alto risco: <?= e(implode('; ', array_map(static fn (string $t): string => mb_strtolower(Labels::DPIA_TRIGGER[$t]), $dpiaTriggers))) ?>. Avalie a elaboração do RIPD.</p>
            <?php if (can(P::DPIA_EDIT)): ?><a class="btn btn-ghost btn-sm section-gap-sm" href="/ripd/novo?operacao=<?= (int) $a['id'] ?>"><?= icon('plus') ?> Elaborar RIPD</a><?php endif; ?>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="card">
            <h2>Responsabilidade</h2>
            <dl class="dl section-gap-sm">
                <dt>Setor</dt><dd><?= e($a['department_name'] ?? '—') ?></dd>
                <dt>Responsável</dt><dd><?= e($a['owner_name'] ?? '—') ?></dd>
                <dt>Próxima revisão</dt><dd><?= e(fmt_date($a['review_due_date'])) ?></dd>
                <dt>Criada em</dt><dd><?= e(fmt_datetime($a['created_at'])) ?></dd>
            </dl>
        </section>

        <?php if ($actions): ?>
        <section class="card">
            <h2>Ações vinculadas</h2>
            <ul class="bar-list section-gap-sm">
                <?php foreach ($actions as $x): ?>
                <li><a class="title-link" href="/planos/<?= (int) $x['id'] ?>"><?= e(code('ACT', (int) $x['seq']) . ' · ' . $x['title']) ?></a> <span class="badge badge-sm"><?= e(label(Labels::ACTION_STATUS, $x['status'])) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php if ($canEdit): ?>
        <form method="post" action="/inventario/<?= (int) $a['id'] ?>/excluir" data-confirm="Excluir esta operação? Riscos e ações vinculados serão mantidos, sem o vínculo. Para preservar o histórico, prefira marcar como descontinuada.">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-block"><?= icon('trash') ?> Excluir operação</button>
        </form>
        <?php endif; ?>
    </div>
</div>
