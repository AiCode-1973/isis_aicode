<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Database;

/**
 * Administradores da plataforma (equipe AiCode) e configurações globais de cobrança.
 */
final class PlatformRepository
{
    public function findAdminByEmail(string $email): ?array
    {
        return Database::fetch('SELECT * FROM platform_admins WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    public function findAdmin(int $id): ?array
    {
        return Database::fetch('SELECT * FROM platform_admins WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function activeAdmins(): array
    {
        return Database::fetchAll("SELECT * FROM platform_admins WHERE status = 'active' ORDER BY name");
    }

    /** Cria ou atualiza (senha e nome) o administrador com este e-mail. Retorna o id. */
    public function saveAdmin(string $name, string $email, string $passwordHash): int
    {
        $email = mb_strtolower(trim($email));
        $existing = $this->findAdminByEmail($email);
        if ($existing !== null) {
            Database::execute("UPDATE platform_admins SET name = ?, password_hash = ?, status = 'active' WHERE id = ?", [$name, $passwordHash, $existing['id']]);
            return (int) $existing['id'];
        }
        return Database::insert('platform_admins', ['name' => $name, 'email' => $email, 'password_hash' => $passwordHash]);
    }

    public function touchLogin(int $id): void
    {
        Database::execute('UPDATE platform_admins SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        Database::execute('UPDATE platform_admins SET password_hash = ? WHERE id = ?', [$passwordHash, $id]);
    }

    public function setting(string $name, ?string $default = null): ?string
    {
        $value = Database::value('SELECT value FROM platform_settings WHERE name = ?', [$name]);
        return $value === null || $value === '' ? $default : (string) $value;
    }

    public function saveSetting(string $name, ?string $value): void
    {
        Database::execute(
            'INSERT INTO platform_settings (name, value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()',
            [$name, $value],
        );
    }
}
