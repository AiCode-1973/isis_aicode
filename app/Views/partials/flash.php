<?php

use Isis\Core\Session;

$messages = [
    'success' => ['alert-success', 'check'],
    'error' => ['alert-error', 'alert'],
    'info' => ['alert-info', 'info'],
    'warning' => ['alert-warn', 'alert'],
];
foreach ($messages as $key => [$class, $iconName]):
    $text = Session::getFlash($key);
    if (!is_string($text) || $text === '') {
        continue;
    }
?>
<div class="alert <?= $class ?>" role="<?= $key === 'error' ? 'alert' : 'status' ?>">
    <?= icon($iconName) ?>
    <p><?= e($text) ?></p>
</div>
<?php endforeach; ?>
