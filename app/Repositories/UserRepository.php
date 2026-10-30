<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Database;

final class UserRepository extends TenantScopedRepository
{
    protected const string TABLE = 'users';

    /** Listagem da empresa com o nome do setor. */
    public function listWithDepartment(): array
    {
        return Database::fetchAll(
            'SELECT u.*, d.name AS department_name
               FROM users u
          LEFT JOIN departments d ON d.tenant_id = u.tenant_id AND d.id = u.department_id
              WHERE u.tenant_id = ?
           ORDER BY u.status ASC, u.name ASC',
            [$this->tenantId()],
        );
    }

    /** @return list<array{id: int, name: string}> */
    public function options(): array
    {
        return Database::fetchAll(
            'SELECT id, name FROM users WHERE tenant_id = ? ORDER BY name',
            [$this->tenantId()],
        );
    }

    public function countActive(): int
    {
        return $this->count("status = 'active'");
    }

    public function countActiveAdmins(): int
    {
        return $this->count("status = 'active' AND role = 'admin'");
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return $this->insert($data + ['created_at' => date('Y-m-d H:i:s')]);
    }

    /** @param array<string, mixed> $data */
    public function save(int $id, array $data): void
    {
        $this->update($id, $data + ['updated_at' => date('Y-m-d H:i:s')]);
    }

    /** Invalida as outras sessões do usuário (troca de senha, desativação, "sair de todos"). */
    public function bumpSessionEpoch(int $id): int
    {
        Database::execute(
            'UPDATE users SET session_epoch = session_epoch + 1 WHERE tenant_id = ? AND id = ?',
            [$this->tenantId(), $id],
        );
        return (int) Database::value('SELECT session_epoch FROM users WHERE tenant_id = ? AND id = ?', [$this->tenantId(), $id]);
    }

    // ---- Consultas globais: usadas só antes de existir empresa na sessão (login, cadastro, recuperação) ----

    public static function findByEmailGlobal(string $email): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    public static function findByIdGlobal(int $id): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function emailExists(string $email): bool
    {
        return Database::value('SELECT 1 FROM users WHERE email = ?', [mb_strtolower(trim($email))]) !== null;
    }
}
