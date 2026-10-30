<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Database;

final class InvitationRepository extends TenantScopedRepository
{
    protected const string TABLE = 'invitations';

    public function pending(): array
    {
        return Database::fetchAll(
            'SELECT i.*, u.name AS invited_by_name, d.name AS department_name
               FROM invitations i
          LEFT JOIN users u ON u.tenant_id = i.tenant_id AND u.id = i.invited_by
          LEFT JOIN departments d ON d.tenant_id = i.tenant_id AND d.id = i.department_id
              WHERE i.tenant_id = ? AND i.accepted_at IS NULL AND i.revoked_at IS NULL
           ORDER BY i.created_at DESC',
            [$this->tenantId()],
        );
    }

    public function countPending(): int
    {
        return $this->count('accepted_at IS NULL AND revoked_at IS NULL AND expires_at > NOW()');
    }

    public function pendingForEmail(string $email): ?array
    {
        return $this->select('email = ? AND accepted_at IS NULL AND revoked_at IS NULL', [$email], 'id DESC', 1)[0] ?? null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return $this->insert($data + ['created_at' => date('Y-m-d H:i:s')]);
    }

    public function renew(int $id, string $tokenHash, string $expiresAt): void
    {
        $this->update($id, ['token_hash' => $tokenHash, 'expires_at' => $expiresAt]);
    }

    public function revoke(int $id): void
    {
        $this->update($id, ['revoked_at' => date('Y-m-d H:i:s')]);
    }

    public function markAccepted(int $id): void
    {
        $this->update($id, ['accepted_at' => date('Y-m-d H:i:s')]);
    }

    /** Global: o convidado ainda não tem sessão; a empresa vem do próprio convite. */
    public static function findValidByTokenHash(string $hash): ?array
    {
        return Database::fetch(
            'SELECT i.*, t.legal_name, t.trade_name, t.status AS tenant_status
               FROM invitations i JOIN tenants t ON t.id = i.tenant_id
              WHERE i.token_hash = ? AND i.accepted_at IS NULL AND i.revoked_at IS NULL AND i.expires_at > NOW()',
            [$hash],
        );
    }
}
