<?php

use Isis\Auth\Permission as P;
use Isis\Support\Labels;

$statusBadge = ['rascunho' => 'badge', 'em_aprovacao' => 'badge-warn', 'aprovada' => 'badge-ok', 'rejeitada' => 'badge-danger', 'substituida' => 'badge'];
$expired = $p['valid_until'] && $p['valid_until'] < date('Y-m-d');
$ackDone = count(array_filter($acks, static fn (array $a): bool => $a['acknowledged_at'] !== null));
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/politicas">Políticas e documentos</a> · <?= e(code('POL', (int) $p['seq'])) ?></p>
        <h1><?= e($p['title']) ?></h1>
        <p>
            <?= $current ? '<span class="badge badge-ok">Vigente · v' . (int) $current['version_number'] . '</span>' : '<span class="badge badge-warn">Ainda sem versão aprovada</span>' ?>
            <?php if ($current): ?><span class="badge <?= $expired ? 'badge-danger' : '' ?>">Válida até <?= e(fmt_date($current['valid_until'])) ?></span><?php endif; ?>
            · <?= e(label(Labels::POLICY_CATEGORY, $p['category'])) ?>
        </p>
    </div>
    <?php if (can(P::POLICIES_EDIT) && !$pending): ?>
    <a class="btn btn-primary" href="/politicas/<?= (int) $p['id'] ?>/editar"><?= $draft ? 'Editar rascunho' : 'Criar nova versão' ?></a>
    <?php endif; ?>
</div>

