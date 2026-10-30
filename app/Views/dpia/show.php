<?php

use Isis\Auth\Permission as P;
use Isis\Support\Labels;

$statusBadge = ['rascunho' => 'badge', 'em_aprovacao' => 'badge-warn', 'aprovado' => 'badge-ok'];
$text = static fn (?string $v): string => $v !== null && trim($v) !== '' ? '<div class="pre-line">' . e($v) . '</div>' : '<span class="muted">Não preenchido.</span>';
$isDraft = $d['status'] === 'rascunho';
$reviewOverdue = $d['status'] === 'aprovado' && $d['review_due_date'] && $d['review_due_date'] < date('Y-m-d');
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/ripd">Relatórios de impacto</a> · <?= e(code('RIPD', (int) $d['seq'])) ?></p>
        <h1><?= e($d['title']) ?></h1>
        <p>
            <span class="badge <?= $statusBadge[$d['status']] ?>">Versão <?= (int) $d['version'] ?> · <?= e(mb_strtolower(Labels::DPIA_STATUS[$d['status']])) ?></span>
            <?php if ($d['approved_version'] !== null && $d['status'] !== 'aprovado'): ?><span class="badge badge-ok">Vigente: versão <?= (int) $d['approved_version'] ?></span><?php endif; ?>
            <?php if ($d['conclusion']): ?> · <?= e(Labels::DPIA_CONCLUSION[$d['conclusion']]) ?><?php endif; ?>
        </p>
    </div>
    <div class="actions">
        <a class="btn btn-ghost" href="/ripd/<?= (int) $d['id'] ?>/documento"><?= icon('eye') ?> <?= $d['status'] === 'aprovado' ? 'Documento aprovado' : 'Ver documento' ?></a>
        <?php if ($isDraft && can(P::DPIA_EDIT)): ?><a class="btn btn-primary" href="/ripd/<?= (int) $d['id'] ?>/editar">Editar</a><?php endif; ?>
        <?php if ($d['status'] === 'aprovado' && can(P::DPIA_EDIT)): ?>
        <form method="post" action="/ripd/<?= (int) $d['id'] ?>/revisar" class="inline-form" data-confirm="Iniciar a versão <?= (int) $d['version'] + 1 ?>? A versão aprovada continua guardada e vale até a nova ser aprovada.">
            <?= csrf_field() ?><button type="submit" class="btn btn-primary"><?= icon('refresh') ?> Iniciar revisão</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($reviewOverdue): ?>
<div class="alert alert-warn"><?= icon('clock') ?><p>A revisão prevista para <?= e(fmt_date($d['review_due_date'])) ?> está vencida. Confira se o tratamento mudou e inicie uma nova revisão.</p></div>
<?php endif; ?>

