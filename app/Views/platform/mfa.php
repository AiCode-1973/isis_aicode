<h1>Verificação em duas etapas</h1>
<p class="lead">Abra o aplicativo autenticador no celular e digite o código de 6 dígitos do ÍSIS Plataforma.</p>

<form method="post" action="/plataforma/verificacao" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="f-code">Código</label>
        <input type="text" id="f-code" name="code" required maxlength="20" inputmode="numeric" autocomplete="one-time-code" autofocus class="code-input" placeholder="000000">
        <p class="hint">Sem o celular? Digite um dos seus códigos de recuperação (formato abcde-fghij).</p>
    </div>
    <button type="submit" class="btn btn-primary btn-block" data-loading="Conferindo...">Confirmar</button>
</form>
<div class="auth-links">
    <a href="/plataforma/entrar">Voltar ao login</a>
    <span>Perdeu o celular e os códigos? No servidor, rode <span class="mono">php bin/isis plataforma:2fa-redefinir seu@email</span>.</span>
</div>
