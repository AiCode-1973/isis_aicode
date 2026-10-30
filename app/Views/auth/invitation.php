<h1>Você foi convidado</h1>
<p class="lead">
    <strong><?= e($invitation['trade_name'] ?: $invitation['legal_name']) ?></strong> convidou você para o ISIS
    com o perfil <span class="badge badge-brand"><?= e($roleLabel) ?></span>.
</p>

<form method="post" action="/convite/<?= e($token) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="f-email">E-mail</label>
        <input type="email" id="f-email" value="<?= e($invitation['email']) ?>" readonly autocomplete="username">
    </div>
    <div class="field">
        <label for="f-name">Seu nome completo</label>
        <input type="text" id="f-name" name="name" value="<?= e(old('name', $invitation['name'])) ?>" required maxlength="150" autocomplete="name"<?= error_attrs('name') ?>>
        <?= field_error('name') ?>
    </div>
    <?= Isis\Core\View::partial('partials/password', ['name' => 'password', 'label' => 'Crie uma senha', 'hint' => 'Mínimo de 10 caracteres, com maiúsculas, minúsculas e números.']) ?>
    <?= Isis\Core\View::partial('partials/password', ['name' => 'password_confirmation', 'label' => 'Confirme a senha']) ?>
    <div class="field">
        <label class="check">
            <input type="checkbox" name="accept_terms" value="1"<?= error_attrs('accept_terms') ?>>
            <span>Li os <a href="/termos" target="_blank" rel="noopener">termos de uso e o aviso de privacidade</a>.</span>
        </label>
        <?= field_error('accept_terms') ?>
    </div>
    <button type="submit" class="btn btn-primary btn-block" data-loading="Criando acesso...">Aceitar convite e entrar</button>
</form>
