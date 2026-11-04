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

    /** Motivo da última falha de envio (mostrado no teste de e-mail do painel da plataforma). */
    public static ?string $lastError = null;

    /** @param array<string, mixed> $data */
    public static function sendTemplate(string $to, string $subject, string $template, array $data = []): bool
    {
        $html = View::render('emails/' . $template, $data + ['subject' => $subject], 'email');
        return self::send($to, $subject, $html);
    }

    public static function send(string $to, string $subject, string $html): bool
    {
        self::$sent[] = ['to' => $to, 'subject' => $subject, 'html' => $html];
        self::$lastError = null;
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
            } else {
                // MAIL_ENCRYPTION=none: sem TLS nenhum (uso com MAIL_HOST=localhost em hospedagens com "SMTP Restrictions",
                // onde o certificado não vale para "localhost"). Fora do próprio servidor, use tls ou ssl.
                $mail->SMTPAutoTLS = false;
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
            self::$lastError = $e->getMessage();
            Logger::error('Falha no envio de e-mail', ['erro' => $e->getMessage(), 'servidor' => Config::get('mail.host') . ':' . Config::get('mail.port'), 'criptografia' => Config::get('mail.encryption')]);
            return false;
        }
    }

    /**
     * Diagnóstico da conexão SMTP, passo a passo, sem fazer login e sem expor a senha:
     * resolve o nome, conecta, negocia a criptografia e lê o que o servidor oferece.
     *
     * @return list<array{0: bool, 1: string}> [deu certo?, descrição]
     */
    public static function diagnose(): array
    {
        $host = (string) Config::get('mail.host');
        $port = (int) Config::get('mail.port');
        $encryption = (string) Config::get('mail.encryption');
        $steps = [];
        if ($host === '') {
            return [[false, 'MAIL_HOST está vazio.']];
        }
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        $steps[] = [$ip !== $host || filter_var($host, FILTER_VALIDATE_IP) || $host === 'localhost', "Nome {$host} → {$ip}"];

        if (($port === 465 && $encryption !== 'ssl') || ($port === 587 && $encryption === 'ssl')) {
            $steps[] = [false, "Combinação provavelmente errada: a porta 465 usa MAIL_ENCRYPTION=ssl e a 587 usa tls (atual: porta {$port}, " . ($encryption ?: 'none') . ').'];
        }
        $ssl = ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host];
        $scheme = $encryption === 'ssl' ? 'ssl' : 'tcp';
        $errno = 0;
        $err = '';
        $socket = @stream_socket_client("{$scheme}://{$host}:{$port}", $errno, $err, 15, STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => $ssl]));
        if (!$socket) {
            $local = in_array($host, ['localhost', '127.0.0.1'], true);
            $try = $local ? ' Confira se o servidor de e-mail roda nesta máquina e em qual porta (25 ou 587).' : ' Tente MAIL_HOST=localhost com MAIL_ENCRYPTION=none.';
            $hint = match (true) {
                $encryption === 'ssl' && $err !== '' && str_contains(strtolower($err), 'ssl') => ' A criptografia SSL falhou: confira se a porta 465 combina com MAIL_ENCRYPTION=ssl.',
                in_array($errno, [110, 10060], true) || str_contains(strtolower($err), 'timed out') => ' A conexão não teve resposta: provável bloqueio de saída (no cPanel, "SMTP Restrictions").' . $try,
                in_array($errno, [111, 10061], true) || str_contains(strtolower($err), 'refused') => ' O servidor recusou a conexão nessa porta.' . $try,
                default => '',
            };
            $steps[] = [false, "Conexão a {$host}:{$port} ({$scheme}) falhou: {$err} (código {$errno}).{$hint}"];
            return $steps;
        }
        stream_set_timeout($socket, 15);
        $read = static function () use ($socket): string {
            $out = '';
            while (($line = fgets($socket, 515)) !== false) {
                $out .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return trim($out);
        };
        $banner = strtok($read(), "\r\n");
        $steps[] = [str_starts_with((string) $banner, '220'), "Conectado a {$host}:{$port}: " . $banner];
        @fwrite($socket, "EHLO isis.local\r\n");
        $ehlo = $read();
        if ($encryption === 'tls') {
            @fwrite($socket, "STARTTLS\r\n");
            $answer = $read();
            $ok = str_starts_with($answer, '220') && @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
            if (!$ok) {
                $error = error_get_last()['message'] ?? $answer;
                $steps[] = [false, 'STARTTLS falhou: ' . $error . (str_contains($error, 'certificate') || str_contains($error, 'CN') ? ' O certificado não vale para "' . $host . '": use o nome que aparece no certificado ou MAIL_HOST=localhost com MAIL_ENCRYPTION=none.' : '')];
                fclose($socket);
                return $steps;
            }
            $steps[] = [true, 'STARTTLS negociado, certificado válido para ' . $host];
            @fwrite($socket, "EHLO isis.local\r\n");
            $ehlo = $read();
        } elseif ($encryption === 'ssl') {
            $steps[] = [true, 'SSL negociado, certificado válido para ' . $host];
        }
        $steps[] = [(bool) preg_match('/AUTH\s/', $ehlo), preg_match('/AUTH\s+([^\r\n]+)/', $ehlo, $m) ? 'Login oferecido pelo servidor: ' . $m[1] : 'O servidor não ofereceu login (AUTH) nesta conexão.'];
        @fwrite($socket, "QUIT\r\n");
        fclose($socket);
        return $steps;
    }
}
