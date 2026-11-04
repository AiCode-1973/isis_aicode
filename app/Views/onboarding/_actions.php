<?php
use Isis\Services\OnboardingService as Wizard;

$previous = Wizard::previous($step);
?>
<div class="wizard-actions">
    <?php if ($previous !== null): ?>
    <a class="btn btn-ghost" href="/configuracao-inicial/<?= e($previous) ?>">Voltar</a>
    <?php else: ?>
    <span></span>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary" data-loading="Salvando..."><?= e($submit ?? 'Continuar') ?> <?= icon('arrow-right') ?></button>
</div>
