<!doctype html>
<html lang="pt-BR">
<head>
<?= Isis\Core\View::partial('partials/head', ['title' => $title ?? '']) ?>
</head>
<body>
<div class="wizard">
    <div class="wizard-head">
        <?= Isis\Core\View::partial('partials/brand') ?>
    </div>
    <?= Isis\Core\View::partial('partials/flash') ?>
    <?= $content ?>
    <p class="small muted"><?= Isis\Core\View::partial('partials/disclaimer') ?></p>
</div>
</body>
</html>
