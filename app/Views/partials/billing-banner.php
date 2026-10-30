<?php
/*
 * Aviso de assinatura no topo da área da empresa. O administrador vê teste terminando, atraso e
 * vencimento; os demais só veem quando o sistema está em somente leitura.
 */

use Isis\Auth\Auth;
use Isis\Auth\Role;
use Isis\Services\SubscriptionService;

if (!Auth::check() || parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === '/plano') {
    return;
}
$state = SubscriptionService::state(SubscriptionService::current());
$isAdmin = Auth::role() === Role::Admin;
$days = $state['days_left'];
$text = match (true) {
    $state['read_only'] && $isAdmin => 'Assinatura ' . mb_strtolower($state['label']) . ': o ÍSIS está em somente leitura. Titulares e incidentes continuam liberados.',
    $state['read_only'] => 'A assinatura da empresa está em somente leitura: você pode consultar, mas não incluir nem alterar. Titulares e incidentes continuam liberados.',
    !$isAdmin => null,
    $state['status'] === 'teste' && $days !== null && $days <= 7 => 'Seu teste grátis termina em ' . ($days === 0 ? 'hoje' : $days . ($days === 1 ? ' dia' : ' dias')) . '.',
    $state['status'] === 'em_atraso' => 'O pagamento da assinatura está em atraso desde ' . fmt_date($state['ends_at']) . '.',
    default => null,
};
if ($text === null) {
    return;
}
?>
<div class="billing-banner <?= $state['read_only'] ? 'is-danger' : 'is-warn' ?>" role="status">
    <?= icon($state['read_only'] ? 'lock' : 'clock') ?>
    <span><?= e($text) ?></span>
    <?php if ($isAdmin): ?><a href="/plano">Ver plano e assinatura</a><?php endif; ?>
</div>
