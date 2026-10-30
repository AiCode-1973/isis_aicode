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

    /** Remove o setor desvinculando usuários e convites antes (FKs compostas são RESTRICT). */
    public function remove(int $id): void
    {
        Database::transaction(function () use ($id): void {
            Database::execute('UPDATE users SET department_id = NULL WHERE tenant_id = ? AND department_id = ?', [$this->tenantId(), $id]);
            Database::execute('UPDATE invitations SET department_id = NULL WHERE tenant_id = ? AND department_id = ?', [$this->tenantId(), $id]);
            $this->delete($id);
        });
    }

    public function total(): int
    {
        return $this->count();
    }
}
