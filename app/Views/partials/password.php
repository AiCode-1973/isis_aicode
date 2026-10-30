<?php
/** @var string $name @var string $label @var string|null $autocomplete @var string|null $hint */
$id = 'f-' . $name;
?>
<div class="field">
    <label for="<?= e($id) ?>"><?= e($label) ?></label>
    <div class="password-wrap">
        <input type="password" id="<?= e($id) ?>" name="<?= e($name) ?>" required maxlength="128"
               autocomplete="<?= e($autocomplete ?? 'new-password') ?>"<?= error_attrs($name) ?>>
        <button type="button" class="password-toggle" data-password-toggle="<?= e($id) ?>" aria-label="Mostrar senha" aria-pressed="false"><?= icon('eye') ?></button>
    </div>
    <?php if (!empty($hint)): ?><p class="hint"><?= e($hint) ?></p><?php endif; ?>
    <?= field_error($name) ?>
</div>
