<div class="page-head">
    <div>
        <h1>Configurações da plataforma</h1>
        <p>Instruções de pagamento, contato de cobrança e identificação da AiCode nos documentos legais.</p>
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

    <form method="post" action="/plataforma/documentos" class="card" id="documentos" novalidate>
        <?= csrf_field() ?>
        <h2>Documentos legais</h2>
        <p class="hint">Aparecem nos <a href="/termos" target="_blank" rel="noopener">Termos de uso</a>, no <a href="/privacidade" target="_blank" rel="noopener">Aviso de privacidade</a> e no <a href="/acordo-de-tratamento" target="_blank" rel="noopener">Acordo de tratamento de dados</a>. Enquanto estiverem vazios, as páginas mostram "<?= e(Isis\Support\LegalDocs::PENDING) ?>".</p>
        <?php foreach (['legal_name' => 190, 'legal_cnpj' => 20, 'legal_address' => 300, 'privacy_email' => 190, 'dpo_name' => 150] as $key => $max): ?>
        <div class="field">
            <label for="f-<?= e($key) ?>"><?= e(Isis\Support\LegalDocs::FIELDS[$key]) ?></label>
            <input type="<?= $key === 'privacy_email' ? 'email' : 'text' ?>" id="f-<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e(old($key, $legal[$key])) ?>" maxlength="<?= $max ?>"<?= error_attrs($key) ?>>
            <?= field_error($key) ?>
        </div>
        <?php endforeach; ?>
        <div class="field">
            <label for="f-subprocessors">Suboperadores</label>
            <textarea id="f-subprocessors" name="subprocessors" rows="4" maxlength="3000"<?= error_attrs('subprocessors') ?>><?= e(old('subprocessors', $legal['subprocessors'])) ?></textarea>
            <p class="hint">Um por linha: empresa, serviço e país. Ex.: "Provedor X · hospedagem do servidor e do banco · Brasil".</p>
            <?= field_error('subprocessors') ?>
        </div>
        <div class="actions section-gap-sm"><button type="submit" class="btn btn-primary">Salvar documentos</button></div>
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

        <section class="card" id="dois-fatores">
            <h2><?= icon('shield') ?> Verificação em duas etapas</h2>
            <?php if ($mfaCodes): ?>
            <div class="recovery-box section-gap-sm">
                <p><strong>Guarde seus códigos de recuperação.</strong> Cada um funciona uma única vez, no lugar do código do aplicativo, se você perder ou trocar o celular. Eles aparecem só agora.</p>
                <ol class="recovery-codes">
                    <?php foreach ($mfaCodes as $c): ?><li class="mono"><?= e($c) ?></li><?php endforeach; ?>
                </ol>
                <button type="button" class="btn btn-ghost no-print" data-print><?= icon('download') ?> Imprimir</button>
            </div>
            <?php endif; ?>
            <p class="section-gap-sm">Ativada e obrigatória para toda a equipe AiCode. Códigos de recuperação: <strong class="<?= $mfaRemaining <= 2 ? 'text-danger' : '' ?>"><?= plural((int) $mfaRemaining, 'disponível', 'disponíveis') ?> de 10</strong>.</p>
            <form method="post" action="/plataforma/dois-fatores/codigos" class="section-gap-sm" novalidate>
                <?= csrf_field() ?>
                <p class="small muted">Gerar novos códigos invalida os anteriores.</p>
                <?= Isis\Core\View::partial('partials/password', ['name' => 'password_codes', 'label' => 'Sua senha', 'autocomplete' => 'current-password']) ?>
                <button type="submit" class="btn btn-ghost"><?= icon('refresh') ?> Gerar novos códigos</button>
            </form>
            <p class="hint section-gap-sm">Perdeu o celular e os códigos? No servidor, rode <span class="mono">php bin/isis plataforma:2fa-redefinir email@aicode.dev.br</span>. Na próxima entrada, a pessoa cadastra o aplicativo de novo.</p>
        </section>

        <section class="card" aria-label="Administradores da plataforma">
            <h2>Administradores da plataforma</h2>
            <ul class="stack section-gap-sm">
                <?php foreach ($admins as $a): ?>
                <li><strong><?= e($a['name']) ?></strong> · <?= e($a['email']) ?> <span class="muted small">· último acesso <?= e(fmt_datetime($a['last_login_at'])) ?> · <?= empty($a['mfa_enabled_at']) ? 'duas etapas pendente (ativa na próxima entrada)' : 'duas etapas ativada' ?></span></li>
                <?php endforeach; ?>
            </ul>
            <p class="hint section-gap-sm">Para incluir alguém ou trocar uma senha esquecida, rode no servidor: <span class="mono">php bin/isis plataforma:admin email@aicode.dev.br "Nome"</span></p>
        </section>
    </div>
</div>
