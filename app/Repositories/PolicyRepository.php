<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Database;

/**
 * Políticas e documentos: versões com aprovação, validade e ciência dos colaboradores.
 */
final class PolicyRepository extends TenantScopedRepository
{
    protected const string TABLE = 'policies';

    private const string BASE_SELECT = "
        SELECT p.*, u.name AS owner_name,
               cv.version_number AS current_number, cv.valid_until, cv.decided_at AS approved_at,
               (SELECT v.status FROM policy_versions v WHERE v.tenant_id = p.tenant_id AND v.policy_id = p.id ORDER BY v.version_number DESC LIMIT 1) AS latest_status,
               (SELECT v.version_number FROM policy_versions v WHERE v.tenant_id = p.tenant_id AND v.policy_id = p.id ORDER BY v.version_number DESC LIMIT 1) AS latest_number
          FROM policies p
     LEFT JOIN users u ON u.tenant_id = p.tenant_id AND u.id = p.owner_user_id
     LEFT JOIN policy_versions cv ON cv.tenant_id = p.tenant_id AND cv.id = p.current_version_id
         WHERE p.tenant_id = ?";

    /** Lista com a situação de ciência do usuário informado. */
    public function search(int $userId, bool $onlyPublished): array
    {
        $sql = self::BASE_SELECT . ' AND p.archived_at IS NULL';
        if ($onlyPublished) {
            $sql .= ' AND p.current_version_id IS NOT NULL';
        }
        $rows = Database::fetchAll($sql . ' ORDER BY p.title', [$this->tenantId()]);
        $acked = array_flip(array_map('intval', array_column(Database::fetchAll(
            'SELECT version_id FROM policy_acknowledgements WHERE tenant_id = ? AND user_id = ?', [$this->tenantId(), $userId]), 'version_id')));
        $active = $this->activeUsers();
        foreach ($rows as &$row) {
            $row['acked_by_me'] = $row['current_version_id'] !== null && isset($acked[(int) $row['current_version_id']]);
            $row['ack_count'] = $row['current_version_id'] !== null ? $this->ackCount((int) $row['current_version_id']) : 0;
            $row['active_users'] = $active;
        }
        return $rows;
    }

    public function findFull(int $id): ?array
    {
        return Database::fetch(self::BASE_SELECT . ' AND p.id = ?', [$this->tenantId(), $id]);
    }

    public function findFullOrFail(int $id): array
    {
        return $this->findFull($id) ?? $this->findOrFail($id);
    }

    public function versions(int $policyId): array
    {
        return Database::fetchAll(
            'SELECT v.*, a.name AS author_name, d.name AS decider_name FROM policy_versions v
          LEFT JOIN users a ON a.tenant_id = v.tenant_id AND a.id = v.created_by
          LEFT JOIN users d ON d.tenant_id = v.tenant_id AND d.id = v.decided_by
              WHERE v.tenant_id = ? AND v.policy_id = ? ORDER BY v.version_number DESC',
            [$this->tenantId(), $policyId],
        );
    }

    public function version(int $versionId): ?array
    {
        return Database::fetch('SELECT * FROM policy_versions WHERE tenant_id = ? AND id = ?', [$this->tenantId(), $versionId]);
    }

    /** Última versão ainda editável (rascunho ou devolvida), se houver. */
    public function editableVersion(int $policyId): ?array
    {
        return Database::fetch(
            "SELECT * FROM policy_versions WHERE tenant_id = ? AND policy_id = ? AND status IN ('rascunho','rejeitada')
              ORDER BY version_number DESC LIMIT 1",
            [$this->tenantId(), $policyId],
        );
    }

    public function pendingApproval(int $policyId): ?array
    {
        return Database::fetch(
            "SELECT * FROM policy_versions WHERE tenant_id = ? AND policy_id = ? AND status = 'em_aprovacao' ORDER BY version_number DESC LIMIT 1",
            [$this->tenantId(), $policyId],
        );
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, string $content, ?string $summary, ?int $userId): int
    {
        return Database::transaction(function () use ($data, $content, $summary, $userId): int {
            $id = $this->insert($data + ['seq' => $this->nextSeq(), 'created_by' => $userId, 'created_at' => date('Y-m-d H:i:s')]);
            $this->insertVersion($id, 1, $content, $summary, $userId);
            return $id;
        });
    }

    /** @param array<string, mixed> $data */
    public function save(int $id, array $data): void
    {
        $this->update($id, $data + ['updated_at' => date('Y-m-d H:i:s')]);
    }

    /** Salva o texto no rascunho atual ou abre uma nova versão a partir da vigente. Retorna o id da versão. */
    public function saveDraft(int $policyId, string $content, ?string $summary, ?int $userId): int
    {
        $draft = $this->editableVersion($policyId);
        if ($draft !== null) {
            Database::execute(
                "UPDATE policy_versions SET content = ?, change_summary = ?, status = 'rascunho', decision_notes = NULL, updated_at = NOW()
                  WHERE tenant_id = ? AND id = ?",
                [$content, $summary, $this->tenantId(), $draft['id']],
            );
            return (int) $draft['id'];
        }
        $next = (int) Database::value('SELECT COALESCE(MAX(version_number), 0) + 1 FROM policy_versions WHERE tenant_id = ? AND policy_id = ?', [$this->tenantId(), $policyId]);
        return $this->insertVersion($policyId, $next, $content, $summary, $userId);
    }

