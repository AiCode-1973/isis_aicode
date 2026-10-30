<?php

declare(strict_types=1);

namespace Isis\Core;

/**
 * Criptografia de campos sensíveis com libsodium (XChaCha20-Poly1305, autenticada).
 * Subchaves derivadas da APP_KEY via HKDF; o contexto (ex.: "tenant:12") vai como dado associado,
 * de modo que um valor cifrado de uma empresa não decifra no contexto de outra.
 */
final class Crypto
{
    private const string PREFIX = 'v1:';
    private static ?string $masterKey = null;

    public static function setKey(?string $key): void
    {
        self::$masterKey = $key === null ? null : self::decodeKey($key);
    }

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
    }

    public static function encrypt(?string $plain, string $context = ''): ?string
    {
        if ($plain === null) {
            return null;
        }
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, $context, $nonce, self::subkey('encryption'));
        return self::PREFIX . base64_encode($nonce . $cipher);
    }

    public static function decrypt(?string $payload, string $context = ''): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }
        if (!str_starts_with($payload, self::PREFIX)) {
            throw new \RuntimeException('Formato de dado criptografado desconhecido.');
        }
        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        $nonceSize = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if ($raw === false || strlen($raw) <= $nonceSize) {
            throw new \RuntimeException('Dado criptografado corrompido.');
        }
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($raw, $nonceSize),
            $context,
            substr($raw, 0, $nonceSize),
            self::subkey('encryption'),
        );
        if ($plain === false) {
            throw new \RuntimeException('Não foi possível decifrar o dado (chave ou contexto incorretos).');
        }
        return $plain;
    }

    /** Índice cego (HMAC) para buscar por igualdade sem guardar o valor em claro. */
    public static function blindIndex(string $value, string $context): string
    {
        return hash_hmac('sha256', $context . '|' . mb_strtolower(trim($value)), self::subkey('blind-index'));
    }

    /** Token aleatório seguro para URLs (base64url, sem padding). */
    public static function randomToken(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    /** Tokens são guardados só como hash. */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private static function subkey(string $purpose): string
    {
        return hash_hkdf('sha256', self::masterKey(), 32, 'isis:' . $purpose);
    }

    private static function masterKey(): string
    {
        if (self::$masterKey === null) {
            $configured = Config::get('app.key');
            if (!is_string($configured) || $configured === '') {
                throw new \RuntimeException('APP_KEY não configurada. Gere com: php bin/isis key:generate');
            }
            self::$masterKey = self::decodeKey($configured);
        }
        return self::$masterKey;
    }

    private static function decodeKey(string $key): string
    {
        $raw = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : false;
        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new \RuntimeException('APP_KEY inválida: use o formato base64: com 32 bytes.');
        }
        return $raw;
    }
}
