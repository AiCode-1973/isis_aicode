<?php

declare(strict_types=1);

namespace Isis\Auth;

/**
 * Empresa (tenant) da requisição atual. Definida só a partir da sessão autenticada,
 * nunca de parâmetros de URL ou formulário.
 */
final class TenantContext
{
    private static ?int $tenantId = null;

    public static function set(int $tenantId): void
    {
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Empresa inválida.');
        }
        self::$tenantId = $tenantId;
    }

    public static function id(): int
    {
        if (self::$tenantId === null) {
            throw new \LogicException('Nenhuma empresa ativa no contexto: acesso a dados institucionais bloqueado.');
        }
        return self::$tenantId;
    }

    public static function has(): bool
    {
        return self::$tenantId !== null;
    }

    public static function clear(): void
    {
        self::$tenantId = null;
    }

    /**
     * Executa um bloco no contexto de outra empresa (uso interno: cadastro, convites, seeds).
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function runAs(int $tenantId, callable $callback): mixed
    {
        $previous = self::$tenantId;
        self::set($tenantId);
        try {
            return $callback();
        } finally {
            self::$tenantId = $previous;
        }
    }
}
