<h1>Entrar no ISIS</h1>
<p class="lead">Acesse o ambiente da sua empresa.</p>

<form method="post" action="/entrar" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="f-email">E-mail</label>
        <input type="email" id="f-email" name="email" value="<?= e(old('email')) ?>" required autocomplete="username" maxlength="190" autofocus<?= error_attrs('email') ?>>
        <?= field_error('email') ?>
    </div>
    <?= Isis\Core\View::partial('partials/password', ['name' => 'password', 'label' => 'Senha', 'autocomplete' => 'current-password']) ?>
    <button type="submit" class="btn btn-primary btn-block" data-loading="Entrando...">Entrar</button>
</form>

<div class="auth-links">
    <a href="/esqueci-senha">Esqueci minha senha</a>
    <span>Nova empresa? <a href="/cadastro">Criar conta</a></span>
</div>
