<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Database;

/**
 * Relatórios de Impacto à Proteção de Dados Pessoais (RIPD) e suas versões aprovadas.
 */
final class DpiaRepository extends TenantScopedRepository
{
    protected const string TABLE = 'dpias';

    private const string BASE_SELECT = "
        SELECT d.*, u.name AS owner_name, ap.name AS approver_name,
               (SELECT COUNT(*) FROM dpia_activities x WHERE x.tenant_id = d.tenant_id AND x.dpia_id = d.id) AS activities_count,
               (SELECT MAX(v.version) FROM dpia_versions v WHERE v.tenant_id = d.tenant_id AND v.dpia_id = d.id) AS approved_version,
               (SELECT MAX(v.approved_at) FROM dpia_versions v WHERE v.tenant_id = d.tenant_id AND v.dpia_id = d.id) AS last_approved_at
          FROM dpias d
     LEFT JOIN users u ON u.tenant_id = d.tenant_id AND u.id = d.owner_user_id
     LEFT JOIN users ap ON ap.tenant_id = d.tenant_id AND ap.id = d.approved_by
         WHERE d.tenant_id = ?";

    /** @param array{q?: ?string, status?: ?string} $f */
    public function search(array $f): array
    {
        $sql = self::BASE_SELECT;
        $params = [$this->tenantId()];
        if (!empty($f['q'])) {
            $sql .= ' AND d.title LIKE ?';
            $params[] = '%' . addcslashes((string) $f['q'], '%_\\') . '%';
        }
        if (!empty($f['status'])) {
            $sql .= ' AND d.status = ?';
            $params[] = $f['status'];
        }
        $rows = Database::fetchAll($sql . ' ORDER BY d.seq DESC', $params);
        $names = $this->activityNames(array_map(static fn (array $r): int => (int) $r['id'], $rows));
        foreach ($rows as &$row) {
            $row['activities'] = $names[(int) $row['id']] ?? [];
        }
        return $rows;
    }

    /** RIPD com a lista de ids das operações cobertas. */
    public function findFull(int $id): ?array
    {
        $dpia = Database::fetch(self::BASE_SELECT . ' AND d.id = ?', [$this->tenantId(), $id]);
        if ($dpia === null) {
            return null;
        }
        $dpia['activity_ids'] = array_map('intval', array_column(Database::fetchAll(
            'SELECT activity_id FROM dpia_activities WHERE tenant_id = ? AND dpia_id = ?', [$this->tenantId(), $id]), 'activity_id'));
        $dpia['triggers'] = self::decodeTriggers($dpia['triggers']);
        return $dpia;
    }

