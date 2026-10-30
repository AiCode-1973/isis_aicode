<h1>Criar conta da empresa</h1>
<p class="lead">Cada empresa tem seu próprio ambiente, usuários e dados, isolados das demais. Você será o administrador.</p>

<form method="post" action="/cadastro" novalidate>
    <?= csrf_field() ?>
    <div class="honeypot" aria-hidden="true">
        <label for="f-website">Não preencha</label>
        <input type="text" id="f-website" name="website" tabindex="-1" autocomplete="off">
    </div>

    <p class="section-title">Empresa</p>
    <div class="form-grid">
        <div class="field full">
            <label for="f-legal_name">Razão social</label>
            <input type="text" id="f-legal_name" name="legal_name" value="<?= e(old('legal_name')) ?>" required maxlength="190" autocomplete="organization"<?= error_attrs('legal_name') ?>>
            <?= field_error('legal_name') ?>
        </div>
        <div class="field">
            <label for="f-trade_name">Nome fantasia <span class="muted">(opcional)</span></label>
            <input type="text" id="f-trade_name" name="trade_name" value="<?= e(old('trade_name')) ?>" maxlength="190"<?= error_attrs('trade_name') ?>>
            <?= field_error('trade_name') ?>
        </div>
        <div class="field">
            <label for="f-cnpj">CNPJ</label>
            <input type="text" id="f-cnpj" name="cnpj" value="<?= e(old('cnpj')) ?>" required maxlength="18" inputmode="text" data-mask="cnpj" placeholder="00.000.000/0000-00"<?= error_attrs('cnpj') ?>>
            <p class="hint">Aceita o CNPJ numérico e o novo alfanumérico.</p>
            <?= field_error('cnpj') ?>
        </div>
        <div class="field full">
            <label for="f-segment_code">Segmento de atuação</label>
            <select id="f-segment_code" name="segment_code" required<?= error_attrs('segment_code') ?>>
                <option value="">Selecione...</option>
                <?php foreach ($segments as $code => $name): ?>
                <option value="<?= e($code) ?>"<?= old('segment_code') === $code ? ' selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint">Usado para sugerir setores, exemplos e orientações. Você pode mudar depois.</p>
            <?= field_error('segment_code') ?>
        </div>
    </div>

    <p class="section-title">Administrador da conta</p>
    <div class="form-grid">
        <div class="field">
            <label for="f-name">Seu nome</label>
            <input type="text" id="f-name" name="name" value="<?= e(old('name')) ?>" required maxlength="150" autocomplete="name"<?= error_attrs('name') ?>>
            <?= field_error('name') ?>
        </div>
        <div class="field">
            <label for="f-email">E-mail corporativo</label>
            <input type="email" id="f-email" name="email" value="<?= e(old('email')) ?>" required maxlength="190" autocomplete="email"<?= error_attrs('email') ?>>
            <?= field_error('email') ?>
        </div>
        <?= Isis\Core\View::partial('partials/password', ['name' => 'password', 'label' => 'Senha', 'hint' => 'Mínimo de 10 caracteres, com maiúsculas, minúsculas e números.']) ?>
        <?= Isis\Core\View::partial('partials/password', ['name' => 'password_confirmation', 'label' => 'Confirme a senha']) ?>
    </div>

    <div class="field">
        <label class="check">
            <input type="checkbox" name="accept_terms" value="1"<?= old('accept_terms') === '1' ? ' checked' : '' ?><?= error_attrs('accept_terms') ?>>
            <span>Li os <a href="/termos" target="_blank" rel="noopener">termos de uso e o aviso de privacidade</a> e entendo que o ISIS apoia a organização da conformidade, sem substituir análise jurídica nem garantir conformidade legal.</span>
        </label>
        <?= field_error('accept_terms') ?>
    </div>

    <button type="submit" class="btn btn-primary btn-block" data-loading="Criando conta...">Criar conta da empresa</button>
</form>

<div class="auth-links">
    <span>Já tem conta? <a href="/entrar">Entrar</a></span>
    <span>Sua empresa já usa o ISIS? Peça um convite ao administrador.</span>
</div>
