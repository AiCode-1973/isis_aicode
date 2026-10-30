<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/perfil">Meu perfil</a></p>
        <h1>Verificação em duas etapas</h1>
        <p>Além da senha, o ÍSIS pede um código que muda a cada 30 segundos, gerado por um aplicativo no seu celular. Assim, quem descobrir a sua senha não consegue entrar.</p>
    </div>
</div>

<?php if ($codes): ?>
<section class="card recovery-box">
    <h2><?= icon('alert') ?> Guarde seus códigos de recuperação</h2>
    <p>Cada código funciona uma única vez, no lugar do código do aplicativo, se você perder ou trocar o celular. <strong>Eles aparecem só agora.</strong> Anote em papel ou guarde num gerenciador de senhas.</p>
    <ol class="recovery-codes">
        <?php foreach ($codes as $c): ?><li class="mono"><?= e($c) ?></li><?php endforeach; ?>
    </ol>
    <button type="button" class="btn btn-ghost no-print" data-print><?= icon('download') ?> Imprimir</button>
</section>
<?php endif; ?>

<div class="grid grid-main section-gap">
    <?php if (!$enabled): ?>
    <section class="card">
        <h2>Ativar</h2>
        <?php if ($required): ?><div class="alert alert-warn section-gap-sm"><?= icon('shield') ?><p>Sua empresa exige a verificação em duas etapas para o seu perfil. Ative para continuar usando o ÍSIS.</p></div><?php endif; ?>
        <ol class="mfa-steps section-gap-sm">
            <li>Instale um aplicativo autenticador no celular, se ainda não tiver: <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong> ou outro de sua preferência.</li>
            <li>No aplicativo, toque em adicionar e <strong>leia o QR code</strong> abaixo. Se não conseguir, escolha digitar a chave.
                <div class="mfa-setup">
                    <div class="qr" role="img" aria-label="QR code para cadastrar o ÍSIS no aplicativo autenticador"><?= $qr ?></div>
                    <div>
                        <p class="small muted">Chave para digitar</p>
                        <p class="mono mfa-secret"><?= e($secret) ?></p>
                        <p class="small muted">Tipo: baseada em tempo · 6 dígitos</p>
                        <p class="small"><a href="<?= e($uri) ?>">Abrir no aplicativo</a> (no próprio celular)</p>
                    </div>
                </div>
            </li>
            <li>Digite o código de 6 dígitos que o aplicativo mostrar:
                <form method="post" action="/perfil/dois-fatores/ativar" class="actions section-gap-sm" novalidate>
                    <?= csrf_field() ?>
                    <label for="f-code" class="visually-hidden">Código do aplicativo</label>
                    <input type="text" id="f-code" name="code" required maxlength="7" inputmode="numeric" autocomplete="one-time-code" class="code-input" placeholder="000000">
                    <button type="submit" class="btn btn-primary" data-loading="Ativando..."><?= icon('shield') ?> Ativar</button>
                </form>
            </li>
        </ol>
    </section>
    <?php else: ?>
    <section class="card">
        <h2><?= icon('check') ?> Ativada</h2>
        <dl class="dl section-gap-sm">
            <dt>Ativada em</dt><dd><?= e(fmt_datetime($me['mfa_enabled_at'])) ?></dd>
            <dt>Códigos de recuperação</dt><dd class="<?= $remaining <= 2 ? 'text-danger' : '' ?>"><?= (int) $remaining ?> disponível(is) de 10</dd>
        </dl>
        <p class="small section-gap-sm">A cada entrada, depois da senha, o ÍSIS pede o código do aplicativo.</p>

        <form method="post" action="/perfil/dois-fatores/codigos" class="section-gap" novalidate>
            <?= csrf_field() ?>
            <h3>Gerar novos códigos de recuperação</h3>
            <p class="small muted">Os códigos anteriores deixam de valer.</p>
            <?= Isis\Core\View::partial('partials/password', ['name' => 'password_codes', 'label' => 'Sua senha', 'autocomplete' => 'current-password']) ?>
            <button type="submit" class="btn btn-ghost"><?= icon('refresh') ?> Gerar novos códigos</button>
        </form>

        <?php if (!$required): ?>
        <form method="post" action="/perfil/dois-fatores/desativar" class="section-gap" novalidate data-confirm="Desativar a verificação em duas etapas? Sua conta ficará protegida só pela senha.">
            <?= csrf_field() ?>
            <h3>Desativar</h3>
            <?= Isis\Core\View::partial('partials/password', ['name' => 'password', 'label' => 'Sua senha', 'autocomplete' => 'current-password']) ?>
            <button type="submit" class="btn btn-danger">Desativar verificação em duas etapas</button>
        </form>
        <?php else: ?>
        <p class="hint section-gap">A política da sua empresa exige a verificação em duas etapas para o seu perfil, por isso ela não pode ser desativada.</p>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <aside class="card">
        <h2>Dicas</h2>
        <ul class="guidance small section-gap-sm">
            <li>Deixe o horário do celular no modo automático: o código depende da hora certa.</li>
            <li>Trocou de celular? Antes de apagar o antigo, transfira as contas do aplicativo ou use um código de recuperação para entrar e cadastrar de novo.</li>
            <li>Perdeu o celular e os códigos? O administrador da empresa pode redefinir a verificação em Usuários e perfis.</li>
            <li>A equipe do ÍSIS nunca pede o seu código.</li>
        </ul>
    </aside>
</div>
