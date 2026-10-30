<?php

declare(strict_types=1);

namespace Isis\Audit;

/**
 * Encadeamento de hashes da trilha de auditoria: cada registro inclui o hash do anterior,
 * então qualquer alteração ou exclusão no meio da cadeia é detectada na verificação.
 */
final class AuditHasher
{
    public const string GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    /** @param array<string, mixed> $record */
    public static function hash(string $previousHash, array $record): string
    {
        $fields = [];
        foreach (['tenant_id', 'user_id', 'user_name', 'action', 'entity_type', 'entity_id', 'ip', 'changes', 'created_at'] as $key) {
            $value = $record[$key] ?? null;
            $fields[$key] = $value === null ? null : (string) $value;
        }
        return hash('sha256', $previousHash . '|' . json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Verifica uma sequência ordenada de registros da mesma cadeia.
     *
     * @param iterable<array<string, mixed>> $records
     * @return array{ok: bool, checked: int, broken_id: ?int}
     */
    public static function verify(iterable $records): array
    {
        $previous = self::GENESIS;
        $checked = 0;
        foreach ($records as $record) {
            $checked++;
            if ($record['prev_hash'] !== $previous || self::hash($previous, $record) !== $record['hash']) {
                return ['ok' => false, 'checked' => $checked, 'broken_id' => (int) $record['id']];
            }
            $previous = $record['hash'];
        }
        return ['ok' => true, 'checked' => $checked, 'broken_id' => null];
    }
}
