<?php

use Isis\Support\Labels;

$old = has_old();
$include = old_list('include');
$titles = old_list('action_title');
$priorities = old_list('action_priority');
$dues = old_list('action_due');
$owners = old_list('action_owner');
$riskNow = old('risk_id', (string) ($risk['id'] ?? ''));
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/modelos?tipo=plano">Modelos prontos</a> · Plano de ação</p>
        <h1><?= e($model['title']) ?></h1>
        <p><?= e($model['situation']) ?> Revise as ações, ajuste os textos, os prazos e os responsáveis e desmarque o que não se aplica. Só as ações marcadas serão criadas.</p>
    </div>
</div>

<form method="post" action="/planos/modelo/<?= e($model['key']) ?>" class="card" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="field">
            <label for="f-risk">Ligar a um risco <span class="muted">(opcional)</span></label>
            <select id="f-risk" name="risk_id"<?= error_attrs('risk_id') ?>>
                <option value="">— nenhum —</option>
                <?php foreach ($risks as $id => $label): ?><option value="<?= (int) $id ?>"<?= $riskNow === (string) $id ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <p class="hint">As ações aparecem na ficha do risco e ajudam a reduzir o nível dele.</p>
            <?= field_error('risk_id') ?>
        </div>
        <div class="field">
            <label for="f-dept">Setor <span class="muted">(opcional)</span></label>
            <select id="f-dept" name="department_id">
                <option value="">— nenhum —</option>
                <?php foreach ($departments as $id => $name): ?><option value="<?= (int) $id ?>"<?= old('department_id') === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>

    <?= field_error('include') ?>
    <div class="plan-rows">
        <?php foreach ($model['actions'] as $i => [$title, $desc, $priority, $days]):
            $checked = $old ? ($include[$i] ?? '') === '1' : true;
            $p = $old ? ($priorities[$i] ?? $priority) : $priority;
            $o = $old ? ($owners[$i] ?? '') : '';
        ?>
        <fieldset class="plan-row">
            <legend class="visually-hidden">Ação <?= $i + 1 ?></legend>
            <label class="check plan-include"><input type="checkbox" name="include[<?= $i ?>]" value="1"<?= $checked ? ' checked' : '' ?>> <span>Criar</span></label>
            <div class="field plan-title">
                <label for="a-title-<?= $i ?>">Ação <?= $i + 1 ?></label>
                <input type="text" id="a-title-<?= $i ?>" name="action_title[<?= $i ?>]" value="<?= e($old ? ($titles[$i] ?? '') : $title) ?>" maxlength="190"<?= error_attrs('action_title_' . $i) ?>>
                <?php if ($desc): ?><p class="hint"><?= e($desc) ?></p><?php endif; ?>
                <?= field_error('action_title_' . $i) ?>
            </div>
            <div class="field">
                <label for="a-pri-<?= $i ?>">Prioridade</label>
                <select id="a-pri-<?= $i ?>" name="action_priority[<?= $i ?>]">
                    <?php foreach (Labels::PRIORITY as $k => $v): ?><option value="<?= e($k) ?>"<?= $p === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="a-due-<?= $i ?>">Prazo</label>
                <input type="date" id="a-due-<?= $i ?>" name="action_due[<?= $i ?>]" value="<?= e($old ? ($dues[$i] ?? '') : date('Y-m-d', strtotime("+{$days} days"))) ?>"<?= error_attrs('action_due_' . $i) ?>>
                <?= field_error('action_due_' . $i) ?>
            </div>
            <div class="field">
                <label for="a-own-<?= $i ?>">Responsável</label>
                <select id="a-own-<?= $i ?>" name="action_owner[<?= $i ?>]"<?= error_attrs('action_owner_' . $i) ?>>
                    <option value="">— definir depois —</option>
                    <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= $o === (string) $u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </fieldset>
        <?php endforeach; ?>
    </div>

    <div class="actions">
        <button type="submit" class="btn btn-primary" data-loading="Criando...">Criar as ações marcadas</button>
        <a class="btn btn-ghost" href="/modelos?tipo=plano">Cancelar</a>
    </div>
</form>