<div class="grid grid-main section-gap">
    <div class="stack">
        <?php if ($d['status'] === 'em_aprovacao' && can(P::DPIA_APPROVE)): ?>
        <section class="card approval-box">
            <h2>Versão <?= (int) $d['version'] ?> aguardando aprovação</h2>
            <p class="small muted">Enviada em <?= e(fmt_datetime($d['submitted_at'])) ?>. Confira o <a href="/ripd/<?= (int) $d['id'] ?>/documento">documento completo</a> antes de decidir. Na aprovação, o conteúdo fica guardado como está.</p>
            <div class="grid grid-2 section-gap">
                <form method="post" action="/ripd/<?= (int) $d['id'] ?>/aprovar" novalidate>
                    <?= csrf_field() ?>
                    <div class="field"><label for="ap-notes">Observação (opcional)</label><input type="text" id="ap-notes" name="notes" maxlength="500"></div>
                    <button type="submit" class="btn btn-teal btn-block"><?= icon('check') ?> Aprovar RIPD</button>
                </form>
                <form method="post" action="/ripd/<?= (int) $d['id'] ?>/devolver" novalidate>
                    <?= csrf_field() ?>
                    <div class="field"><label for="rj-notes">Motivo da devolução</label><input type="text" id="rj-notes" name="notes" maxlength="500" required></div>
                    <button type="submit" class="btn btn-danger btn-block">Devolver para ajustes</button>
                </form>
            </div>
        </section>
        <?php elseif ($d['status'] === 'em_aprovacao'): ?>
        <div class="alert alert-info"><?= icon('clock') ?><p>Aguardando aprovação do encarregado ou do administrador.</p></div>
        <?php endif; ?>

        <?php if ($isDraft && can(P::DPIA_EDIT)): ?>
        <section class="card">
            <div class="card-header">
                <div>
                    <h2>Versão <?= (int) $d['version'] ?> em elaboração</h2>
                    <?php if ($d['returned_notes']): ?><p class="text-danger">Devolvido: <?= e($d['returned_notes']) ?></p><?php endif; ?>
                </div>
                <?php if (!$missing): ?>
                <form method="post" action="/ripd/<?= (int) $d['id'] ?>/enviar" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-primary">Enviar para aprovação</button></form>
                <?php endif; ?>
            </div>
            <?php if ($missing): ?>
            <p class="small section-gap-sm">Para enviar à aprovação, falta:</p>
            <ul class="issues section-gap-sm">
                <?php foreach ($missing as $item): ?><li><?= icon('alert') ?><span><?= e($item) ?></span></li><?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-ok small section-gap-sm"><?= icon('check') ?> Todas as seções obrigatórias estão preenchidas.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="card">
            <h2>Operações avaliadas</h2>
            <?php if (!$doc['activities']): ?>
            <p class="empty">Nenhuma operação vinculada.</p>
            <?php else: ?>
            <ul class="bar-list section-gap-sm">
                <?php foreach ($doc['activities'] as $a): ?>
                <li><a class="title-link" href="/inventario/<?= (int) $a['id'] ?>"><?= e($a['code'] . ' · ' . $a['name']) ?></a> <span class="small muted"><?= e($a['legal_basis'] ?? 'sem hipótese legal') ?></span></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <p class="small section-gap-sm"><strong>Situações de alto risco:</strong> <?= $doc['dpia']['triggers'] ? e(implode('; ', $doc['dpia']['triggers'])) : '<span class="muted">nenhuma marcada</span>' ?></p>
        </section>

        <section class="card">
            <h2>Descrição do tratamento</h2>
            <div class="detail-grid section-gap-sm">
                <div class="detail full"><span class="label">Natureza</span><div class="value"><?= $text($d['nature']) ?></div></div>
                <div class="detail full"><span class="label">Escopo</span><div class="value"><?= $text($d['scope']) ?></div></div>
                <div class="detail full"><span class="label">Contexto</span><div class="value"><?= $text($d['context']) ?></div></div>
                <div class="detail full"><span class="label">Finalidade</span><div class="value"><?= $text($d['purpose']) ?></div></div>
                <div class="detail full"><span class="label">Partes consultadas</span><div class="value"><?= $text($d['stakeholders']) ?></div></div>
                <div class="detail full"><span class="label">Necessidade e proporcionalidade</span><div class="value"><?= $text($d['necessity']) ?></div></div>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <div><h2>Riscos das operações</h2><p>Vêm do módulo Riscos e entram no relatório.</p></div>
                <?php if (can(P::RISKS_EDIT) && count($d['activity_ids']) === 1): ?><a class="btn btn-ghost btn-sm" href="/riscos/novo?operacao=<?= (int) $d['activity_ids'][0] ?>"><?= icon('plus') ?> Novo risco</a><?php endif; ?>
            </div>
            <?php if (!$doc['risks']): ?>
            <p class="empty">Nenhum risco registrado para as operações avaliadas. Um RIPD sem riscos identificados dificilmente se sustenta: registre ao menos os principais.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table-stack">
                    <thead><tr><th>Risco</th><th>Inerente</th><th>Residual</th><th>Tratamento</th><th>Ações</th></tr></thead>
                    <tbody>
                    <?php foreach ($doc['risks'] as $r): ?>
                    <tr>
                        <td data-label="Risco"><span class="code"><?= e($r['code']) ?></span> <?= e($r['title']) ?> <span class="small muted">· <?= e($r['activity']) ?></span></td>
                        <td data-label="Inerente"><?= risk_badge($r['likelihood'], $r['impact']) ?></td>
                        <td data-label="Residual"><?= $r['residual_likelihood'] !== null ? risk_badge($r['residual_likelihood'], $r['residual_impact']) : '<span class="muted">—</span>' ?></td>
                        <td data-label="Tratamento"><?= e($r['treatment']) ?></td>
                        <td data-label="Ações"><?= $r['actions_count'] > 0 ? $r['actions_done'] . '/' . $r['actions_count'] : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <div class="detail-grid section-gap">
                <div class="detail full"><span class="label">Medidas e salvaguardas gerais</span><div class="value"><?= $text($d['safeguards']) ?></div></div>
            </div>
        </section>

        <section class="card">
            <h2>Conclusão e parecer</h2>
            <div class="detail-grid section-gap-sm">
                <div class="detail full"><span class="label">Conclusão</span><div class="value"><?= e(Labels::of(Labels::DPIA_CONCLUSION, $d['conclusion'])) ?></div></div>
                <div class="detail full"><span class="label">Fundamentação</span><div class="value"><?= $text($d['conclusion_notes']) ?></div></div>
                <div class="detail full"><span class="label">Parecer do encarregado</span><div class="value"><?= $text($d['dpo_opinion']) ?></div></div>
            </div>
        </section>

        <?= Isis\Core\View::partial('partials/evidences', ['entityType' => 'dpia', 'entityId' => (int) $d['id'], 'evidences' => $evidences, 'canUpload' => can(P::DPIA_EDIT)]) ?>
    </div>

    <div class="stack">
        <section class="card">
            <h2>Informações</h2>
            <dl class="dl section-gap-sm">
                <dt>Responsável</dt><dd><?= e($d['owner_name'] ?? '—') ?></dd>
                <dt>Próxima revisão</dt><dd class="<?= $reviewOverdue ? 'text-danger' : '' ?>"><?= e(fmt_date($d['review_due_date'])) ?></dd>
                <dt>Criado em</dt><dd><?= e(fmt_datetime($d['created_at'])) ?></dd>
                <?php if ($d['status'] === 'aprovado'): ?>
                <dt>Aprovado por</dt><dd><?= e($d['approver_name'] ?? '—') ?></dd>
                <dt>Aprovado em</dt><dd><?= e(fmt_datetime($d['approved_at'])) ?></dd>
                <?php if ($d['approval_notes']): ?><dt>Observação</dt><dd><?= e($d['approval_notes']) ?></dd><?php endif; ?>
                <?php endif; ?>
            </dl>
        </section>

        <section class="card">
            <h2>Versões aprovadas</h2>
            <?php if (!$versions): ?>
            <p class="muted small section-gap-sm">Nenhuma versão aprovada ainda.</p>
            <?php else: ?>
            <ul class="bar-list section-gap-sm">
                <?php foreach ($versions as $v): ?>
                <li><a class="title-link" href="/ripd/<?= (int) $d['id'] ?>/versoes/<?= (int) $v['version'] ?>">Versão <?= (int) $v['version'] ?></a> <span class="small muted"><?= e(fmt_date($v['approved_at'])) ?> · <?= e($v['approved_by_name']) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <p class="hint section-gap-sm">Cada versão aprovada fica guardada exatamente como foi aprovada, com código de integridade.</p>
            <?php endif; ?>
        </section>

        <?php if (can(P::DPIA_EDIT) && $isDraft && $d['approved_version'] === null): ?>
        <form method="post" action="/ripd/<?= (int) $d['id'] ?>/excluir" data-confirm="Excluir este RIPD em elaboração? As evidências anexadas também serão excluídas.">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-block"><?= icon('trash') ?> Excluir RIPD</button>
        </form>
        <?php endif; ?>
    </div>
</div>
