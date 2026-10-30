<?php

use Isis\Support\Labels;

$isNew = $p['id'] === null;
$val = static fn (string $key): string => old($key, (string) ($p[$key] ?? ''));
$requiresAck = has_old() ? old('requires_ack') === '1' : (bool) ($p['requires_ack'] ?? true);
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/politicas">Políticas e documentos</a><?= $isNew ? '' : ' · ' . e(code('POL', (int) $p['seq'])) ?></p>
        <h1><?= $isNew ? 'Nova política' : e($p['title']) ?></h1>
        <p><?= !$isNew && !empty($newVersion) ? 'Você está criando uma nova versão a partir da vigente. Ela só passa a valer depois de aprovada.' : 'O texto fica como rascunho até ser enviado e aprovado.' ?></p>
    </div>
</div>

<form method="post" action="<?= $isNew ? '/politicas' : '/politicas/' . (int) $p['id'] ?>" class="card" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="field full"><label for="f-title">Título</label><input type="text" id="f-title" name="title" value="<?= e($val('title')) ?>" required maxlength="190"<?= error_attrs('title') ?>><?= field_error('title') ?></div>
        <div class="field">
            <label for="f-cat">Tipo de documento</label>
            <select id="f-cat" name="category"><?php foreach (Labels::POLICY_CATEGORY as $k => $label): ?><option value="<?= e($k) ?>"<?= $val('category') === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field">
            <label for="f-owner">Responsável pelo documento</label>
            <select id="f-owner" name="owner_user_id"><option value="">—</option><?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= $val('owner_user_id') === (string) $u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field">
            <label for="f-review">Validade após aprovação</label>
            <select id="f-review" name="review_months"><?php foreach ([3, 6, 12, 24, 36] as $m): ?><option value="<?= $m ?>"<?= $val('review_months') === (string) $m ? ' selected' : '' ?>><?= $m ?> meses</option><?php endforeach; ?></select>
        </div>
        <div class="field">
            <span class="label">Ciência</span>
            <label class="check"><input type="checkbox" name="requires_ack" value="1"<?= $requiresAck ? ' checked' : '' ?>> <span>Exigir que todos os usuários registrem a leitura</span></label>
        </div>
        <div class="field full">
            <label for="f-content">Texto</label>
            <textarea id="f-content" name="content" rows="22" class="policy-editor" maxlength="200000"<?= error_attrs('content') ?>><?= e(old('content', $content)) ?></textarea>
            <p class="hint">Texto simples; as quebras de linha são mantidas. Para documentos formatados (PDF), anexe o arquivo em Evidências e documentos e resuma aqui.</p>
            <?= field_error('content') ?>
        </div>
        <div class="field full"><label for="f-sum">Resumo das alterações desta versão</label><input type="text" id="f-sum" name="change_summary" value="<?= e(old('change_summary', $summary)) ?>" maxlength="400"></div>
    </div>
    <div class="actions sticky-actions">
        <button type="submit" class="btn btn-primary" data-loading="Salvando...">Salvar rascunho</button>
        <a class="btn btn-ghost" href="<?= $isNew ? '/politicas' : '/politicas/' . (int) $p['id'] ?>">Cancelar</a>
    </div>
</form>
