<h1>Confirme seu e-mail</h1>
<p class="lead">
    Enviamos um link de confirmação<?= $email ? ' para <strong>' . e($email) . '</strong>' : '' ?>.
    Abra-o para ativar o acesso. O link vale por 48 horas.
</p>

<?php if ($mailLog): ?>
<div class="alert alert-info"><?= icon('info') ?><p>Ambiente de desenvolvimento: os e-mails estão sendo gravados em <span class="mono">storage/logs/mail.log</span>.</p></div>
<?php endif; ?>

<hr class="divider">
<h2>Não recebeu?</h2>
<p class="muted small">Verifique a caixa de spam. Se precisar, peça um novo link.</p>
<form method="post" action="/cadastro/reenviar" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="f-email">E-mail cadastrado</label>
        <input type="email" id="f-email" name="email" value="<?= e($email ?? '') ?>" required maxlength="190" autocomplete="email">
    </div>
    <button type="submit" class="btn btn-ghost btn-block">Reenviar link de confirmação</button>
</form>
<div class="auth-links"><a href="/entrar">Ir para o login</a></div>
