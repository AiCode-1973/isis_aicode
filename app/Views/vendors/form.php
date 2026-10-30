<?php

use Isis\Support\Labels;

$isNew = $v['id'] === null;
$val = static fn (string $key): string => old($key, (string) ($v[$key] ?? ''));
$selActs = old_list('activities', array_column($v['activities'] ?? [], 'id'));
$flag = static fn (string $key): bool => has_old() ? old($key) === '1' : (bool) ($v[$key] ?? false);
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/fornecedores">Fornecedores</a><?= $isNew ? '' : ' · ' . e(code('FOR', (int) $v['seq'])) ?></p>
        <h1><?= $isNew ? 'Novo fornecedor' : e($v['name']) ?></h1>
    </div>
</div>

<form method="post" action="<?= $isNew ? '/fornecedores' : '/fornecedores/' . (int) $v['id'] ?>" class="card" novalidate>
    <?= csrf_field() ?>
    <section class="form-section">
        <h2>Identificação</h2>
        <div class="form-grid">
            <div class="field"><label for="f-name">Nome</label><input type="text" id="f-name" name="name" value="<?= e($val('name')) ?>" required maxlength="190"<?= error_attrs('name') ?>><?= field_error('name') ?></div>
            <div class="field"><label for="f-cnpj">CNPJ <span class="muted">(opcional)</span></label><input type="text" id="f-cnpj" name="cnpj" value="<?= e($val('cnpj') !== '' ? fmt_cnpj($val('cnpj')) : '') ?>" maxlength="18" data-mask="cnpj"<?= error_attrs('cnpj') ?>><?= field_error('cnpj') ?></div>
            <div class="field full"><label for="f-service">Serviço prestado</label><input type="text" id="f-service" name="service" value="<?= e($val('service')) ?>" maxlength="190" placeholder="Ex.: hospedagem em nuvem, folha de pagamento, laboratório"></div>
            <div class="field">
                <label for="f-role">Papel em relação aos dados</label>
                <select id="f-role" name="role"><?php foreach (Labels::VENDOR_ROLE as $k => $label): ?><option value="<?= e($k) ?>"<?= $val('role') === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
            </div>
            <div class="field">
                <label for="f-status">Situação</label>
                <select id="f-status" name="status"><?php foreach (Labels::VENDOR_STATUS as $k => $label): ?><option value="<?= e($k) ?>"<?= $val('status') === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
            </div>
        </div>
    </section>

    <section class="form-section">
        <h2>Dados envolvidos</h2>
        <div class="form-grid">
            <div class="field full">
                <label class="check"><input type="checkbox" name="data_access" value="1"<?= $flag('data_access') ? ' checked' : '' ?>> <span>Trata ou acessa dados pessoais da empresa</span></label>
                <label class="check"><input type="checkbox" name="sensitive_data" value="1"<?= $flag('sensitive_data') ? ' checked' : '' ?>> <span>Inclui dados sensíveis ou de crianças</span></label>
                <label class="check"><input type="checkbox" name="international" value="1"<?= $flag('international') ? ' checked' : '' ?>> <span>Armazena ou trata os dados fora do Brasil</span></label>
            </div>
            <div class="field"><label for="f-country">País (se exterior)</label><input type="text" id="f-country" name="country" value="<?= e($val('country')) ?>" maxlength="80"></div>
            <fieldset class="field full">
                <legend class="label">Operações de tratamento em que participa</legend>
                <?php if (!$activities): ?><p class="hint">Nenhuma operação cadastrada no inventário.</p><?php else: ?>
                <div class="check-grid">
                    <?php foreach ($activities as $id => $name): ?>
                    <label class="check"><input type="checkbox" name="activities[]" value="<?= (int) $id ?>"<?= in_array((string) $id, $selActs, true) ? ' checked' : '' ?>> <span><?= e($name) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </fieldset>
        </div>
    </section>

    <section class="form-section">
        <h2>Contato e responsabilidade</h2>
        <div class="form-grid-3">
            <div class="field"><label for="f-cn">Contato no fornecedor</label><input type="text" id="f-cn" name="contact_name" value="<?= e($val('contact_name')) ?>" maxlength="150"></div>
            <div class="field"><label for="f-ce">E-mail</label><input type="email" id="f-ce" name="contact_email" value="<?= e($val('contact_email')) ?>" maxlength="190"<?= error_attrs('contact_email') ?>><?= field_error('contact_email') ?></div>
            <div class="field"><label for="f-cp">Telefone</label><input type="text" id="f-cp" name="contact_phone" value="<?= e($val('contact_phone')) ?>" maxlength="40"></div>
            <div class="field">
                <label for="f-owner">Responsável interno</label>
                <select id="f-owner" name="owner_user_id"><option value="">—</option><?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= $val('owner_user_id') === (string) $u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="field">
                <label for="f-dept">Setor</label>
                <select id="f-dept" name="department_id"><option value="">—</option><?php foreach ($departments as $id => $name): ?><option value="<?= (int) $id ?>"<?= $val('department_id') === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select>
            </div>
            <div class="field"><label for="f-review">Próxima revisão</label><input type="date" id="f-review" name="next_review_date" value="<?= e($val('next_review_date')) ?>"><p class="hint">Calculada pela avaliação; ajuste se precisar.</p></div>
            <div class="field full"><label for="f-notes">Observações</label><textarea id="f-notes" name="notes" maxlength="5000"><?= e($val('notes')) ?></textarea></div>
        </div>
    </section>

    <div class="actions sticky-actions">
        <button type="submit" class="btn btn-primary" data-loading="Salvando..."><?= $isNew ? 'Cadastrar fornecedor' : 'Salvar alterações' ?></button>
        <a class="btn btn-ghost" href="<?= $isNew ? '/fornecedores' : '/fornecedores/' . (int) $v['id'] ?>">Cancelar</a>
    </div>
</form>
