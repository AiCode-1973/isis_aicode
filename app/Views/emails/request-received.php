<p>Olá, <?= e($name) ?>.</p>
<p>Recebemos sua solicitação de <strong><?= e($right) ?></strong> enviada a <strong><?= e($company) ?></strong>.</p>
<p style="font-size:18px;margin:20px 0;">Protocolo: <strong style="letter-spacing:1px;"><?= e($protocol) ?></strong></p>
<p>A resposta será enviada até <strong><?= e($due) ?></strong>. Antes disso, poderemos pedir informações para confirmar sua identidade e proteger seus dados.</p>
<p>Acompanhe o andamento com o protocolo e este e-mail em:<br><a href="<?= e($link) ?>"><?= e($link) ?></a></p>
