<?php
$text = match (true) {
    $notice === 'teste-fim' => 'O teste grátis do ÍSIS terminou. A conta está em modo somente leitura: tudo pode ser consultado e exportado, mas inclusões e alterações ficam bloqueadas até a contratação de um plano.',
    str_starts_with($notice, 'teste-') => 'O teste grátis do ÍSIS (plano ' . $plan . ') termina em ' . fmt_date($endsAt) . '. Para continuar sem interrupção, escolha um plano.',
    $notice === 'vence-5' => 'A assinatura do ÍSIS (plano ' . $plan . ') vence em ' . fmt_date($endsAt) . '. Se a cobrança já foi paga, desconsidere este aviso.',
    $notice === 'atraso' => 'O período pago da assinatura terminou em ' . fmt_date($endsAt) . '. O acesso continua normal por alguns dias; regularize o pagamento para evitar o modo somente leitura.',
    default => 'A assinatura do ÍSIS venceu em ' . fmt_date($endsAt) . ' e a conta está em modo somente leitura. Regularize o pagamento para voltar a incluir e alterar dados.',
};
?>
<p>Olá, <?= e($name) ?>.</p>
<p><?= e($text) ?></p>
<p>Empresa: <strong><?= e($company) ?></strong>. Solicitações de titulares e incidentes de segurança continuam funcionando em qualquer situação.</p>
<p style="margin:24px 0;"><a href="<?= e($link) ?>" style="background:#1d3a6e;color:#ffffff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:bold;">Ver plano e assinatura</a></p>
