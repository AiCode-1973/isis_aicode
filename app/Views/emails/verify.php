<p>Olá, <?= e($name) ?>.</p>
<p>Sua empresa foi cadastrada no <strong>ISIS</strong>. Confirme seu e-mail para ativar o acesso:</p>
<p style="margin:24px 0;"><a href="<?= e($link) ?>" style="background:#1d3a6e;color:#ffffff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:bold;">Confirmar e-mail</a></p>
<p style="font-size:13px;color:#5d6b7f;">O link vale por 48 horas e só pode ser usado uma vez. Se o botão não funcionar, copie este endereço no navegador:<br><?= e($link) ?></p>
