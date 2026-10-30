<?php

declare(strict_types=1);

namespace Isis\Core;

/**
 * Token anti-CSRF por sessão (padrão synchronizer token), exigido em todo POST.
 */
final class Csrf
{
    private const string KEY = '_csrf';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function validate(?string $token): bool
    {
        $expected = $_SESSION[self::KEY] ?? null;
        return is_string($expected) && is_string($token) && $token !== '' && hash_equals($expected, $token);
    }

    public static function rotate(): void
    {
        unset($_SESSION[self::KEY]);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }
}
