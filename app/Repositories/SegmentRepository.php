<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Database;

/**
 * Segmentos de atuação (catálogo global) e seus modelos de sugestões.
 */
final class SegmentRepository
{
    /** @return array<string, string> código => nome */
    public static function options(): array
    {
        $options = [];
        foreach (Database::fetchAll('SELECT code, name FROM segments ORDER BY sort_order, name') as $row) {
            $options[$row['code']] = $row['name'];
        }
        return $options;
    }

    public static function find(string $code): ?array
    {
        $row = Database::fetch('SELECT * FROM segments WHERE code = ?', [$code]);
        if ($row !== null) {
            $row['template'] = json_decode((string) $row['template'], true) ?: [];
        }
        return $row;
    }

    /** @return array<string, mixed> */
    public static function template(string $code): array
    {
        return self::find($code)['template'] ?? [];
    }
}
