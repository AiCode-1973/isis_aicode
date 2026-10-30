<?php

declare(strict_types=1);

namespace Isis\Auth;

use Isis\Audit\AuditLogger;
use Isis\Core\Config;
use Isis\Core\Csrf;
use Isis\Core\RateLimiter;
use Isis\Core\Session;
use Isis\Repositories\TenantRepository;
use Isis\Repositories\UserRepository;

/**
 * Autenticação, controle de sessão e verificação de permissões.
 */
final class Auth
{
    private static ?array $user = null;
    private static ?array $tenant = null;

    /** Hash usado quando o e-mail não existe, para o tempo de resposta não revelar contas. */
    private static function dummyHash(): string
    {
        static $hash = null;
        return $hash ??= self::hashPassword(bin2hex(random_bytes(8)));
    }

    public static function hashPassword(string $password): string
    {
        return defined('PASSWORD_ARGON2ID')
            ? password_hash($password, PASSWORD_ARGON2ID)
            : password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function needsRehash(string $hash): bool
    {
        return defined('PASSWORD_ARGON2ID')
            ? password_needs_rehash($hash, PASSWORD_ARGON2ID)
            : password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Tenta autenticar. Retorna o usuário ou um código de erro: throttled, invalid, unverified, inactive.
     *
     * @return array{user: ?array, error: ?string}
     */
    public static function attempt(string $email, string $password, string $ip): array
    {
        $email = mb_strtolower(trim($email));
        $window = (int) Config::get('security.login_window_minutes', 15);
        if (
            RateLimiter::tooMany('login_account', $email, (int) Config::get('security.login_max_per_account', 5), $window)
            || RateLimiter::tooMany('login_ip', $ip, (int) Config::get('security.login_max_per_ip', 20), $window)
        ) {
            AuditLogger::log('auth.login_blocked', 'user', null, null, ['email' => $email], ['tenant_id' => null, 'user_id' => null, 'user_name' => null]);
            return ['user' => null, 'error' => 'throttled'];
        }

        $user = UserRepository::findByEmailGlobal($email);
        $valid = password_verify($password, $user['password_hash'] ?? self::dummyHash()) && $user !== null;

        if (!$valid) {
            RateLimiter::hit('login_account', $email);
            RateLimiter::hit('login_ip', $ip);
            AuditLogger::log('auth.login_failed', 'user', $user ? (int) $user['id'] : null, null, ['email' => $email], [
                'tenant_id' => $user ? (int) $user['tenant_id'] : null,
                'user_id' => null,
                'user_name' => null,
            ]);
            return ['user' => null, 'error' => 'invalid'];
        }

        $tenant = (new TenantRepository())->findById((int) $user['tenant_id']);
        if ($user['status'] !== 'active' || $tenant === null || $tenant['status'] !== 'active') {
            return ['user' => null, 'error' => 'inactive'];
        }
        if ($user['email_verified_at'] === null) {
            return ['user' => $user, 'error' => 'unverified'];
        }

        RateLimiter::clear('login_account', $email);
        if (self::needsRehash((string) $user['password_hash'])) {
            $user['password_hash'] = self::hashPassword($password);
            \Isis\Core\Database::execute('UPDATE users SET password_hash = ? WHERE id = ?', [$user['password_hash'], $user['id']]);
        }
        return ['user' => $user, 'error' => null];
    }

    /** Abre a sessão autenticada (novo ID de sessão e novo token CSRF). */
    public static function login(array $user): void
    {
        Session::regenerate();
        Csrf::rotate();
        $now = time();
        Session::set('auth', [
            'uid' => (int) $user['id'],
            'tid' => (int) $user['tenant_id'],
            'epoch' => (int) $user['session_epoch'],
            'login_at' => $now,
            'last_seen' => $now,
        ]);
        self::$user = $user;
        TenantContext::set((int) $user['tenant_id']);
        self::$tenant = (new TenantRepository())->findById((int) $user['tenant_id']);

        (new UserRepository())->save((int) $user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        AuditLogger::log('auth.login', 'user', (int) $user['id']);
    }

    /**
     * Restaura a sessão a cada requisição. Encerra se expirou, se o usuário/empresa foi desativado
     * ou se a "época" de sessão mudou (troca de senha, "sair de todos os dispositivos").
     *
     * @return string|null Motivo do encerramento, ou null se a sessão é válida (ou não há sessão)
     */
    public static function boot(): ?string
    {
        $auth = Session::get('auth');
        if (!is_array($auth) || !isset($auth['uid'], $auth['tid'])) {
            return null;
        }
        $now = time();
        $idle = (int) Config::get('session.idle_minutes', 30) * 60;
        $max = (int) Config::get('session.max_hours', 8) * 3600;
        if ($now - (int) $auth['last_seen'] > $idle || $now - (int) $auth['login_at'] > $max) {
            self::endSession();
            return 'expired';
        }

        $user = UserRepository::findByIdGlobal((int) $auth['uid']);
        if (
            $user === null
            || (int) $user['tenant_id'] !== (int) $auth['tid']
            || $user['status'] !== 'active'
            || (int) $user['session_epoch'] !== (int) $auth['epoch']
        ) {
            self::endSession();
            return 'revoked';
        }
        $tenant = (new TenantRepository())->findById((int) $user['tenant_id']);
        if ($tenant === null || $tenant['status'] !== 'active') {
            self::endSession();
            return 'revoked';
        }

        self::$user = $user;
        self::$tenant = $tenant;
        TenantContext::set((int) $user['tenant_id']);
        $auth['last_seen'] = $now;
        Session::set('auth', $auth);
        return null;
    }

    /** Atualiza a época guardada na sessão atual (mantém este dispositivo após invalidar os outros). */
    public static function refreshEpoch(int $epoch): void
    {
        $auth = Session::get('auth');
        if (is_array($auth)) {
            $auth['epoch'] = $epoch;
            Session::set('auth', $auth);
        }
    }

    public static function logout(): void
    {
        if (self::$user !== null) {
            AuditLogger::log('auth.logout', 'user', (int) self::$user['id']);
        }
        self::endSession();
    }

    private static function endSession(): void
    {
        self::$user = null;
        self::$tenant = null;
        TenantContext::clear();
        Session::forget('auth');
        Session::regenerate();
        Csrf::rotate();
    }

    public static function check(): bool
    {
        return self::$user !== null;
    }

    public static function user(): ?array
    {
        return self::$user;
    }

    public static function id(): ?int
    {
        return self::$user !== null ? (int) self::$user['id'] : null;
    }

    public static function tenant(): ?array
    {
        return self::$tenant;
    }

    public static function role(): ?Role
    {
        return self::$user !== null ? Role::tryFrom((string) self::$user['role']) : null;
    }

    public static function can(string $permission): bool
    {
        return self::role()?->can($permission) ?? false;
    }

    /** Usado em testes e em rotinas internas. */
    public static function actAs(?array $user, ?array $tenant = null): void
    {
        self::$user = $user;
        self::$tenant = $tenant;
        if ($user !== null) {
            TenantContext::set((int) $user['tenant_id']);
        } else {
            TenantContext::clear();
        }
    }
}
