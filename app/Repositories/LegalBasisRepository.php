<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Database;

/**
 * Hipóteses legais da LGPD (catálogo global).
 */
final class LegalBasisRepository
{
    /** @return array<string, array> código => linha */
    public static function all(bool $sensitive): array
    {
        $rows = [];
        foreach (Database::fetchAll('SELECT * FROM legal_bases WHERE is_sensitive = ? ORDER BY sort_order', [$sensitive ? 1 : 0]) as $row) {
            $rows[$row['code']] = $row;
        }
        return $rows;
    }
}
