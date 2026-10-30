<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Auth\TenantContext;
use Isis\Core\Database;

/**
 * Base para tabelas com dados institucionais. Toda leitura e escrita é filtrada por tenant_id
 * da empresa da sessão; o tenant_id recebido de fora é sempre descartado.
 */
abstract class TenantScopedRepository
{
    /** Nome da tabela; definido em cada repositório. */
    protected const string TABLE = '';

    protected function table(): string
    {
        Database::assertIdentifier(static::TABLE);
        return static::TABLE;
    }

    protected function tenantId(): int
    {
        return TenantContext::id();
    }

    public function find(int $id): ?array
    {
        return Database::fetch(
            "SELECT * FROM `{$this->table()}` WHERE tenant_id = ? AND id = ?",
            [$this->tenantId(), $id],
        );
    }

    /** Busca na empresa atual; registro de outra empresa é tratado como inexistente (404). */
    public function findOrFail(int $id): array
    {
        $row = $this->find($id);
        if ($row === null) {
            \Isis\Audit\AuditLogger::log('access.not_found', $this->table(), $id);
            throw new \Isis\Core\HttpException(404);
        }
        return $row;
    }

    /**
     * SELECT com o filtro da empresa já aplicado. $where é anexado após "tenant_id = ?".
     *
     * @param list<mixed> $params
     */
    protected function select(string $where = '', array $params = [], string $orderBy = 'id DESC', ?int $limit = null, int $offset = 0): array
    {
        $sql = "SELECT * FROM `{$this->table()}` WHERE tenant_id = ?" . ($where !== '' ? " AND ({$where})" : '');
        $sql .= " ORDER BY {$orderBy}";
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
        }
        return Database::fetchAll($sql, [$this->tenantId(), ...$params]);
    }

    /** @param list<mixed> $params */
    protected function count(string $where = '', array $params = []): int
    {
        $sql = "SELECT COUNT(*) FROM `{$this->table()}` WHERE tenant_id = ?" . ($where !== '' ? " AND ({$where})" : '');
        return (int) Database::value($sql, [$this->tenantId(), ...$params]);
    }

    /** @param array<string, mixed> $data */
    protected function insert(array $data): int
    {
        unset($data['id']);
        $data['tenant_id'] = $this->tenantId();
        return Database::insert($this->table(), $data);
    }

    /** @param array<string, mixed> $data */
    protected function update(int $id, array $data): int
    {
        unset($data['id'], $data['tenant_id']);
        if ($data === []) {
            return 0;
        }
        $sets = [];
        foreach (array_keys($data) as $column) {
            Database::assertIdentifier($column);
            $sets[] = "`{$column}` = ?";
        }
        return Database::execute(
            "UPDATE `{$this->table()}` SET " . implode(', ', $sets) . ' WHERE tenant_id = ? AND id = ?',
            [...array_values($data), $this->tenantId(), $id],
        );
    }

    /** Próximo número sequencial da empresa (códigos como OP-001). Use dentro de transação. */
    protected function nextSeq(): int
    {
        return (int) Database::value(
            "SELECT COALESCE(MAX(seq), 0) + 1 FROM `{$this->table()}` WHERE tenant_id = ? FOR UPDATE",
            [$this->tenantId()],
        );
    }

    /**
     * Substitui os vínculos N:N de um registro (tabela de ligação com tenant_id).
     *
     * @param list<int> $ids
     */
    protected function syncPivot(string $pivot, string $ownerColumn, int $ownerId, string $itemColumn, array $ids): void
    {
        array_map(Database::assertIdentifier(...), [$pivot, $ownerColumn, $itemColumn]);
        Database::execute("DELETE FROM `{$pivot}` WHERE tenant_id = ? AND `{$ownerColumn}` = ?", [$this->tenantId(), $ownerId]);
        foreach (array_unique($ids) as $id) {
            Database::execute(
                "INSERT INTO `{$pivot}` (tenant_id, `{$ownerColumn}`, `{$itemColumn}`) VALUES (?, ?, ?)",
                [$this->tenantId(), $ownerId, $id],
            );
        }
    }

    protected function delete(int $id): int
    {
        return Database::execute(
            "DELETE FROM `{$this->table()}` WHERE tenant_id = ? AND id = ?",
            [$this->tenantId(), $id],
        );
    }
}
