<div class="error-page">
    <p class="error-code"><?= (int) $status ?></p>
    <h1><?= e($title) ?></h1>
    <p class="muted"><?= e($message) ?></p>
    <div class="actions actions-center">
        <a class="btn btn-primary" href="/"><?= icon('arrow-right') ?> Voltar ao início</a>
    </div>
</div>
