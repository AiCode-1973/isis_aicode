<?php $company = $tenant['trade_name'] ?: $tenant['legal_name']; ?>
<div class="page-head no-print">
    <div>
        <p class="eyebrow"><a href="/treinamentos">Treinamentos</a></p>
        <h1>Certificado</h1>
    </div>
    <button type="button" class="btn btn-primary" data-print><?= icon('download') ?> Imprimir ou salvar em PDF</button>
</div>

<article class="certificate">
    <div class="certificate-inner">
        <img src="<?= e(asset('img/logo.svg')) ?>" alt="" width="64" height="64">
        <p class="eyebrow">Certificado de conclusão</p>
        <h1><?= e($c['module_title']) ?></h1>
        <p class="certificate-text">Certificamos que</p>
        <p class="certificate-name"><?= e($c['user_name']) ?></p>
        <p class="certificate-text">concluiu o treinamento acima, oferecido por <strong><?= e($company) ?></strong>, com carga horária estimada de <?= (int) $c['duration_minutes'] ?> minutos e aproveitamento de <strong><?= (int) $c['best_score'] ?>%</strong>.</p>
        <div class="certificate-meta">
            <div><span class="label">Concluído em</span><strong><?= e(fmt_date($c['completed_at'])) ?></strong></div>
            <div><span class="label">Válido até</span><strong><?= e(fmt_date($c['certificate_valid_until'])) ?></strong></div>
            <div><span class="label">Código</span><strong class="mono"><?= e($c['certificate_code']) ?></strong></div>
        </div>
        <p class="small muted">Verifique a autenticidade em <?= e($verifyUrl) ?></p>
        <p class="small muted">Emitido pelo ÍSIS · Gestão da Privacidade e Proteção de Dados</p>
    </div>
</article>
