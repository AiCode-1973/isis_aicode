<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Database;

final class DepartmentRepository extends TenantScopedRepository
{
    protected const string TABLE = 'departments';

    public function all(): array
    {
        return Database::fetchAll(
            'SELECT d.*, (SELECT COUNT(*) FROM users u WHERE u.tenant_id = d.tenant_id AND u.department_id = d.id) AS users_count
               FROM departments d WHERE d.tenant_id = ? ORDER BY d.name',
            [$this->tenantId()],
        );
    }

    /** @return array<int, string> id => nome */
    public function options(): array
    {
        $options = [];
        foreach ($this->select('', [], 'name ASC') as $row) {
            $options[(int) $row['id']] = $row['name'];
        }
        return $options;
    }

    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        return $this->count('name = ? AND id <> ?', [$name, $exceptId ?? 0]) > 0;
    }

    public function create(string $name): int
    {
        return $this->insert(['name' => $name, 'created_at' => date('Y-m-d H:i:s')]);
    }

    public function rename(int $id, string $name): void
    {
        $this->update($id, ['name' => $name]);
    }

    /** Tabelas com coluna department_id (FKs compostas RESTRICT), desvinculadas antes de excluir o setor. */
    private const array LINKED = ['users', 'invitations', 'processing_activities', 'risks', 'actions', 'incidents', 'vendors'];

    /** Remove o setor; os registros ligados a ele ficam sem setor. */
    public function remove(int $id): void
    {
        Database::transaction(function () use ($id): void {
            foreach (self::LINKED as $table) {
                Database::execute("UPDATE `{$table}` SET department_id = NULL WHERE tenant_id = ? AND department_id = ?", [$this->tenantId(), $id]);
            }
            $this->delete($id);
        });
    }

    public function total(): int
    {
        return $this->count();
    }
}
