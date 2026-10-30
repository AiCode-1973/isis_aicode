<?php

declare(strict_types=1);

namespace Isis\Auth;

use Isis\Audit\AuditLogger;
use Isis\Core\Config;
use Isis\Core\Csrf;
use Isis\Core\RateLimiter;
use Isis\Core\Session;
use Isis\Repositories\PlatformRepository;

/**
 * Acesso da equipe AiCode ao painel da plataforma (/plataforma).
 * Conta e sessão separadas das contas das empresas: ninguém de uma empresa vira administrador da plataforma.
 */
final class PlatformAuth
{
    private static ?array $admin = null;

    /** @return array{admin: ?array, error: ?string} error: throttled ou invalid */
    public static function attempt(string $email, string $password, string $ip): array
    {
        $email = mb_strtolower(trim($email));
        $window = (int) Config::get('security.login_window_minutes', 15);
        if (RateLimiter::tooMany('platform_login', $email, 5, $window) || RateLimiter::tooMany('platform_login_ip', $ip, 10, $window)) {
            return ['admin' => null, 'error' => 'throttled'];
        }
        $admin = (new PlatformRepository())->findAdminByEmail($email);
        $valid = password_verify($password, $admin['password_hash'] ?? Auth::hashPassword(bin2hex(random_bytes(8))))
            && $admin !== null && $admin['status'] === 'active';
        if (!$valid) {
            RateLimiter::hit('platform_login', $email);
            RateLimiter::hit('platform_login_ip', $ip);
            AuditLogger::log('platform.login_failed', 'platform_admin', $admin ? (int) $admin['id'] : null, null, ['email' => $email],
                ['tenant_id' => null, 'user_id' => null, 'user_name' => null]);
            return ['admin' => null, 'error' => 'invalid'];
        }
        RateLimiter::clear('platform_login', $email);
        return ['admin' => $admin, 'error' => null];
    }

    public static function login(array $admin): void
    {
        Session::regenerate();
        Csrf::rotate();
        Session::set('platform', ['aid' => (int) $admin['id'], 'login_at' => time(), 'last_seen' => time()]);
        self::$admin = $admin;
        (new PlatformRepository())->touchLogin((int) $admin['id']);
        AuditLogger::log('platform.login', 'platform_admin', (int) $admin['id'], null, null,
            ['tenant_id' => null, 'user_id' => null, 'user_name' => 'AiCode · ' . $admin['name']]);
    }

    /** Restaura a sessão da plataforma (mesmos limites de inatividade e duração das empresas). */
    public static function boot(): void
    {
        $data = Session::get('platform');
        if (!is_array($data) || !isset($data['aid'])) {
            return;
        }
        $now = time();
        $idle = (int) Config::get('session.idle_minutes', 30) * 60;
        $max = (int) Config::get('session.max_hours', 8) * 3600;
        $admin = (new PlatformRepository())->findAdmin((int) $data['aid']);
        if ($now - (int) $data['last_seen'] > $idle || $now - (int) $data['login_at'] > $max || $admin === null || $admin['status'] !== 'active') {
            self::logout();
            return;
        }
        $data['last_seen'] = $now;
        Session::set('platform', $data);
        self::$admin = $admin;
    }

    public static function logout(): void
    {
        self::$admin = null;
        Session::forget('platform');
        Session::regenerate();
        Csrf::rotate();
    }

    public static function admin(): ?array
    {
        return self::$admin;
    }

    public static function check(): bool
    {
        return self::$admin !== null;
    }

    /** Usado em testes. */
    public static function actAs(?array $admin): void
    {
        self::$admin = $admin;
    }
}
