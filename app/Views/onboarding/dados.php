<?php
use Isis\Core\View;
use Isis\Services\CatalogService;
use Isis\Services\OnboardingService as Wizard;

$saved = $saved ?? null;
$dataOptions = array_values(array_unique([...Wizard::dataOptions($segment), ...($saved['data_categories'] ?? [])]));
$subjectOptions = array_values(array_unique([...Wizard::subjectOptions($segment), ...($saved['subject_categories'] ?? [])]));
$dataChecked = old_list('data_categories', $saved['data_categories'] ?? ($template['data_categories'] ?? []));
$subjectChecked = old_list('subject_categories', $saved['subject_categories'] ?? ($template['subject_categories'] ?? []));
$radio = static function (string $name, string $question, string $hint) use ($saved): string {
    $now = old($name, (string) ($saved[$name] ?? ''));
    $html = '<fieldset class="wizard-group"><legend>' . e($question) . '</legend><p class="hint">' . e($hint) . '</p><div class="chips-check chips-inline">';
    foreach (Wizard::ANSWERS as $code => $label) {
        $html .= '<label class="chip-check"><input type="radio" name="' . e($name) . '" value="' . e($code) . '"' . ($now === $code ? ' checked' : '') . '> <span>' . e($label) . '</span></label>';
    }
    return $html . '</div>' . field_error($name) . '</fieldset>';
};
?>
<?= View::partial('onboarding/_steps', compact('step', 'tenant', 'answers') + [
    'heading' => 'Que dados pessoais a empresa usa?',
    'lead' => 'Já marcamos o que é comum no seu segmento. Ajuste ao que acontece de fato: isso vira o catálogo do inventário.',
]) ?>

<form method="post" action="/configuracao-inicial/dados" class="card" novalidate>
    <?= csrf_field() ?>
    <fieldset class="wizard-group wizard-group-first">
        <legend>Tipos de dados</legend>
        <p class="hint">Os marcados como <span class="badge badge-danger">sensível</span> têm regras mais rígidas na LGPD (art. 5º, II, e art. 11).</p>
        <div class="chips-check">
            <?php foreach ($dataOptions as $name): ?>
            <label class="chip-check"><input type="checkbox" name="data_categories[]" value="<?= e($name) ?>"<?= in_array($name, $dataChecked, true) ? ' checked' : '' ?>> <span><?= e($name) ?><?php if (CatalogService::isSensitive($name)): ?> <span class="badge badge-danger">sensível</span><?php endif; ?></span></label>
            <?php endforeach; ?>
        </div>
        <?= field_error('data_categories') ?>
        <div class="field section-gap-sm">
            <label for="f-other_data">Outros tipos <span class="muted">(um por linha)</span></label>
            <textarea id="f-other_data" name="other_data" maxlength="2000" rows="2"><?= e(old('other_data')) ?></textarea>
        </div>
    </fieldset>

    <fieldset class="wizard-group">
        <legend>De quem são esses dados?</legend>
        <div class="chips-check">
            <?php foreach ($subjectOptions as $name): ?>
            <label class="chip-check"><input type="checkbox" name="subject_categories[]" value="<?= e($name) ?>"<?= in_array($name, $subjectChecked, true) ? ' checked' : '' ?>> <span><?= e($name) ?><?php if (CatalogService::isVulnerable($name)): ?> <span class="badge badge-warn">vulnerável</span><?php endif; ?></span></label>
            <?php endforeach; ?>
        </div>
        <?= field_error('subject_categories') ?>
        <div class="field section-gap-sm">
            <label for="f-other_subjects">Outros titulares <span class="muted">(um por linha)</span></label>
            <textarea id="f-other_subjects" name="other_subjects" maxlength="2000" rows="2"><?= e(old('other_subjects')) ?></textarea>
        </div>
    </fieldset>

    <?= $radio('vendors', 'Fornecedores ou sistemas de terceiros acessam esses dados?', 'Por exemplo: sistema em nuvem, contabilidade, folha de pagamento, agência de marketing, laboratório parceiro.') ?>
    <?= $radio('international', 'Algum desses dados fica em servidores fora do Brasil?', 'É comum em serviços de nuvem, e-mail e ferramentas de marketing estrangeiros.') ?>

    <?= View::partial('onboarding/_actions', ['step' => $step]) ?>
</form>

<?= View::partial('onboarding/_leave', ['completed' => $completed]) ?>