    public function findFullOrFail(int $id): array
    {
        $dpia = $this->findFull($id);
        if ($dpia === null) {
            $this->findOrFail($id); // registra a tentativa e responde 404
        }
        return $dpia;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<int> $activityIds
     */
    public function create(array $data, array $activityIds): int
    {
        return Database::transaction(function () use ($data, $activityIds): int {
            $id = $this->insert($data + ['seq' => $this->nextSeq(), 'created_at' => date('Y-m-d H:i:s')]);
            $this->syncPivot('dpia_activities', 'dpia_id', $id, 'activity_id', $activityIds);
            return $id;
        });
    }

    /**
     * @param array<string, mixed> $data
     * @param list<int>|null $activityIds null = mantém as operações
     */
    public function save(int $id, array $data, ?array $activityIds = null): void
    {
        Database::transaction(function () use ($id, $data, $activityIds): void {
            $this->update($id, $data + ['updated_at' => date('Y-m-d H:i:s')]);
            if ($activityIds !== null) {
                $this->syncPivot('dpia_activities', 'dpia_id', $id, 'activity_id', $activityIds);
            }
        });
    }

    /** Só para RIPD que nunca foi aprovado (versões aprovadas não podem ser apagadas). */
    public function remove(int $id): void
    {
        $this->delete($id);
    }

    /** Grava a versão aprovada e marca o RIPD como aprovado, numa só transação. */
    public function approve(array $dpia, string $snapshot, int $userId, string $userName, ?string $notes): void
    {
        Database::transaction(function () use ($dpia, $snapshot, $userId, $userName, $notes): void {
            $now = date('Y-m-d H:i:s');
            Database::insert('dpia_versions', [
                'tenant_id' => $this->tenantId(), 'dpia_id' => (int) $dpia['id'], 'version' => (int) $dpia['version'],
                'snapshot' => $snapshot, 'sha256' => hash('sha256', $snapshot),
                'approved_by' => $userId, 'approved_by_name' => $userName, 'approved_at' => $now,
            ]);
            $this->update((int) $dpia['id'], [
                'status' => 'aprovado', 'approved_by' => $userId, 'approved_at' => $now,
                'approval_notes' => $notes, 'returned_notes' => null, 'updated_at' => $now,
            ]);
        });
    }

    /** @return list<array> versões aprovadas, da mais recente para a mais antiga (sem o conteúdo) */
    public function versions(int $dpiaId): array
    {
        return Database::fetchAll(
            'SELECT id, version, sha256, approved_by_name, approved_at FROM dpia_versions WHERE tenant_id = ? AND dpia_id = ? ORDER BY version DESC',
            [$this->tenantId(), $dpiaId],
        );
    }

    public function version(int $dpiaId, int $version): ?array
    {
        return Database::fetch(
            'SELECT * FROM dpia_versions WHERE tenant_id = ? AND dpia_id = ? AND version = ?',
            [$this->tenantId(), $dpiaId, $version],
        );
    }

    /** RIPDs que cobrem uma operação do inventário. */
    public function forActivity(int $activityId): array
    {
        return Database::fetchAll(
            'SELECT d.id, d.seq, d.title, d.status, d.version,
                    (SELECT MAX(v.version) FROM dpia_versions v WHERE v.tenant_id = d.tenant_id AND v.dpia_id = d.id) AS approved_version
               FROM dpias d JOIN dpia_activities x ON x.tenant_id = d.tenant_id AND x.dpia_id = d.id
              WHERE d.tenant_id = ? AND x.activity_id = ? ORDER BY d.seq DESC',
            [$this->tenantId(), $activityId],
        );
    }

    /** @return list<int> operações já cobertas por algum RIPD */
    public function coveredActivityIds(): array
    {
        return array_map('intval', array_column(Database::fetchAll(
            'SELECT DISTINCT activity_id FROM dpia_activities WHERE tenant_id = ?', [$this->tenantId()]), 'activity_id'));
    }

    /** @return array{total: int, draft: int, pending: int, approved: int, review_overdue: int} */
    public function stats(): array
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS total, SUM(status = 'rascunho') AS draft, SUM(status = 'em_aprovacao') AS pending,
                    SUM(status = 'aprovado') AS approved,
                    SUM(status = 'aprovado' AND review_due_date IS NOT NULL AND review_due_date < CURDATE()) AS review_overdue
               FROM dpias WHERE tenant_id = ?",
            [$this->tenantId()],
        ) ?? [];
        return array_map('intval', $row);
    }

    /** @return list<string> */
    public static function decodeTriggers(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        $list = json_decode((string) $raw, true);
        return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
    }

    /**
     * @param list<int> $ids
     * @return array<int, list<string>> id do RIPD => ["OP-001 · nome", ...]
     */
    private function activityNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = Database::fetchAll(
            'SELECT x.dpia_id, a.seq, a.name FROM dpia_activities x
               JOIN processing_activities a ON a.tenant_id = x.tenant_id AND a.id = x.activity_id
              WHERE x.tenant_id = ? AND x.dpia_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY a.seq',
            [$this->tenantId(), ...$ids],
        );
        $names = [];
        foreach ($rows as $row) {
            $names[(int) $row['dpia_id']][] = code('OP', (int) $row['seq']) . ' · ' . $row['name'];
        }
        return $names;
    }
}
