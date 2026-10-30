<section class="section">
    <div class="container public-form">
        <div class="card text-center">
            <?php if ($cert): ?>
            <div class="kpi-icon teal center-icon"><?= icon('check', 'icon-lg') ?></div>
            <h1>Certificado válido</h1>
            <p class="protocol"><?= e($code) ?></p>
            <dl class="dl dl-center">
                <dt>Participante</dt><dd><?= e($cert['user_name']) ?></dd>
                <dt>Treinamento</dt><dd><?= e($cert['module_title']) ?></dd>
                <dt>Empresa</dt><dd><?= e($cert['company']) ?></dd>
                <dt>Concluído em</dt><dd><?= e(fmt_date($cert['completed_at'])) ?></dd>
                <dt>Validade</dt><dd><?= e(fmt_date($cert['certificate_valid_until'])) ?><?= $cert['certificate_valid_until'] < date('Y-m-d') ? ' <span class="badge badge-warn">expirado</span>' : '' ?></dd>
            </dl>
            <?php else: ?>
            <div class="kpi-icon warn center-icon"><?= icon('alert', 'icon-lg') ?></div>
            <h1>Certificado não encontrado</h1>
            <p class="muted">Confira o código informado no certificado (formato ISIS-XXXX-XXXX).</p>
            <?php endif; ?>
        </div>
    </div>
</section>
