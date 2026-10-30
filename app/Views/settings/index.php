<div class="page-head">
    <div>
        <h1>Configurações da empresa</h1>
        <p>Dados cadastrais, segmento, encarregado e setores.</p>
    </div>
</div>

<div class="grid grid-2 wide-split">
    <form method="post" action="/configuracoes/empresa" class="card" novalidate>
        <?= csrf_field() ?>
        <h2>Dados da empresa</h2>
        <div class="form-grid section-gap-sm">
            <div class="field full">
                <label for="f-legal_name">Razão social</label>
                <input type="text" id="f-legal_name" name="legal_name" value="<?= e(old('legal_name', $tenant['legal_name'])) ?>" required maxlength="190"<?= error_attrs('legal_name') ?>>
                <?= field_error('legal_name') ?>
            </div>
            <div class="field">
                <label for="f-trade_name">Nome fantasia</label>
                <input type="text" id="f-trade_name" name="trade_name" value="<?= e(old('trade_name', $tenant['trade_name'] ?? '')) ?>" maxlength="190"<?= error_attrs('trade_name') ?>>
                <?= field_error('trade_name') ?>
            </div>
            <div class="field">
                <label for="f-cnpj">CNPJ</label>
                <input type="text" id="f-cnpj" value="<?= e(fmt_cnpj($tenant['cnpj'])) ?>" readonly>
                <p class="hint">Para alterar o CNPJ, fale com o suporte.</p>
            </div>
            <div class="field full">
                <label for="f-segment_code">Segmento</label>
                <select id="f-segment_code" name="segment_code"<?= error_attrs('segment_code') ?>>
                    <?php foreach ($segments as $code => $name): ?>
                    <option value="<?= e($code) ?>"<?= old('segment_code', $tenant['segment_code']) === $code ? ' selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error('segment_code') ?>
            </div>
        </div>

        <p class="section-title">Encarregado e canal de privacidade</p>
        <div class="form-grid">
            <div class="field">
                <label for="f-dpo_name">Nome do encarregado</label>
                <input type="text" id="f-dpo_name" name="dpo_name" value="<?= e(old('dpo_name', $tenant['dpo_name'] ?? '')) ?>" required maxlength="150"<?= error_attrs('dpo_name') ?>>
                <?= field_error('dpo_name') ?>
            </div>
            <div class="field">
                <label for="f-dpo_email">E-mail do encarregado</label>
                <input type="email" id="f-dpo_email" name="dpo_email" value="<?= e(old('dpo_email', $tenant['dpo_email'] ?? '')) ?>" required maxlength="190"<?= error_attrs('dpo_email') ?>>
                <?= field_error('dpo_email') ?>
            </div>
            <div class="field full">
                <label for="f-privacy_email">E-mail do canal de privacidade</label>
                <input type="email" id="f-privacy_email" name="privacy_email" value="<?= e(old('privacy_email', $tenant['privacy_email'] ?? '')) ?>" maxlength="190"<?= error_attrs('privacy_email') ?>>
                <?= field_error('privacy_email') ?>
            </div>
        </div>
        <p class="section-title">Segurança de acesso</p>
        <div class="field">
            <label for="f-mfa_policy">Verificação em duas etapas</label>
            <select id="f-mfa_policy" name="mfa_policy"<?= error_attrs('mfa_policy') ?>>
                <?php foreach (Isis\Support\Labels::MFA_POLICY as $k => $v): ?><option value="<?= e($k) ?>"<?= old('mfa_policy', $tenant['mfa_policy'] ?? 'opcional') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
            </select>
            <?= field_error('mfa_policy') ?>
            <p class="hint">Quando obrigatória, quem ainda não ativou é levado à ativação no próximo acesso, inclusive você. Hoje <?= (int) $mfaAdoption['enabled'] ?> de <?= (int) $mfaAdoption['active'] ?> usuário(s) ativo(s) já ativaram.</p>
        </div>
        <div class="field section-gap">
            <label for="f-channel">Canal on-line do titular</label>
            <input type="text" id="f-channel" class="mono" value="<?= e(absolute_url('/titular/' . $tenant['slug'])) ?>" readonly data-select-on-focus>
            <p class="hint">Divulgue este endereço no aviso de privacidade. Os pedidos chegam em Solicitações de titulares.</p>
        </div>
        <div class="actions section-gap">
            <button type="submit" class="btn btn-primary" data-loading="Salvando...">Salvar dados</button>
        </div>
    </form>

    <section class="card" id="setores" aria-labelledby="dept-title">
        <h2 id="dept-title">Setores</h2>
        <p class="small muted">Organizam usuários, inventário e responsabilidades.</p>
        <form method="post" action="/configuracoes/setores" class="actions section-gap-sm" novalidate>
            <?= csrf_field() ?>
            <label for="f-dept-new" class="visually-hidden">Nome do novo setor</label>
            <input type="text" id="f-dept-new" name="name" placeholder="Novo setor" required maxlength="120" class="grow"<?= error_attrs('name') ?>>
            <button type="submit" class="btn btn-teal"><?= icon('plus') ?> Adicionar</button>
        </form>
        <?= field_error('name') ?>

        <?php if (!$departments): ?>
        <p class="empty">Nenhum setor cadastrado.</p>
        <?php else: ?>
        <ul class="dept-list">
            <?php foreach ($departments as $d): ?>
            <li>
                <form method="post" action="/configuracoes/setores/<?= (int) $d['id'] ?>" class="dept-row" novalidate>
                    <?= csrf_field() ?>
                    <label for="f-dept-<?= (int) $d['id'] ?>" class="visually-hidden">Nome do setor</label>
                    <input type="text" id="f-dept-<?= (int) $d['id'] ?>" name="name" value="<?= e($d['name']) ?>" maxlength="120" required>
                    <span class="badge" title="Usuários no setor"><?= (int) $d['users_count'] ?> <?= icon('user', 'icon') ?></span>
                    <button type="submit" class="btn btn-ghost btn-sm">Salvar</button>
                </form>
                <form method="post" action="/configuracoes/setores/<?= (int) $d['id'] ?>/excluir" class="inline-form" data-confirm="Excluir o setor &quot;<?= e($d['name']) ?>&quot;? Usuários vinculados ficarão sem setor.">
                    <?= csrf_field() ?>
                    <button type="submit" class="icon-btn btn-danger" aria-label="Excluir setor <?= e($d['name']) ?>"><?= icon('trash') ?></button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
</div>
