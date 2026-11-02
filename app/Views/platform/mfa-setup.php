<h1>Ative a verificação em duas etapas</h1>
<p class="lead">O painel da plataforma dá acesso a todas as empresas, por isso exige um código do celular além da senha. Faça isso uma vez só.</p>

<ol class="mfa-steps">
    <li>Instale um aplicativo autenticador no celular, se ainda não tiver: <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong> ou outro de sua preferência.</li>
    <li>No aplicativo, toque em adicionar e <strong>leia o QR code</strong>. Se não conseguir, escolha digitar a chave.
        <div class="mfa-setup">
            <div class="qr" role="img" aria-label="QR code para cadastrar o ÍSIS Plataforma no aplicativo autenticador"><?= $qr ?></div>
            <div>
                <p class="small muted">Chave para digitar</p>
                <p class="mono mfa-secret"><?= e($secret) ?></p>
                <p class="small muted">Tipo: baseada em tempo · 6 dígitos</p>
                <p class="small"><a href="<?= e($uri) ?>">Abrir no aplicativo</a> (no próprio celular)</p>
            </div>
        </div>
    </li>
    <li>Digite o código de 6 dígitos que o aplicativo mostrar:
        <form method="post" action="/plataforma/verificacao/ativar" class="actions section-gap-sm" novalidate>
            <?= csrf_field() ?>
            <label for="f-code" class="visually-hidden">Código do aplicativo</label>
            <input type="text" id="f-code" name="code" required maxlength="7" inputmode="numeric" autocomplete="one-time-code" class="code-input" placeholder="000000">
            <button type="submit" class="btn btn-primary" data-loading="Ativando..."><?= icon('shield') ?> Ativar e entrar</button>
        </form>
    </li>
</ol>
<div class="auth-links">
    <a href="/plataforma/entrar">Voltar ao login</a>
    <span>Você tem 10 minutos para concluir. Depois, entre de novo com a senha.</span>
</div>
