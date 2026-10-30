<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Crypto;
use Isis\Core\Database;

/**
 * Tokens de uso único (confirmação de e-mail e redefinição de senha), guardados só como hash SHA-256.
 */
final class UserTokenRepository
{
    public const string VERIFY_EMAIL = 'verify_email';
    public const string PASSWORD_RESET = 'password_reset';

    /** Cria um token novo, invalidando os anteriores do mesmo tipo. Retorna o token em claro (vai só no e-mail). */
    public static function issue(int $userId, string $type, int $minutes): string
    {
        $token = Crypto::randomToken();
        Database::transaction(function () use ($userId, $type, $minutes, $token): void {
            Database::execute('UPDATE user_tokens SET used_at = NOW() WHERE user_id = ? AND type = ? AND used_at IS NULL', [$userId, $type]);
            Database::insert('user_tokens', [
                'user_id' => $userId,
                'type' => $type,
                'token_hash' => Crypto::hashToken($token),
                'expires_at' => date('Y-m-d H:i:s', time() + $minutes * 60),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        });
        return $token;
    }

    public static function findValid(string $token, string $type): ?array
    {
        return Database::fetch(
            'SELECT * FROM user_tokens WHERE token_hash = ? AND type = ? AND used_at IS NULL AND expires_at > NOW()',
            [Crypto::hashToken($token), $type],
        );
    }

    public static function consume(int $id): bool
    {
        return Database::execute('UPDATE user_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [$id]) === 1;
    }
}
