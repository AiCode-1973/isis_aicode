<div class="page-head">
    <div>
        <h1>Planos e preços</h1>
        <p>Valores e limites usados na página pública de preços, nas cobranças e nos bloqueios do sistema. Deixe um limite em branco para “sem limite”.</p>
    </div>
    <a class="btn btn-ghost" href="/precos"><?= icon('eye') ?> Ver página pública</a>
</div>

<div class="stack">
<?php foreach ($plans as $p): $mine = old('_plan') === $p['code'] || !has_old(); ?>
<form method="post" action="/plataforma/planos/<?= e($p['code']) ?>" class="card" id="plano-<?= e($p['code']) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="_plan" value="<?= e($p['code']) ?>">
    <h2><?= e($p['name']) ?> <span class="muted small mono">· <?= e($p['code']) ?></span></h2>
    <div class="form-grid form-grid-3 section-gap-sm">
        <div class="field">
            <label for="f-<?= e($p['code']) ?>-name">Nome</label>
            <input type="text" id="f-<?= e($p['code']) ?>-name" name="name" value="<?= e($mine ? old('name', $p['name']) : $p['name']) ?>" required maxlength="80">
        </div>
        <div class="field">
            <label for="f-<?= e($p['code']) ?>-pm">Preço mensal (R$)</label>
            <input type="text" id="f-<?= e($p['code']) ?>-pm" name="price_monthly" inputmode="decimal" value="<?= e($p['price_monthly_cents'] !== null ? number_format($p['price_monthly_cents'] / 100, 2, ',', '.') : '') ?>"<?= $mine ? error_attrs('price_monthly') : '' ?>>
            <?= $mine ? field_error('price_monthly') : '' ?>
        </div>
        <div class="field">
            <label for="f-<?= e($p['code']) ?>-py">Preço anual (R$)</label>
            <input type="text" id="f-<?= e($p['code']) ?>-py" name="price_yearly" inputmode="decimal" value="<?= e($p['price_yearly_cents'] !== null ? number_format($p['price_yearly_cents'] / 100, 2, ',', '.') : '') ?>"<?= $mine ? error_attrs('price_yearly') : '' ?>>
            <p class="hint">Sugestão: 10 × o mensal (2 meses grátis).</p>
            <?= $mine ? field_error('price_yearly') : '' ?>
        </div>
        <div class="field">
            <label for="f-<?= e($p['code']) ?>-mu">Máximo de usuários</label>
            <input type="number" min="1" id="f-<?= e($p['code']) ?>-mu" name="max_users" value="<?= e((string) $p['max_users']) ?>" placeholder="Sem limite">
        </div>
        <div class="field">
            <label for="f-<?= e($p['code']) ?>-mv">Máximo de fornecedores</label>
            <input type="number" min="1" id="f-<?= e($p['code']) ?>-mv" name="max_vendors" value="<?= e((string) $p['max_vendors']) ?>" placeholder="Sem limite">
        </div>
        <div class="field">
            <label for="f-<?= e($p['code']) ?>-mt">Máximo de treinamentos</label>
            <input type="number" min="1" id="f-<?= e($p['code']) ?>-mt" name="max_trainings" value="<?= e((string) $p['max_trainings']) ?>" placeholder="Sem limite">
        </div>
        <div class="field full">
            <label for="f-<?= e($p['code']) ?>-desc">Descrição</label>
            <input type="text" id="f-<?= e($p['code']) ?>-desc" name="description" value="<?= e((string) $p['description']) ?>" maxlength="300">
        </div>
        <div class="field full">
            <label for="f-<?= e($p['code']) ?>-hl">Destaques na página de preços (um por linha)</label>
            <textarea id="f-<?= e($p['code']) ?>-hl" name="highlights" rows="5" maxlength="2000"><?= e(implode("\n", $p['highlights'])) ?></textarea>
        </div>
        <div class="field">
            <label class="check"><input type="checkbox" name="contact_only" value="1"<?= $p['contact_only'] ? ' checked' : '' ?>> <span>Sob consulta (sem preço público)</span></label>
        </div>
        <div class="field">
            <label class="check"><input type="checkbox" name="is_public" value="1"<?= $p['is_public'] ? ' checked' : '' ?>> <span>Mostrar na página de preços</span></label>
        </div>
    </div>
    <div class="actions section-gap-sm"><button type="submit" class="btn btn-primary">Salvar <?= e($p['name']) ?></button></div>
</form>
<?php endforeach; ?>
</div>
