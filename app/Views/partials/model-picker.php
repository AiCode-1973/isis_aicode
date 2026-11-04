<?php
/*
 * "Começar de um modelo" no topo dos formulários de cadastro. Escolher um modelo só recarrega a tela
 * com os campos preenchidos (GET ?modelo=...): nada é gravado até a pessoa salvar.
 *
 * Variáveis: $type (inventario|risco|incidente), $models (lista), $model (aplicado ou null), $path (endereço do formulário),
 * $missing (itens do modelo que não existem nos catálogos), $extra (aviso adicional, opcional).
 */
if (!$models) {
    return;
}
$missing ??= [];
?>
<?php if ($model): ?>
<div class="alert alert-info model-applied" role="status">
    <?= icon('file') ?>
    <div>
        <p><strong>Modelo aplicado: <?= e($model['title']) ?>.</strong> Os campos vieram preenchidos com um exemplo. Revise e ajuste à realidade da empresa antes de salvar; nada foi gravado ainda.</p>
        <?php if ($missing): ?>
        <p class="small">Estes itens do modelo não existem nos seus catálogos e ficaram de fora: <?= e(implode(', ', $missing)) ?>. Cadastre-os em <a href="/inventario/catalogos">Catálogos</a> se fizerem sentido.</p>
        <?php endif; ?>
        <?php if (!empty($extra)): ?><p class="small"><?= $extra ?></p><?php endif; ?>
        <p class="small"><a href="<?= e($path) ?>">Começar em branco</a> · <a href="/modelos?tipo=<?= e($type) ?>">Ver outros modelos</a></p>
    </div>
</div>
<?php else: ?>
<form method="get" action="<?= e($path) ?>" class="card model-picker" aria-label="Começar de um modelo">
    <span class="model-picker-icon" aria-hidden="true"><?= icon('file') ?></span>
    <div class="field">
        <label for="f-modelo">Começar de um modelo <span class="muted">(opcional)</span></label>
        <select id="f-modelo" name="modelo">
            <?php foreach ($models as $m): ?><option value="<?= e($m['key']) ?>"><?= e($m['title']) ?></option><?php endforeach; ?>
        </select>
        <p class="hint">Preenche o formulário com um exemplo para a situação escolhida. Você revisa tudo antes de salvar.</p>
    </div>
    <div class="model-picker-actions">
        <button type="submit" class="btn btn-ghost">Usar modelo</button>
        <a class="small" href="/modelos?tipo=<?= e($type) ?>">Ver todos os modelos</a>
    </div>
</form>
<?php endif; ?>
