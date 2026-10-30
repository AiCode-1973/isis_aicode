<div class="page-head">
    <div>
        <h1>Configurações da plataforma</h1>
        <p>Instruções de pagamento mostradas às empresas e contato de cobrança.</p>
    </div>
</div>

<div class="grid grid-2 wide-split">
    <form method="post" action="/plataforma/configuracoes" class="card" novalidate>
        <?= csrf_field() ?>
        <h2>Cobrança</h2>
        <div class="field section-gap-sm">
            <label for="f-instructions">Instruções de pagamento</label>
            <textarea id="f-instructions" name="payment_instructions" rows="6" maxlength="2000"<?= error_attrs('payment_instructions') ?>><?= e(old('payment_instructions', (string) $instructions)) ?></textarea>
            <p class="hint">Aparece para o administrador da empresa em Plano e assinatura. Ex.: chave Pix, dados bancários, prazo para enviar o comprovante.</p>
            <?= field_error('payment_instructions') ?>
        </div>
        <div class="field">
            <label for="f-billing_email">E-mail de cobrança</label>
            <input type="email" id="f-billing_email" name="billing_email" value="<?= e(old('billing_email', (string) $billingEmail)) ?>" maxlength="190"<?= error_attrs('billing_email') ?>>
            <p class="hint">Recebe os pedidos de mudança de plano (além dos administradores da plataforma) e aparece para as empresas.</p>
            <?= field_error('billing_email') ?>
        </div>
        <div class="actions section-gap-sm"><button type="submit" class="btn btn-primary">Salvar</button></div>
    </form>

    <div class="stack">
        <form method="post" action="/plataforma/senha" class="card" id="senha" novalidate>
            <?= csrf_field() ?>
            <h2>Alterar minha senha</h2>
            <div class="section-gap-sm">
                <?= Isis\Core\View::partial('partials/password', ['name' => 'current_password', 'label' => 'Senha atual', 'autocomplete' => 'current-password']) ?>
                <?= Isis\Core\View::partial('partials/password', ['name' => 'password', 'label' => 'Nova senha', 'autocomplete' => 'new-password']) ?>
                <?= Isis\Core\View::partial('partials/password', ['name' => 'password_confirmation', 'label' => 'Confirme a nova senha', 'autocomplete' => 'new-password']) ?>
            </div>
            <div class="actions"><button type="submit" class="btn btn-primary">Alterar senha</button></div>
        </form>

        <section class="card" aria-label="Administradores da plataforma">
            <h2>Administradores da plataforma</h2>
            <ul class="stack section-gap-sm">
                <?php foreach ($admins as $a): ?>
                <li><strong><?= e($a['name']) ?></strong> · <?= e($a['email']) ?> <span class="muted small">· último acesso <?= e(fmt_datetime($a['last_login_at'])) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <p class="hint section-gap-sm">Para incluir alguém ou trocar uma senha esquecida, rode no servidor: <span class="mono">php bin/isis plataforma:admin email@aicode.dev.br "Nome"</span></p>
        </section>
    </div>
</div>
