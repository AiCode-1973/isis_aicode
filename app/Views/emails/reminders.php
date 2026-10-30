<p>Olá, <?= e($name) ?>.</p>
<p>Este é o resumo diário do que precisa da sua atenção na gestão de privacidade de <strong><?= e($company) ?></strong>:</p>
<?php foreach ($groups as $title => $items): ?>
<p style="margin:18px 0 6px;font-weight:bold;color:#1d3a6e;"><?= e($title) ?></p>
<ul style="margin:0;padding-left:20px;">
    <?php foreach ($items as $item): ?>
    <li style="margin-bottom:4px;"><a href="<?= e($item['link']) ?>" style="color:#0f1b2d;"><?= e($item['text']) ?></a><?php if ($item['due']): ?> <span style="color:<?= $item['late'] ? '#b4232f' : '#5d6b7f' ?>;">· <?= $item['late'] ? 'venceu em' : 'vence em' ?> <?= e(fmt_date($item['due'])) ?></span><?php endif; ?></li>
    <?php endforeach; ?>
</ul>
<?php endforeach; ?>
<p style="margin:24px 0;"><a href="<?= e($link) ?>" style="background:#1d3a6e;color:#ffffff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:bold;">Abrir o painel</a></p>
