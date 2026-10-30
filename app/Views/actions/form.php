<?php

use Isis\Support\Labels;

$isNew = $x['id'] === null;
$val = static fn (string $key): string => old($key, (string) ($x[$key] ?? ''));
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/planos">Planos de ação</a><?= $isNew ? '' : ' · ' . e(code('ACT', (int) $x['seq'])) ?></p>
        <h1><?= $isNew ? 'Nova ação' : e($x['title']) ?></h1>
        <p>Uma ação clara tem um responsável, um prazo e uma forma de comprovar que foi feita.</p>
    </div>
</div>

<form method="post" action="<?= $isNew ? '/planos' : '/planos/' . (int) $x['id'] ?>" class="card" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="field full">
            <label for="f-title">O que será feito</label>
            <input type="text" id="f-title" name="title" value="<?= e($val('title')) ?>" required maxlength="190" placeholder="Ex.: Revisar perfis de acesso ao prontuário eletrônico"<?= error_attrs('title') ?>>
            <?= field_error('title') ?>
        </div>
        <div class="field full">
            <label for="f-desc">Detalhes e critério de conclusão</label>
            <textarea id="f-desc" name="description" maxlength="5000" placeholder="Como fazer e o que comprova a conclusão."><?= e($val('description')) ?></textarea>
        </div>
        <div class="field">
            <label for="f-risk">Risco tratado</label>
            <select id="f-risk" name="risk_id"<?= error_attrs('risk_id') ?>>
                <option value="">—</option>
                <?php foreach ($risks as $id => $name): ?><option value="<?= (int) $id ?>"<?= $val('risk_id') === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
            </select>
            <?= field_error('risk_id') ?>
        </div>
        <div class="field">
            <label for="f-activity">Operação de tratamento</label>
            <select id="f-activity" name="activity_id"<?= error_attrs('activity_id') ?>>
                <option value="">—</option>
                <?php foreach ($activities as $id => $name): ?><option value="<?= (int) $id ?>"<?= $val('activity_id') === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="f-incident">Incidente relacionado</label>
            <select id="f-incident" name="incident_id"<?= error_attrs('incident_id') ?>>
                <option value="">—</option>
                <?php foreach ($incidents as $id => $name): ?><option value="<?= (int) $id ?>"<?= $val('incident_id') === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="f-owner">Responsável</label>
            <select id="f-owner" name="owner_user_id"<?= error_attrs('owner_user_id') ?>>
                <option value="">— definir depois —</option>
                <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= $val('owner_user_id') === (string) $u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
            </select>
            <?= field_error('owner_user_id') ?>
        </div>
        <div class="field">
            <label for="f-dept">Setor</label>
            <select id="f-dept" name="department_id">
                <option value="">—</option>
                <?php foreach ($departments as $id => $name): ?><option value="<?= (int) $id ?>"<?= $val('department_id') === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="f-due">Prazo</label>
            <input type="date" id="f-due" name="due_date" value="<?= e($val('due_date')) ?>"<?= error_attrs('due_date') ?>>
            <?= field_error('due_date') ?>
        </div>
        <div class="field">
            <label for="f-priority">Prioridade</label>
            <select id="f-priority" name="priority">
                <?php foreach (Labels::PRIORITY as $k => $v): ?><option value="<?= e($k) ?>"<?= $val('priority') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="f-status">Situação</label>
            <select id="f-status" name="status">
                <?php foreach (Labels::ACTION_STATUS as $k => $v): ?><option value="<?= e($k) ?>"<?= $val('status') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="actions sticky-actions">
        <button type="submit" class="btn btn-primary" data-loading="Salvando..."><?= $isNew ? 'Criar ação' : 'Salvar alterações' ?></button>
        <a class="btn btn-ghost" href="<?= $isNew ? '/planos' : '/planos/' . (int) $x['id'] ?>">Cancelar</a>
    </div>
</form>
