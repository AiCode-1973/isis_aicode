<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Auth\TenantContext;
use Isis\Core\Database;

/**
 * Empresas (tabela raiz do multiempresa). Fora do cadastro, só acessa a empresa da sessão.
 */
final class TenantRepository
{
    public function current(): array
    {
        $tenant = $this->findById(TenantContext::id());
        if ($tenant === null) {
            throw new \LogicException('Empresa da sessão não encontrada.');
        }
        return $tenant;
    }

    /** Uso interno (autenticação e cadastro). */
    public function findById(int $id): ?array
    {
        return Database::fetch(
            'SELECT t.*, s.name AS segment_name FROM tenants t JOIN segments s ON s.code = t.segment_code WHERE t.id = ?',
            [$id],
        );
    }

    public function cnpjExists(string $cnpj): bool
    {
        return Database::value('SELECT 1 FROM tenants WHERE cnpj = ?', [$cnpj]) !== null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $data['slug'] = $this->uniqueSlug((string) ($data['trade_name'] ?: $data['legal_name']));
        return Database::insert('tenants', $data);
    }

    /** @param array<string, mixed> $data */
    public function updateCurrent(array $data): void
    {
        $allowed = ['legal_name', 'trade_name', 'segment_code', 'dpo_name', 'dpo_email', 'privacy_email', 'onboarding_completed_at'];
        $data = array_intersect_key($data, array_flip($allowed));
        if ($data === []) {
            return;
        }
        $sets = implode(', ', array_map(static fn (string $c): string => "`{$c}` = ?", array_keys($data)));
        Database::execute("UPDATE tenants SET {$sets} WHERE id = ?", [...array_values($data), TenantContext::id()]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = \Isis\Support\Text::slug($name);
        $base = trim(substr($base !== '' ? $base : 'empresa', 0, 60), '-');
        $slug = $base;
        for ($i = 2; Database::value('SELECT 1 FROM tenants WHERE slug = ?', [$slug]) !== null; $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }
}
