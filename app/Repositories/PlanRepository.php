<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Database;

/**
 * Planos comerciais (tabela global, editada só pelo painel da plataforma).
 */
final class PlanRepository
{
    /** @return list<array<string, mixed>> */
    public function all(bool $onlyPublic = false): array
    {
        $rows = Database::fetchAll('SELECT * FROM plans' . ($onlyPublic ? ' WHERE is_public = 1' : '') . ' ORDER BY sort_order, name');
        return array_map(self::hydrate(...), $rows);
    }

    public function find(string $code): ?array
    {
        $row = Database::fetch('SELECT * FROM plans WHERE code = ?', [$code]);
        return $row === null ? null : self::hydrate($row);
    }

    /** @return array<string, string> código => nome */
    public function options(): array
    {
        return array_column($this->all(), 'name', 'code');
    }

    /** @param array<string, mixed> $data */
    public function update(string $code, array $data): void
    {
        $allowed = ['name', 'description', 'price_monthly_cents', 'price_yearly_cents', 'max_users', 'max_vendors', 'max_trainings', 'contact_only', 'is_public', 'highlights', 'sort_order'];
        $data = array_intersect_key($data, array_flip($allowed));
        if (isset($data['highlights']) && is_array($data['highlights'])) {
            $data['highlights'] = json_encode(array_values($data['highlights']), JSON_UNESCAPED_UNICODE);
        }
        $sets = implode(', ', array_map(static fn (string $c): string => "`{$c}` = ?", array_keys($data)));
        Database::execute("UPDATE plans SET {$sets}, updated_at = NOW() WHERE code = ?", [...array_values($data), $code]);
    }

    private static function hydrate(array $row): array
    {
        $row['highlights'] = json_decode((string) ($row['highlights'] ?? '[]'), true) ?: [];
        foreach (['price_monthly_cents', 'price_yearly_cents', 'max_users', 'max_vendors', 'max_trainings'] as $field) {
            $row[$field] = $row[$field] === null ? null : (int) $row[$field];
        }
        $row['contact_only'] = (bool) $row['contact_only'];
        $row['is_public'] = (bool) $row['is_public'];
        return $row;
    }
}
