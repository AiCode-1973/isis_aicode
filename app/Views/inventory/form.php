<?php

use Isis\Support\Labels;

$isNew = $a['id'] === null;
$action = $isNew ? '/inventario' : '/inventario/' . (int) $a['id'];
$val = static fn (string $key): string => old($key, (string) ($a[$key] ?? ''));
$selData = old_list('data_categories', array_column($a['data_categories'], 'id'));
$selSubjects = old_list('subject_categories', array_column($a['subject_categories'], 'id'));
$selSystems = old_list('systems', array_column($a['systems'], 'id'));
$selDepts = old_list('departments', array_column($a['departments'], 'id'));

if (has_old()) {
    $sharings = [];
    foreach (old_list('sharing_recipient') as $i => $recipient) {
        $sharings[] = [
            'recipient' => $recipient,
            'recipient_type' => old_list('sharing_type')[$i] ?? 'operador',
            'purpose' => old_list('sharing_purpose')[$i] ?? '',
            'international' => (old_list('sharing_international')[$i] ?? '0') === '1',
        ];
    }
} else {
    $sharings = $a['sharings'];
}
$sharings = array_values(array_filter($sharings, static fn (array $s): bool => trim((string) $s['recipient']) !== ''));
$sharings[] = ['recipient' => '', 'recipient_type' => 'operador', 'purpose' => '', 'international' => false];
$sharings[] = ['recipient' => '', 'recipient_type' => 'operador', 'purpose' => '', 'international' => false];
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/inventario">Inventário</a><?= $isNew ? '' : ' · ' . e(code('OP', (int) $a['seq'])) ?></p>
        <h1><?= $isNew ? 'Nova operação de tratamento' : e($a['name']) ?></h1>
        <p>Preencha o que já souber; você pode salvar como rascunho e completar depois. O painel mostra o que falta.</p>
    </div>
</div>

