<?php

declare(strict_types=1);

namespace Isis\Audit;

use Isis\Auth\Auth;
use Isis\Auth\TenantContext;
use Isis\Core\Database;

/**
 * Registra ações na trilha de auditoria (somente inclusão), com usuário, data/hora, IP e alterações (antes/depois).
 */
final class AuditLogger
{
    /** Campos nunca gravados em claro na auditoria. */
    private const array SENSITIVE = ['password', 'password_hash', 'password_confirmation', 'current_password', 'token', 'token_hash', '_token'];

    private static ?string $ip = null;
    private static ?string $userAgent = null;

    public static function setRequestInfo(string $ip, string $userAgent): void
    {
        self::$ip = $ip;
        self::$userAgent = $userAgent;
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     * @param array{tenant_id?: ?int, user_id?: ?int, user_name?: ?string} $actor Sobrescreve o usuário/empresa da sessão
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null,
        array $actor = [],
    ): void {
        $user = Auth::user();
        $tenantId = array_key_exists('tenant_id', $actor) ? $actor['tenant_id'] : (TenantContext::has() ? TenantContext::id() : null);
        $userId = array_key_exists('user_id', $actor) ? $actor['user_id'] : ($user['id'] ?? null);
        $userName = array_key_exists('user_name', $actor) ? $actor['user_name'] : ($user['name'] ?? null);

        $changes = self::diff($before, $after);
        $record = [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'user_name' => $userName,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip' => self::$ip,
            'changes' => $changes === null ? null : json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ];

        Database::transaction(function () use ($record): void {
            $chainKey = (int) ($record['tenant_id'] ?? 0);
            Database::execute('INSERT IGNORE INTO audit_chain (chain_key, last_hash) VALUES (?, ?)', [$chainKey, AuditHasher::GENESIS]);
            $previous = (string) Database::value('SELECT last_hash FROM audit_chain WHERE chain_key = ? FOR UPDATE', [$chainKey]);
            $hash = AuditHasher::hash($previous, $record);
            Database::insert('audit_logs', $record + [
                'user_agent' => self::$userAgent,
                'prev_hash' => $previous,
                'hash' => $hash,
            ]);
            Database::execute('UPDATE audit_chain SET last_hash = ? WHERE chain_key = ?', [$hash, $chainKey]);
        });
    }

    /**
     * Alterações entre dois estados. Criação: só "depois"; exclusão: só "antes"; edição: campos alterados.
     *
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     * @return array<string, mixed>|null
     */
    public static function diff(?array $before, ?array $after): ?array
    {
        if ($before === null && $after === null) {
            return null;
        }
        if ($before === null) {
            return ['depois' => self::mask($after ?? [])];
        }
        if ($after === null) {
            return ['antes' => self::mask($before)];
        }
        $changed = [];
        foreach ($after as $key => $value) {
            $old = $before[$key] ?? null;
            if (self::normalize($old) !== self::normalize($value)) {
                $changed[$key] = ['de' => self::maskValue((string) $key, $old), 'para' => self::maskValue((string) $key, $value)];
            }
        }
        return $changed === [] ? null : $changed;
    }

    /** Compara 5 e "5" como iguais (valores vindos do banco chegam como texto). */
    private static function normalize(mixed $value): string
    {
        return ($value === null || is_scalar($value)) ? (string) $value : (string) json_encode($value);
    }

    /** @param array<string, mixed> $data */
    private static function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = self::maskValue((string) $key, $value);
        }
        return $data;
    }

    private static function maskValue(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (in_array($key, self::SENSITIVE, true) || str_ends_with($key, '_enc')) {
            return '[protegido]';
        }
        return $value;
    }
}
