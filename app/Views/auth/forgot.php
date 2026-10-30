<h1>Recuperar acesso</h1>
<p class="lead">Informe seu e-mail. Se ele estiver cadastrado, enviaremos um link de uso único, válido por 60 minutos, para criar uma nova senha.</p>

<form method="post" action="/esqueci-senha" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="f-email">E-mail</label>
        <input type="email" id="f-email" name="email" value="<?= e(old('email')) ?>" required maxlength="190" autocomplete="email" autofocus<?= error_attrs('email') ?>>
        <?= field_error('email') ?>
    </div>
    <button type="submit" class="btn btn-primary btn-block" data-loading="Enviando...">Enviar link</button>
</form>
<div class="auth-links"><a href="/entrar">Voltar ao login</a></div>