    public function setVersionStatus(int $versionId, string $status, array $extra = []): void
    {
        $sets = ['status = ?'];
        $params = [$status];
        foreach ($extra as $column => $value) {
            \Isis\Core\Database::assertIdentifier($column);
            $sets[] = "`{$column}` = ?";
            $params[] = $value;
        }
        Database::execute('UPDATE policy_versions SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE tenant_id = ? AND id = ?',
            [...$params, $this->tenantId(), $versionId]);
    }

    /** Aprova a versão: a anterior vira "substituída" e esta passa a ser a vigente. */
    public function approve(array $policy, array $version, int $userId, ?string $notes): string
    {
        $validUntil = date('Y-m-d', strtotime('+' . max(1, (int) $policy['review_months']) . ' months'));
        Database::transaction(function () use ($policy, $version, $userId, $notes, $validUntil): void {
            Database::execute("UPDATE policy_versions SET status = 'substituida' WHERE tenant_id = ? AND policy_id = ? AND status = 'aprovada'",
                [$this->tenantId(), $policy['id']]);
            $this->setVersionStatus((int) $version['id'], 'aprovada', [
                'decided_by' => $userId, 'decided_at' => date('Y-m-d H:i:s'), 'decision_notes' => $notes, 'valid_until' => $validUntil,
            ]);
            $this->update((int) $policy['id'], ['current_version_id' => (int) $version['id'], 'updated_at' => date('Y-m-d H:i:s')]);
        });
        return $validUntil;
    }

    public function acknowledge(int $versionId, int $userId, string $ip): bool
    {
        return Database::execute(
            'INSERT IGNORE INTO policy_acknowledgements (tenant_id, version_id, user_id, acknowledged_at, ip) VALUES (?, ?, ?, NOW(), ?)',
            [$this->tenantId(), $versionId, $userId, $ip],
        ) === 1;
    }

    public function hasAcknowledged(int $versionId, int $userId): ?string
    {
        $value = Database::value('SELECT acknowledged_at FROM policy_acknowledgements WHERE tenant_id = ? AND version_id = ? AND user_id = ?',
            [$this->tenantId(), $versionId, $userId]);
        return $value === null ? null : (string) $value;
    }

    /** Ciência por usuário ativo da versão vigente. */
    public function acknowledgements(int $versionId): array
    {
        return Database::fetchAll(
            "SELECT u.id, u.name, d.name AS department_name, a.acknowledged_at
               FROM users u
          LEFT JOIN departments d ON d.tenant_id = u.tenant_id AND d.id = u.department_id
          LEFT JOIN policy_acknowledgements a ON a.tenant_id = u.tenant_id AND a.user_id = u.id AND a.version_id = ?
              WHERE u.tenant_id = ? AND u.status = 'active'
           ORDER BY (a.acknowledged_at IS NULL) DESC, u.name",
            [$versionId, $this->tenantId()],
        );
    }

    /** Políticas vigentes que exigem ciência e o usuário ainda não deu. */
    public function pendingForUser(int $userId): array
    {
        return Database::fetchAll(
            'SELECT p.id, p.seq, p.title FROM policies p
              WHERE p.tenant_id = ? AND p.archived_at IS NULL AND p.requires_ack = 1 AND p.current_version_id IS NOT NULL
                AND NOT EXISTS (SELECT 1 FROM policy_acknowledgements a WHERE a.tenant_id = p.tenant_id AND a.version_id = p.current_version_id AND a.user_id = ?)
           ORDER BY p.title',
            [$this->tenantId(), $userId],
        );
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS total,
                    SUM(p.current_version_id IS NOT NULL) AS published,
                    SUM(cv.valid_until < CURDATE()) AS expired,
                    SUM(cv.valid_until BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY) AS expiring,
                    SUM(EXISTS (SELECT 1 FROM policy_versions v WHERE v.tenant_id = p.tenant_id AND v.policy_id = p.id AND v.status = 'em_aprovacao')) AS awaiting
               FROM policies p
          LEFT JOIN policy_versions cv ON cv.tenant_id = p.tenant_id AND cv.id = p.current_version_id
              WHERE p.tenant_id = ? AND p.archived_at IS NULL",
            [$this->tenantId()],
        ) ?? [];
        $ack = Database::fetch(
            "SELECT COUNT(*) AS expected, SUM(a.id IS NOT NULL) AS done
               FROM policies p
               JOIN users u ON u.tenant_id = p.tenant_id AND u.status = 'active'
          LEFT JOIN policy_acknowledgements a ON a.tenant_id = p.tenant_id AND a.version_id = p.current_version_id AND a.user_id = u.id
              WHERE p.tenant_id = ? AND p.archived_at IS NULL AND p.requires_ack = 1 AND p.current_version_id IS NOT NULL",
            [$this->tenantId()],
        ) ?? [];
        return array_map('intval', $row) + ['ack_expected' => (int) ($ack['expected'] ?? 0), 'ack_done' => (int) ($ack['done'] ?? 0)];
    }

    public function titleExists(string $title): bool
    {
        return $this->count('title = ? AND archived_at IS NULL', [$title]) > 0;
    }

    private function insertVersion(int $policyId, int $number, string $content, ?string $summary, ?int $userId): int
    {
        return Database::insert('policy_versions', [
            'tenant_id' => $this->tenantId(), 'policy_id' => $policyId, 'version_number' => $number, 'content' => $content,
            'change_summary' => $summary, 'status' => 'rascunho', 'created_by' => $userId, 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function ackCount(int $versionId): int
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM policy_acknowledgements a JOIN users u ON u.tenant_id = a.tenant_id AND u.id = a.user_id AND u.status = 'active'
              WHERE a.tenant_id = ? AND a.version_id = ?",
            [$this->tenantId(), $versionId],
        );
    }

    private function activeUsers(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'active'", [$this->tenantId()]);
    }
}
