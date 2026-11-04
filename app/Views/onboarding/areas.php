<?php
use Isis\Core\View;
use Isis\Services\OnboardingService as Wizard;

$suggested = Wizard::departmentSuggestions($segment);
$savedList = $saved['departments'] ?? null;
// Opções: as já escolhidas, as sugestões do segmento e as já cadastradas, sem repetir.
$options = array_values(array_unique([...($savedList ?? []), ...$suggested, ...$existing]));
$checked = old_list('departments', $savedList ?? [...$suggested, ...$existing]);
?>
<?= View::partial('onboarding/_steps', compact('step', 'tenant', 'answers') + [
    'heading' => 'Quais são as áreas da empresa?',
    'lead' => 'As áreas organizam o inventário, os usuários e quem responde por cada parte. Marque as que existem e acrescente outras.',
]) ?>

<form method="post" action="/configuracao-inicial/areas" class="card" novalidate>
    <?= csrf_field() ?>
    <fieldset class="wizard-group wizard-group-first">
        <legend>Áreas sugeridas para <?= e($segments[$segment] ?? 'o segmento') ?></legend>
        <div class="chips-check">
            <?php foreach ($options as $name): ?>
            <label class="chip-check"><input type="checkbox" name="departments[]" value="<?= e($name) ?>"<?= in_array($name, $checked, true) ? ' checked' : '' ?>> <span><?= e($name) ?></span></label>
            <?php endforeach; ?>
        </div>
        <?= field_error('departments') ?>
        <?php if ($existing): ?>
        <p class="hint">Já cadastradas no ÍSIS: <?= e(implode(', ', $existing)) ?>. Desmarcar aqui não as exclui.</p>
        <?php endif; ?>
    </fieldset>

    <div class="field">
        <label for="f-other">Outras áreas <span class="muted">(uma por linha)</span></label>
        <textarea id="f-other" name="other_departments" maxlength="2000" rows="3" placeholder="Ex.: Jurídico&#10;Compras"<?= error_attrs('other_departments') ?>><?= e(old('other_departments')) ?></textarea>
        <?= field_error('other_departments') ?>
    </div>

    <?= View::partial('onboarding/_actions', ['step' => $step]) ?>
</form>

<?= View::partial('onboarding/_leave', ['completed' => $completed]) ?>
