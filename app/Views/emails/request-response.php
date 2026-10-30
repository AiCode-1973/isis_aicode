<p>Olá, <?= e($name) ?>.</p>
<p>Sua solicitação de protocolo <strong><?= e($protocol) ?></strong> a <strong><?= e($company) ?></strong> foi <strong><?= e(mb_strtolower($status)) ?></strong>.</p>
<div style="background:#f4f6fa;border:1px solid #e2e7ef;border-radius:8px;padding:14px 16px;margin:16px 0;white-space:pre-line;"><?= e($response) ?></div>
<p>Dúvidas? Fale com o encarregado de dados<?= $dpo ? ', ' . e($dpo) : '' ?><?= $contact ? ', pelo e-mail ' . e($contact) : '' ?>.</p>
