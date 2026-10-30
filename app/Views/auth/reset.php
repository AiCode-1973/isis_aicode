<h1>Criar nova senha</h1>
<p class="lead">Ao salvar, todas as sessões abertas da sua conta serão encerradas.</p>

<form method="post" action="/redefinir-senha/<?= e($token) ?>" novalidate>
    <?= csrf_field() ?>
    <?= Isis\Core\View::partial('partials/password', ['name' => 'password', 'label' => 'Nova senha', 'hint' => 'Mínimo de 10 caracteres, com maiúsculas, minúsculas e números.']) ?>
    <?= Isis\Core\View::partial('partials/password', ['name' => 'password_confirmation', 'label' => 'Confirme a nova senha']) ?>
    <button type="submit" class="btn btn-primary btn-block" data-loading="Salvando...">Salvar nova senha</button>
</form>
