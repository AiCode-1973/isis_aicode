<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Database;

/**
 * Evidências anexadas aos registros (ações, riscos, operações...).
 */
final class AttachmentRepository extends TenantScopedRepository
{
    protected const string TABLE = 'attachments';

    public function forEntity(string $type, int $id): array
    {
        return Database::fetchAll(
            'SELECT t.*, u.name AS uploader_name FROM attachments t
          LEFT JOIN users u ON u.tenant_id = t.tenant_id AND u.id = t.uploaded_by
              WHERE t.tenant_id = ? AND t.entity_type = ? AND t.entity_id = ? ORDER BY t.id DESC',
            [$this->tenantId(), $type, $id],
        );
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return $this->insert($data + ['created_at' => date('Y-m-d H:i:s')]);
    }

    public function remove(int $id): void
    {
        $this->delete($id);
    }

    public function removeForEntity(string $type, int $id): array
    {
        $files = $this->forEntity($type, $id);
        Database::execute('DELETE FROM attachments WHERE tenant_id = ? AND entity_type = ? AND entity_id = ?', [$this->tenantId(), $type, $id]);
        return $files;
    }
}
