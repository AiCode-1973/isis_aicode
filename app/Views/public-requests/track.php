<?php

use Isis\Support\Labels;

$base = '/titular/' . $tenant['slug'];
?>
<section class="section">
    <div class="container public-form">
        <div class="public-form-head">
            <p class="eyebrow">Canal de privacidade · <?= e($tenant['trade_name'] ?: $tenant['legal_name']) ?></p>
            <h1>Acompanhar solicitação</h1>
            <p class="muted">Informe o protocolo e o e-mail usados no pedido.</p>
        </div>

        <form method="post" action="<?= e($base . '/acompanhar') ?>" class="card form-grid" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="f-protocol">Protocolo</label>
                <input type="text" id="f-protocol" name="protocol" value="<?= e($protocol) ?>" required maxlength="9" placeholder="XXXX-XXXX" class="mono" autocomplete="off">
            </div>
            <div class="field">
                <label for="f-email">E-mail</label>
                <input type="email" id="f-email" name="email" required maxlength="190" autocomplete="email">
            </div>
            <div class="full"><button type="submit" class="btn btn-primary">Consultar</button></div>
        </form>

        <?php if ($error): ?>
        <div class="alert alert-error section-gap" role="alert"><?= icon('alert') ?><p><?= e($error) ?></p></div>
        <?php endif; ?>

        <?php if ($result): $r = $result['request']; ?>
        <section class="card section-gap" aria-label="Situação da solicitação">
            <div class="card-header">
                <div><h2>Protocolo <?= e($r['protocol']) ?></h2><p><?= e(label(Labels::RIGHT_TYPE, $r['right_type'])) ?></p></div>
                <span class="badge badge-brand"><?= e(label(Labels::REQUEST_STATUS, $r['status'])) ?></span>
            </div>
            <dl class="dl">
                <dt>Recebida em</dt><dd><?= e(fmt_datetime($r['received_at'])) ?></dd>
                <dt>Prazo de referência</dt><dd><?= e(fmt_date($r['due_date'])) ?></dd>
                <?php if ($r['responded_at']): ?><dt>Respondida em</dt><dd><?= e(fmt_datetime($r['responded_at'])) ?></dd><?php endif; ?>
            </dl>
            <?php if ($r['response']): ?>
            <h3 class="section-gap">Resposta</h3>
            <div class="response-box"><?= e($r['response']) ?></div>
            <?php endif; ?>
            <?php if ($result['events']): ?>
            <h3 class="section-gap">Andamento</h3>
            <ul class="timeline section-gap-sm">
                <?php foreach ($result['events'] as $ev): if ($ev['event_type'] === 'resposta') continue; ?>
                <li>
                    <div class="meta"><?= e(fmt_datetime($ev['created_at'])) ?><?= $ev['status_to'] && $ev['status_from'] !== $ev['status_to'] ? ' · ' . e(label(Labels::REQUEST_STATUS, $ev['status_to'])) : '' ?></div>
                    <?php if ($ev['note']): ?><div class="note"><?= e($ev['note']) ?></div><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <p class="small section-gap"><a href="<?= e($base) ?>">Fazer uma nova solicitação</a></p>
    </div>
</section>