<?php if ($current && $p['requires_ack']): ?>
<section class="card ack-box<?= $myAck ? ' ok' : '' ?>">
    <?php if ($myAck): ?>
    <p><?= icon('check') ?> Você registrou ciência desta versão em <?= e(fmt_datetime($myAck)) ?>.</p>
    <?php else: ?>
    <form method="post" action="/politicas/<?= (int) $p['id'] ?>/ciencia" class="actions">
        <?= csrf_field() ?>
        <p class="grow"><strong>Leitura obrigatória.</strong> Leia o documento abaixo e confirme que está ciente.</p>
        <button type="submit" class="btn btn-teal"><?= icon('check') ?> Li e estou ciente</button>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<div class="grid grid-main section-gap">
    <div class="stack">
        <?php if ($pending && can(P::POLICIES_APPROVE)): ?>
        <section class="card approval-box">
            <h2>Versão <?= (int) $pending['version_number'] ?> aguardando aprovação</h2>
            <p class="small muted">Enviada em <?= e(fmt_datetime($pending['submitted_at'])) ?><?= $pending['change_summary'] ? ' · ' . e($pending['change_summary']) : '' ?></p>
            <div class="policy-text section-gap-sm"><?= e($pending['content']) ?></div>
            <div class="grid grid-2 section-gap">
                <form method="post" action="/politicas/<?= (int) $p['id'] ?>/aprovar" novalidate>
                    <?= csrf_field() ?>
                    <div class="field"><label for="ap-notes">Observação (opcional)</label><input type="text" id="ap-notes" name="notes" maxlength="500"></div>
                    <button type="submit" class="btn btn-teal btn-block"><?= icon('check') ?> Aprovar e publicar</button>
                </form>
                <form method="post" action="/politicas/<?= (int) $p['id'] ?>/devolver" novalidate>
                    <?= csrf_field() ?>
                    <div class="field"><label for="rj-notes">Motivo da devolução</label><input type="text" id="rj-notes" name="notes" maxlength="500" required></div>
                    <button type="submit" class="btn btn-danger btn-block">Devolver para ajustes</button>
                </form>
            </div>
        </section>
        <?php elseif ($pending): ?>
        <div class="alert alert-info"><?= icon('clock') ?><p>A versão <?= (int) $pending['version_number'] ?> está aguardando aprovação do encarregado ou do administrador.</p></div>
        <?php endif; ?>

        <?php if ($draft && can(P::POLICIES_EDIT)): ?>
        <section class="card">
            <div class="card-header">
                <div>
                    <h2>Rascunho da versão <?= (int) $draft['version_number'] ?></h2>
                    <p><?= $draft['status'] === 'rejeitada' ? '<span class="text-danger">Devolvida: ' . e($draft['decision_notes']) . '</span>' : 'Ainda não enviado para aprovação.' ?></p>
                </div>
                <form method="post" action="/politicas/<?= (int) $p['id'] ?>/enviar" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-primary">Enviar para aprovação</button></form>
            </div>
            <details class="changes"><summary>Ver texto do rascunho</summary><div class="policy-text"><?= e($draft['content']) ?></div></details>
        </section>
        <?php endif; ?>

        <section class="card">
            <h2><?= $current ? 'Texto vigente' : 'Texto' ?></h2>
            <?php if ($current): ?>
            <div class="policy-text section-gap-sm"><?= e($current['content']) ?></div>
            <?php else: ?>
            <p class="muted section-gap-sm">Este documento ainda não tem versão aprovada.</p>
            <?php endif; ?>
        </section>

        <?= Isis\Core\View::partial('partials/evidences', ['entityType' => 'policy', 'entityId' => (int) $p['id'], 'evidences' => $evidences, 'canUpload' => can(P::POLICIES_EDIT)]) ?>
    </div>

    <div class="stack">
        <section class="card">
            <h2>Informações</h2>
            <dl class="dl section-gap-sm">
                <dt>Responsável</dt><dd><?= e($p['owner_name'] ?? '—') ?></dd>
                <dt>Aprovada em</dt><dd><?= e(fmt_date($p['approved_at'])) ?></dd>
                <dt>Revisão</dt><dd>a cada <?= (int) $p['review_months'] ?> meses</dd>
                <dt>Ciência</dt><dd><?= $p['requires_ack'] ? 'obrigatória' : 'não exigida' ?></dd>
            </dl>
        </section>

        <?php if ($editor && $current && $p['requires_ack']): ?>
        <section class="card">
            <h2>Ciência dos usuários</h2>
            <p class="small muted"><?= $ackDone ?> de <?= count($acks) ?> usuários ativos.</p>
            <div class="bar section-gap-sm"><span class="<?= width_class(count($acks) ? $ackDone / count($acks) * 100 : 0) ?>"></span></div>
            <ul class="ack-list section-gap-sm">
                <?php foreach ($acks as $a): ?>
                <li><span><?= e($a['name']) ?><?= $a['department_name'] ? ' <span class="muted small">· ' . e($a['department_name']) . '</span>' : '' ?></span><?= $a['acknowledged_at'] ? '<span class="badge badge-ok badge-sm">' . e(fmt_date($a['acknowledged_at'])) . '</span>' : '<span class="badge badge-warn badge-sm">pendente</span>' ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php if ($versions): ?>
        <section class="card">
            <h2>Versões</h2>
            <ul class="timeline section-gap-sm">
                <?php foreach ($versions as $ver): ?>
                <li>
                    <div class="meta">v<?= (int) $ver['version_number'] ?> · <?= e(fmt_date($ver['created_at'])) ?> · <?= e($ver['author_name'] ?? '—') ?></div>
                    <div><span class="badge badge-sm <?= $statusBadge[$ver['status']] ?>"><?= e(Labels::POLICY_VERSION_STATUS[$ver['status']]) ?></span><?= $ver['decider_name'] ? ' <span class="small muted">por ' . e($ver['decider_name']) . ' em ' . e(fmt_date($ver['decided_at'])) . '</span>' : '' ?></div>
                    <?php if ($ver['change_summary']): ?><div class="note"><?= e($ver['change_summary']) ?></div><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php if (can(P::POLICIES_EDIT)): ?>
        <form method="post" action="/politicas/<?= (int) $p['id'] ?>/arquivar" data-confirm="Arquivar este documento? Ele sai da lista, mas o histórico e as ciências continuam guardados.">
            <?= csrf_field() ?><button type="submit" class="btn btn-danger btn-block">Arquivar documento</button>
        </form>
        <?php endif; ?>
    </div>
</div>
