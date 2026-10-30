<h1>Painel da plataforma</h1>
<p class="lead">Acesso restrito à equipe AiCode. As contas das empresas não entram aqui.</p>

<form method="post" action="/plataforma/entrar" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="f-email">E-mail</label>
        <input type="email" id="f-email" name="email" value="<?= e(old('email')) ?>" required autocomplete="username" maxlength="190" autofocus>
    </div>
    <?= Isis\Core\View::partial('partials/password', ['name' => 'password', 'label' => 'Senha', 'autocomplete' => 'current-password']) ?>
    <button type="submit" class="btn btn-primary btn-block" data-loading="Entrando...">Entrar</button>
</form>

<div class="auth-links">
    <span>Conta criada pelo comando <span class="mono">php bin/isis plataforma:admin</span> no servidor.</span>
</div>
