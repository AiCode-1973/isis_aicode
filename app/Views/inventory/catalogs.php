<?php

use Isis\Support\Labels;

$extraLabel = ['dados' => 'Sensível (art. 5º, II)', 'titulares' => 'Vulnerável (crianças, idosos...)'];
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/inventario">Inventário</a></p>
        <h1>Catálogos do inventário</h1>
        <p>Listas reutilizadas nas operações de tratamento. Excluir um item o remove das operações onde ele aparece.</p>
    </div>
    <?php if ($canEdit): ?>
    <form method="post" action="/inventario/catalogos/importar" class="inline-form">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-ghost"><?= icon('download') ?> Importar sugestões do segmento</button>
    </form>
    <?php endif; ?>
</div>

<nav class="tabs-nav" aria-label="Catálogos">
    <?php foreach ($catalogs as $cat): ?><a class="btn btn-ghost btn-sm" href="#<?= e($cat->type) ?>"><?= e($cat->label()) ?></a><?php endforeach; ?>
</nav>

<div class="stack">
<?php foreach ($catalogs as $cat): $items = $cat->all(); ?>
    <section class="card" id="<?= e($cat->type) ?>" aria-labelledby="t-<?= e($cat->type) ?>">
        <div class="card-header">
            <div><h2 id="t-<?= e($cat->type) ?>"><?= e($cat->label()) ?></h2><p><?= count($items) ?> item(ns).</p></div>
        </div>

        <?php if ($canEdit): ?>
        <form method="post" action="/inventario/catalogos/<?= e($cat->type) ?>" class="actions" novalidate>
            <?= csrf_field() ?>
            <label class="visually-hidden" for="new-<?= e($cat->type) ?>">Novo item</label>
            <input type="text" id="new-<?= e($cat->type) ?>" name="name" class="grow" maxlength="150" required placeholder="Novo item">
            <?php if ($cat->type === 'sistemas'): ?>
            <label class="visually-hidden" for="host-<?= e($cat->type) ?>">Hospedagem</label>
            <select id="host-<?= e($cat->type) ?>" name="hosting" class="grow">
                <?php foreach (Labels::SYSTEM_HOSTING as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
            </select>
            <?php else: ?>
            <label class="check"><input type="checkbox" name="flag" value="1"> <span><?= e($extraLabel[$cat->type]) ?></span></label>
            <?php endif; ?>
            <button type="submit" class="btn btn-teal"><?= icon('plus') ?> Adicionar</button>
        </form>
        <?php endif; ?>

        <?php if (!$items): ?>
        <p class="empty">Nenhum item. <?= $cat->type !== 'sistemas' && $canEdit ? 'Use "Importar sugestões do segmento" para começar.' : '' ?></p>
        <?php else: ?>
        <ul class="dept-list">
            <?php foreach ($items as $item): ?>
            <li>
                <?php if ($canEdit): ?>
                <form method="post" action="/inventario/catalogos/<?= e($cat->type) ?>/<?= (int) $item['id'] ?>" class="dept-row" novalidate>
                    <?= csrf_field() ?>
                    <label class="visually-hidden" for="i-<?= e($cat->type . $item['id']) ?>">Nome</label>
                    <input type="text" id="i-<?= e($cat->type . $item['id']) ?>" name="name" value="<?= e($item['name']) ?>" maxlength="150" required>
                    <?php if ($cat->type === 'sistemas'): ?>
                    <select name="hosting" aria-label="Hospedagem">
                        <?php foreach (Labels::SYSTEM_HOSTING as $k => $v): ?><option value="<?= e($k) ?>"<?= $item['hosting'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <label class="check small"><input type="checkbox" name="flag" value="1"<?= $item[$cat->extraColumn()] ? ' checked' : '' ?>> <span><?= $cat->type === 'dados' ? 'Sensível' : 'Vulnerável' ?></span></label>
                    <?php endif; ?>
                    <span class="badge" title="Operações que usam"><?= (int) $item['uses'] ?> uso(s)</span>
                    <button type="submit" class="btn btn-ghost btn-sm">Salvar</button>
                </form>
                <form method="post" action="/inventario/catalogos/<?= e($cat->type) ?>/<?= (int) $item['id'] ?>/excluir" class="inline-form" data-confirm="Excluir &quot;<?= e($item['name']) ?>&quot;?<?= $item['uses'] ? ' Ele será removido de ' . (int) $item['uses'] . ' operação(ões).' : '' ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="icon-btn btn-danger" aria-label="Excluir <?= e($item['name']) ?>"><?= icon('trash') ?></button>
                </form>
                <?php else: ?>
                <span class="grow"><?= e($item['name']) ?></span>
                <?php if ($cat->type === 'dados' && $item['is_sensitive']): ?><span class="badge badge-danger">sensível</span><?php endif; ?>
                <?php if ($cat->type === 'titulares' && $item['vulnerable']): ?><span class="badge badge-warn">vulnerável</span><?php endif; ?>
                <?php if ($cat->type === 'sistemas'): ?><span class="badge"><?= e(label(Labels::SYSTEM_HOSTING, $item['hosting'])) ?></span><?php endif; ?>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
</div>
