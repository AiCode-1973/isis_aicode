<?php
use Isis\Core\View;
use Isis\Services\OnboardingService as Wizard;

$saved = $saved ?? [];
$segmentNow = old('segment_code', $saved['segment_code'] ?? $tenant['segment_code']);
$sizeNow = old('size', $saved['size'] ?? '');
$audiences = old_list('audiences', $saved['audiences'] ?? []);
$channels = old_list('channels', $saved['channels'] ?? []);
$existingNow = old_list('existing', $saved['existing'] ?? []);
?>
<?= View::partial('onboarding/_steps', compact('step', 'tenant', 'answers') + [
    'heading' => 'Conte um pouco sobre a empresa',
    'lead' => 'Com estas respostas o ÍSIS sugere as áreas, os tipos de dados e a ordem de trabalho. Leva poucos minutos.',
]) ?>

<form method="post" action="/configuracao-inicial/empresa" class="card" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="field">
            <label for="f-segment_code">Segmento de atuação</label>
            <select id="f-segment_code" name="segment_code"<?= error_attrs('segment_code') ?>>
                <?php foreach ($segments as $code => $label): ?>
                <option value="<?= e($code) ?>"<?= $segmentNow === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint">Define as sugestões de áreas e de tipos de dados das próximas etapas.</p>
            <?= field_error('segment_code') ?>
        </div>
        <div class="field">
            <label for="f-size">Quantas pessoas trabalham na empresa?</label>
            <select id="f-size" name="size"<?= error_attrs('size') ?>>
                <option value="">Selecione</option>
                <?php foreach (Wizard::SIZES as $code => $label): ?>
                <option value="<?= e($code) ?>"<?= $sizeNow === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="hint">Inclua sócios, colaboradores e terceirizados fixos.</p>
            <?= field_error('size') ?>
        </div>
    </div>

    <fieldset class="wizard-group">
        <legend>Com quem a empresa se relaciona?</legend>
        <div class="chips-check">
            <?php foreach (Wizard::AUDIENCES as $code => $label): ?>
            <label class="chip-check"><input type="checkbox" name="audiences[]" value="<?= e($code) ?>"<?= in_array($code, $audiences, true) ? ' checked' : '' ?>> <span><?= e($label) ?></span></label>
            <?php endforeach; ?>
        </div>
        <?= field_error('audiences') ?>
    </fieldset>

    <fieldset class="wizard-group">
        <legend>Por onde os dados chegam? <span class="muted">(marque o que se aplica)</span></legend>
        <div class="chips-check">
            <?php foreach (Wizard::CHANNELS as $code => $label): ?>
            <label class="chip-check"><input type="checkbox" name="channels[]" value="<?= e($code) ?>"<?= in_array($code, $channels, true) ? ' checked' : '' ?>> <span><?= e($label) ?></span></label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="wizard-group">
        <legend>O que a empresa já tem hoje? <span class="muted">(se nada, deixe em branco)</span></legend>
        <div class="chips-check">
            <?php foreach (Wizard::EXISTING as $code => $label): ?>
            <label class="chip-check"><input type="checkbox" name="existing[]" value="<?= e($code) ?>"<?= in_array($code, $existingNow, true) ? ' checked' : '' ?>> <span><?= e($label) ?></span></label>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <?= View::partial('onboarding/_actions', ['step' => $step]) ?>
</form>

<?= View::partial('onboarding/_leave', ['completed' => $completed]) ?>
