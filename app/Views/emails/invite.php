<p>Olá, <?= e($name) ?>.</p>
<p><?= e($inviter) ?> convidou você para participar da gestão de privacidade e proteção de dados de <strong><?= e($company) ?></strong> no <strong>ÍSIS</strong>, com o perfil <strong><?= e($role) ?></strong>.</p>
<p style="margin:24px 0;"><a href="<?= e($link) ?>" style="background:#0f8a83;color:#ffffff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:bold;">Aceitar convite</a></p>
<p style="font-size:13px;color:#5d6b7f;">O convite vale por <?= (int) $days ?> dias. Se você não esperava este convite, ignore este e-mail.<br><?= e($link) ?></p>