<form method="post" action="<?= e($action) ?>" class="card" novalidate>
    <?= csrf_field() ?>

    <section class="form-section">
        <h2>1. Identificação</h2>
        <div class="form-grid">
            <div class="field full">
                <label for="f-name">Nome da operação</label>
                <input type="text" id="f-name" name="name" value="<?= e($val('name')) ?>" required maxlength="190" placeholder="Ex.: Cadastro de pacientes, Folha de pagamento, Newsletter"<?= error_attrs('name') ?>>
                <?= field_error('name') ?>
            </div>
            <div class="field full">
                <label for="f-description">Descrição <span class="muted">(opcional)</span></label>
                <textarea id="f-description" name="description" maxlength="5000" placeholder="Como o tratamento acontece, do início ao fim."><?= e($val('description')) ?></textarea>
            </div>
        </div>
        <div class="form-grid-3 section-gap-sm">
            <div class="field">
                <label for="f-status">Situação</label>
                <select id="f-status" name="status"<?= error_attrs('status') ?>>
                    <?php foreach (Labels::ACTIVITY_STATUS as $k => $v): ?><option value="<?= e($k) ?>"<?= $val('status') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-role">Papel da empresa</label>
                <select id="f-role" name="controller_role">
                    <?php foreach (Labels::CONTROLLER_ROLE as $k => $v): ?><option value="<?= e($k) ?>"<?= $val('controller_role') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-review">Próxima revisão</label>
                <input type="date" id="f-review" name="review_due_date" value="<?= e($val('review_due_date')) ?>"<?= error_attrs('review_due_date') ?>>
                <?= field_error('review_due_date') ?>
            </div>
            <div class="field">
                <label for="f-dept">Setor responsável</label>
                <select id="f-dept" name="department_id"<?= error_attrs('department_id') ?>>
                    <option value="">—</option>
                    <?php foreach ($departments as $id => $name): ?><option value="<?= (int) $id ?>"<?= $val('department_id') === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
                </select>
                <?= field_error('department_id') ?>
            </div>
            <div class="field">
                <label for="f-owner">Responsável</label>
                <select id="f-owner" name="owner_user_id"<?= error_attrs('owner_user_id') ?>>
                    <option value="">—</option>
                    <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= $val('owner_user_id') === (string) $u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                </select>
                <?= field_error('owner_user_id') ?>
            </div>
        </div>
    </section>

    <section class="form-section">
        <h2>2. Finalidade e hipótese legal</h2>
        <p class="hint">A finalidade deve ser específica e informada ao titular (art. 6º, I). Escolha a hipótese legal que de fato sustenta o tratamento.</p>
        <div class="form-grid">
            <div class="field full">
                <label for="f-purpose">Finalidade</label>
                <textarea id="f-purpose" name="purpose" maxlength="5000" required placeholder="Para que os dados são usados?"<?= error_attrs('purpose') ?>><?= e($val('purpose')) ?></textarea>
                <?= field_error('purpose') ?>
            </div>
            <div class="field">
                <label for="f-basis">Hipótese legal (art. 7º)</label>
                <select id="f-basis" name="legal_basis_code"<?= error_attrs('legal_basis_code') ?>>
                    <option value="">— ainda não definida —</option>
                    <?php foreach ($bases as $code => $b): ?><option value="<?= e($code) ?>"<?= $val('legal_basis_code') === $code ? ' selected' : '' ?>><?= e($b['article'] . ' · ' . $b['name']) ?></option><?php endforeach; ?>
                </select>
                <?= field_error('legal_basis_code') ?>
            </div>
            <div class="field">
                <label for="f-sbasis">Hipótese legal para dados sensíveis (art. 11)</label>
                <select id="f-sbasis" name="sensitive_legal_basis_code"<?= error_attrs('sensitive_legal_basis_code') ?>>
                    <option value="">— não se aplica / não definida —</option>
                    <?php foreach ($sensitiveBases as $code => $b): ?><option value="<?= e($code) ?>"<?= $val('sensitive_legal_basis_code') === $code ? ' selected' : '' ?>><?= e($b['article'] . ' · ' . $b['name']) ?></option><?php endforeach; ?>
                </select>
                <p class="hint">Obrigatória quando houver dados sensíveis (saúde, biometria, origem racial etc.).</p>
            </div>
            <div class="field full">
                <label for="f-notes">Justificativa e salvaguardas <span class="muted">(opcional)</span></label>
                <textarea id="f-notes" name="legal_basis_notes" maxlength="5000" placeholder="Ex.: avaliação de legítimo interesse, forma de coleta do consentimento, cuidados com dados de crianças."><?= e($val('legal_basis_notes')) ?></textarea>
            </div>
        </div>
    </section>

    <section class="form-section">
        <h2>3. Dados e titulares</h2>
        <p class="hint">Faltou alguma opção? Cadastre em <a href="/inventario/catalogos" target="_blank" rel="noopener">Catálogos</a> e recarregue esta página.</p>
        <div class="form-grid">
            <fieldset class="field full">
                <legend class="label">Categorias de dados pessoais</legend>
                <?php if (!$dataCategories): ?><p class="hint">Nenhuma categoria cadastrada.</p><?php else: ?>
                <div class="check-grid">
                    <?php foreach ($dataCategories as $id => $c): ?>
                    <label class="check"><input type="checkbox" name="data_categories[]" value="<?= (int) $id ?>"<?= in_array((string) $id, $selData, true) ? ' checked' : '' ?>> <span><?= e($c['name']) ?><?= $c['extra'] ? ' <span class="badge badge-danger badge-sm">sensível</span>' : '' ?></span></label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </fieldset>
            <fieldset class="field full">
                <legend class="label">Categorias de titulares</legend>
                <?php if (!$subjectCategories): ?><p class="hint">Nenhuma categoria cadastrada.</p><?php else: ?>
                <div class="check-grid">
                    <?php foreach ($subjectCategories as $id => $c): ?>
                    <label class="check"><input type="checkbox" name="subject_categories[]" value="<?= (int) $id ?>"<?= in_array((string) $id, $selSubjects, true) ? ' checked' : '' ?>> <span><?= e($c['name']) ?><?= $c['extra'] ? ' <span class="badge badge-warn badge-sm">vulnerável</span>' : '' ?></span></label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </fieldset>
        </div>
        <div class="form-grid-3 section-gap-sm">
            <div class="field">
                <label for="f-volume">Volume de titulares</label>
                <select id="f-volume" name="subjects_volume">
                    <option value="">—</option>
                    <?php foreach (Labels::SUBJECTS_VOLUME as $k => $v): ?><option value="<?= e($k) ?>"<?= $val('subjects_volume') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-source">Origem dos dados</label>
                <select id="f-source" name="data_source">
                    <option value="">—</option>
                    <?php foreach (Labels::DATA_SOURCE as $k => $v): ?><option value="<?= e($k) ?>"<?= $val('data_source') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-source-d">Detalhes da origem</label>
                <input type="text" id="f-source-d" name="source_details" value="<?= e($val('source_details')) ?>" maxlength="400" placeholder="Ex.: formulário do site, recepção">
            </div>
        </div>
    </section>

    <section class="form-section">
        <h2>4. Sistemas e setores envolvidos</h2>
        <div class="form-grid">
            <fieldset class="field">
                <legend class="label">Sistemas e locais de armazenamento</legend>
                <?php if (!$systems): ?><p class="hint">Nenhum sistema cadastrado.</p><?php else: ?>
                <div class="check-grid">
                    <?php foreach ($systems as $id => $s): ?>
                    <label class="check"><input type="checkbox" name="systems[]" value="<?= (int) $id ?>"<?= in_array((string) $id, $selSystems, true) ? ' checked' : '' ?>> <span><?= e($s['name']) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </fieldset>
            <fieldset class="field">
                <legend class="label">Outros setores que acessam os dados</legend>
                <div class="check-grid">
                    <?php foreach ($departments as $id => $name): ?>
                    <label class="check"><input type="checkbox" name="departments[]" value="<?= (int) $id ?>"<?= in_array((string) $id, $selDepts, true) ? ' checked' : '' ?>> <span><?= e($name) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        </div>
    </section>

    <section class="form-section">
        <h2>5. Compartilhamentos</h2>
        <p class="hint">Com quem os dados são compartilhados (prestadores, parceiros, órgãos públicos). Deixe em branco as linhas sem uso.</p>
        <?= field_error('sharing_recipient') ?>
        <?php foreach ($sharings as $i => $s): ?>
        <div class="sharing-row">
            <label class="visually-hidden" for="f-sr-<?= $i ?>">Destinatário</label>
            <input type="text" id="f-sr-<?= $i ?>" name="sharing_recipient[<?= $i ?>]" value="<?= e($s['recipient']) ?>" maxlength="190" placeholder="Destinatário">
            <label class="visually-hidden" for="f-st-<?= $i ?>">Tipo</label>
            <select id="f-st-<?= $i ?>" name="sharing_type[<?= $i ?>]">
                <?php foreach (Labels::RECIPIENT_TYPE as $k => $v): ?><option value="<?= e($k) ?>"<?= $s['recipient_type'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
            </select>
            <label class="visually-hidden" for="f-sp-<?= $i ?>">Finalidade do compartilhamento</label>
            <input type="text" id="f-sp-<?= $i ?>" name="sharing_purpose[<?= $i ?>]" value="<?= e((string) $s['purpose']) ?>" maxlength="400" placeholder="Para quê">
            <label class="visually-hidden" for="f-si-<?= $i ?>">Fora do Brasil?</label>
            <select id="f-si-<?= $i ?>" name="sharing_international[<?= $i ?>]">
                <option value="0">No Brasil</option>
                <option value="1"<?= $s['international'] ? ' selected' : '' ?>>Exterior</option>
            </select>
        </div>
        <?php endforeach; ?>
        <div class="form-grid section-gap-sm">
            <div class="field">
                <span class="label">Transferência internacional</span>
                <label class="check"><input type="checkbox" name="international_transfer" value="1"<?= $val('international_transfer') === '1' ? ' checked' : '' ?>> <span>Os dados são armazenados ou enviados para fora do Brasil</span></label>
            </div>
            <div class="field">
                <label for="f-intl">País e mecanismo (art. 33)</label>
                <input type="text" id="f-intl" name="international_details" value="<?= e($val('international_details')) ?>" maxlength="400" placeholder="Ex.: EUA, nuvem AWS, cláusulas-padrão contratuais">
            </div>
        </div>
    </section>

    <section class="form-section">
        <h2>6. Retenção, descarte e segurança</h2>
        <div class="form-grid">
            <div class="field">
                <label for="f-ret">Prazo de retenção</label>
                <input type="text" id="f-ret" name="retention_period" value="<?= e($val('retention_period')) ?>" maxlength="255" placeholder="Ex.: 5 anos após o fim do contrato">
            </div>
            <div class="field">
                <label for="f-disp">Forma de descarte</label>
                <input type="text" id="f-disp" name="disposal_method" value="<?= e($val('disposal_method')) ?>" maxlength="255" placeholder="Ex.: exclusão segura, anonimização, fragmentação">
            </div>
            <div class="field full">
                <label for="f-sec">Medidas de segurança</label>
                <textarea id="f-sec" name="security_measures" maxlength="5000" placeholder="Ex.: controle de acesso por perfil, criptografia, backup, registro de acessos."><?= e($val('security_measures')) ?></textarea>
            </div>
        </div>
    </section>

    <div class="actions sticky-actions">
        <button type="submit" class="btn btn-primary" data-loading="Salvando..."><?= $isNew ? 'Registrar operação' : 'Salvar alterações' ?></button>
        <a class="btn btn-ghost" href="<?= $isNew ? '/inventario' : '/inventario/' . (int) $a['id'] ?>">Cancelar</a>
    </div>
</form>
