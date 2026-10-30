<?php $base = '/titular/' . $tenant['slug']; ?>
<section class="section">
    <div class="container public-form">
        <div class="card text-center">
            <div class="kpi-icon teal center-icon"><?= icon('check', 'icon-lg') ?></div>
            <h1>Solicitação enviada</h1>
            <?php if ($protocol): ?>
            <p>Guarde o número do seu protocolo:</p>
            <p class="protocol"><?= e($protocol) ?></p>
            <p class="muted">Também enviamos o protocolo para o seu e-mail. A empresa <?= e($tenant['trade_name'] ?: $tenant['legal_name']) ?> poderá pedir a confirmação da sua identidade antes de responder.</p>
            <?php else: ?>
            <p class="muted">Se o envio foi concluído, o protocolo chegará ao seu e-mail.</p>
            <?php endif; ?>
            <div class="actions actions-center section-gap">
                <a class="btn btn-primary" href="<?= e($base . '/acompanhar') ?>">Acompanhar solicitação</a>
                <a class="btn btn-ghost" href="<?= e($base) ?>">Fazer outro pedido</a>
            </div>
        </div>
    </div>
</section>
