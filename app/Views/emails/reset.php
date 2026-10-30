<p>Olá, <?= e($name) ?>.</p>
<p>Recebemos um pedido para redefinir a senha da sua conta no <strong>ÍSIS</strong>.</p>
<p style="margin:24px 0;"><a href="<?= e($link) ?>" style="background:#1d3a6e;color:#ffffff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:bold;">Criar nova senha</a></p>
<p style="font-size:13px;color:#5d6b7f;">O link vale por <?= (int) $minutes ?> minutos e só pode ser usado uma vez. Se você não fez este pedido, ignore este e-mail: sua senha atual continua valendo.<br><?= e($link) ?></p>
