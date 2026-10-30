<?php

declare(strict_types=1);

namespace Isis\Core;

/**
 * Envio de e-mail. Driver "log" grava em storage/logs/mail.log (desenvolvimento);
 * "smtp" usa PHPMailer (instalado via composer).
 */
final class Mailer
{
    /** @var list<array{to: string, subject: string, html: string}> Últimos envios (usado nos testes) */
    public static array $sent = [];

    /** @param array<string, mixed> $data */
    public static function sendTemplate(string $to, string $subject, string $template, array $data = []): bool
    {
        $html = View::render('emails/' . $template, $data + ['subject' => $subject], 'email');
        return self::send($to, $subject, $html);
    }

    public static function send(string $to, string $subject, string $html): bool
    {
        self::$sent[] = ['to' => $to, 'subject' => $subject, 'html' => $html];
        $driver = Config::get('mail.driver', 'log');

        if ($driver === 'smtp' && class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            return self::sendSmtp($to, $subject, $html);
        }
        if ($driver === 'smtp') {
            Logger::error('MAIL_DRIVER=smtp, mas o PHPMailer não está instalado (rode composer install). E-mail gravado no log.');
        }
        if ($driver === 'array') {
            return true;
        }
        $dir = APP_ROOT . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $entry = sprintf("==== %s\nPara: %s\nAssunto: %s\n\n%s\n\n", date('Y-m-d H:i:s'), $to, $subject, $html);
        return file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    private static function sendSmtp(string $to, string $subject, string $html): bool
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = (string) Config::get('mail.host');
            $mail->Port = (int) Config::get('mail.port');
            $mail->SMTPAuth = Config::get('mail.username') !== null;
            $mail->Username = (string) Config::get('mail.username');
            $mail->Password = (string) Config::get('mail.password');
            $encryption = Config::get('mail.encryption');
            if ($encryption === 'ssl' || $encryption === 'tls') {
                $mail->SMTPSecure = $encryption === 'ssl'
                    ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                    : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->CharSet = 'UTF-8';
            $mail->setFrom((string) Config::get('mail.from_address'), (string) Config::get('mail.from_name'));
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p>'], "\n", $html))));
            return $mail->send();
        } catch (\Throwable $e) {
            Logger::error('Falha no envio de e-mail', ['erro' => $e->getMessage()]);
            return false;
        }
    }
}
