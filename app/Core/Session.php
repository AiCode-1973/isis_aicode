<?php

declare(strict_types=1);

namespace Isis\Core;

/**
 * Sessão PHP com cookie endurecido e mensagens "flash" (valem só para a próxima requisição).
 */
final class Session
{
    /** @var array<string, mixed> Flash recebido nesta requisição */
    private static array $flashNow = [];

    public static function start(bool $secure): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $path = APP_ROOT . '/storage/sessions';
        if (is_dir($path) && is_writable($path)) {
            session_save_path($path);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) (((int) Config::get('session.max_hours', 8)) * 3600));
        session_name('isis_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        self::$flashNow = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Novo ID de sessão (evita fixação de sessão) mantendo os dados. */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $params['path'],
                'secure' => $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_destroy();
        }
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return self::$flashNow[$key] ?? $default;
    }

    /** Usado em testes. */
    public static function setFlashNow(array $flash): void
    {
        self::$flashNow = $flash;
    }
}
