<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title><?= e($subject ?? 'ISIS') ?></title></head>
<body style="margin:0;padding:0;background:#f4f6fa;font-family:Segoe UI,Arial,sans-serif;color:#0f1b2d;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fa;padding:24px 0;">
<tr><td align="center">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border:1px solid #e2e7ef;border-radius:12px;">
<tr><td style="background:#1d3a6e;border-radius:12px 12px 0 0;padding:18px 24px;color:#ffffff;">
<strong style="font-size:18px;letter-spacing:2px;">ISIS</strong>
<span style="font-size:12px;color:#c3d3ec;display:block;">Gestão da Privacidade e Proteção de Dados</span>
</td></tr>
<tr><td style="padding:24px;font-size:15px;line-height:1.6;">
<?= $content ?>
</td></tr>
<tr><td style="padding:16px 24px;border-top:1px solid #e2e7ef;font-size:12px;color:#5d6b7f;">
Mensagem automática do ISIS. Se você não reconhece esta ação, ignore este e-mail.<br>
<?= Isis\Core\View::partial('partials/disclaimer') ?>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
