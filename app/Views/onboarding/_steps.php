<?php
use Isis\Services\OnboardingService as Wizard;

$keys = array_keys(Wizard::STEPS);
$current = array_search($step, $keys, true);
$name = $tenant['trade_name'] ?: $tenant['legal_name'];
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Configuração inicial · etapa <?= $current + 1 ?> de <?= count($keys) ?></p>
        <h1><?= e($heading) ?></h1>
        <p><?= e($lead ?? 'Responda com o que você sabe hoje. Tudo pode ser alterado depois em Configurações.') ?></p>
    </div>
</div>

<nav class="wizard-steps" aria-label="Etapas da configuração de <?= e($name) ?>">
    <ol>
        <?php foreach (Wizard::STEPS as $code => $label):
            $i = array_search($code, $keys, true);
            $state = $i < $current ? 'done' : ($i === $current ? 'current' : 'todo');
            $reachable = $i < $current || ($i > $current && Wizard::canOpen($code, $answers));
        ?>
        <li class="is-<?= $state ?>"<?= $state === 'current' ? ' aria-current="step"' : '' ?>>
            <?php if ($reachable): ?>
            <a href="/configuracao-inicial/<?= e($code) ?>"><span class="wizard-dot"><?= $state === 'done' ? icon('check') : $i + 1 ?></span><span class="wizard-label"><?= e($label) ?></span></a>
            <?php else: ?>
            <span class="wizard-dot"><?= $i + 1 ?></span><span class="wizard-label"><?= e($label) ?></span>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ol>
</nav>
