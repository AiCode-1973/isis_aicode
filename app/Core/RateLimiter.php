<?php

declare(strict_types=1);

namespace Isis\Core;

/**
 * Limite de tentativas por "balde" (login, recuperação de senha, formulários públicos).
 * As chaves (e-mail, IP) são guardadas apenas como HMAC.
 */
final class RateLimiter
{
    public static function tooMany(string $bucket, string $key, int $max, int $minutes): bool
    {
        $count = (int) Database::value(
            'SELECT COUNT(*) FROM rate_limit_hits WHERE bucket = ? AND key_hash = ? AND success = 0
             AND created_at > (NOW() - INTERVAL ? MINUTE)',
            [$bucket, self::hash($bucket, $key), $minutes],
        );
        return $count >= $max;
    }

    public static function hit(string $bucket, string $key, bool $success = false): void
    {
        Database::execute(
            'INSERT INTO rate_limit_hits (bucket, key_hash, success, created_at) VALUES (?, ?, ?, NOW())',
            [$bucket, self::hash($bucket, $key), $success ? 1 : 0],
        );
    }

    public static function clear(string $bucket, string $key): void
    {
        Database::execute(
            'DELETE FROM rate_limit_hits WHERE bucket = ? AND key_hash = ? AND success = 0',
            [$bucket, self::hash($bucket, $key)],
        );
    }

    /** Remove registros antigos (chamado ocasionalmente). */
    public static function prune(int $days = 2): void
    {
        Database::execute('DELETE FROM rate_limit_hits WHERE created_at < (NOW() - INTERVAL ? DAY)', [$days]);
    }

    private static function hash(string $bucket, string $key): string
    {
        return Crypto::blindIndex($key, 'rate:' . $bucket);
    }
}
