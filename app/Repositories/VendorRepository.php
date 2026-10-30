<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Database;

/**
 * Fornecedores e terceiros que tratam ou recebem dados pessoais, com avaliações e contratos.
 */
final class VendorRepository extends TenantScopedRepository
{
    protected const string TABLE = 'vendors';

    private const string BASE_SELECT = "
        SELECT v.*, u.name AS owner_name, d.name AS department_name,
               (SELECT MIN(c.end_date) FROM vendor_contracts c WHERE c.tenant_id = v.tenant_id AND c.vendor_id = v.id AND c.end_date >= CURDATE()) AS next_contract_end,
               (SELECT COUNT(*) FROM vendor_contracts c WHERE c.tenant_id = v.tenant_id AND c.vendor_id = v.id) AS contracts_count,
               (SELECT COUNT(*) FROM vendor_contracts c WHERE c.tenant_id = v.tenant_id AND c.vendor_id = v.id AND c.has_data_clauses = 1) AS dpa_count,
               (SELECT COUNT(*) FROM vendor_activities va WHERE va.tenant_id = v.tenant_id AND va.vendor_id = v.id) AS activities_count
          FROM vendors v
     LEFT JOIN users u ON u.tenant_id = v.tenant_id AND u.id = v.owner_user_id
     LEFT JOIN departments d ON d.tenant_id = v.tenant_id AND d.id = v.department_id
         WHERE v.tenant_id = ?";

    /** @param array{q?: ?string, status?: ?string, risk?: ?string, unassessed?: bool} $f */
    public function search(array $f): array
    {
        $sql = self::BASE_SELECT;
        $params = [$this->tenantId()];
        if (!empty($f['q'])) {
            $sql .= ' AND (v.name LIKE ? OR v.service LIKE ?)';
            $like = '%' . addcslashes((string) $f['q'], '%_\\') . '%';
            array_push($params, $like, $like);
        }
        if (!empty($f['status'])) {
            $sql .= ' AND v.status = ?';
            $params[] = $f['status'];
        }
        if (!empty($f['risk'])) {
            $sql .= ' AND v.risk_level = ?';
            $params[] = $f['risk'];
        }
        if (!empty($f['unassessed'])) {
            $sql .= ' AND v.last_assessment_at IS NULL';
        }
        $sql .= " ORDER BY (v.status = 'encerrado') ASC, FIELD(v.risk_level, 'alto', 'medio', 'baixo') ASC, v.name ASC";
        return Database::fetchAll($sql, $params);
    }

    public function findFull(int $id): ?array
    {
        $vendor = Database::fetch(self::BASE_SELECT . ' AND v.id = ?', [$this->tenantId(), $id]);
        if ($vendor === null) {
            return null;
        }
        $vendor['activities'] = Database::fetchAll(
            'SELECT a.id, a.seq, a.name FROM vendor_activities va JOIN processing_activities a ON a.tenant_id = va.tenant_id AND a.id = va.activity_id
              WHERE va.tenant_id = ? AND va.vendor_id = ? ORDER BY a.seq', [$this->tenantId(), $id]);
        $vendor['contracts'] = Database::fetchAll(
            'SELECT * FROM vendor_contracts WHERE tenant_id = ? AND vendor_id = ? ORDER BY COALESCE(end_date, \'9999-12-31\') DESC', [$this->tenantId(), $id]);
        $vendor['assessments'] = Database::fetchAll(
            'SELECT a.*, u.name AS assessed_by_name FROM vendor_assessments a LEFT JOIN users u ON u.tenant_id = a.tenant_id AND u.id = a.assessed_by
              WHERE a.tenant_id = ? AND a.vendor_id = ? ORDER BY a.id DESC', [$this->tenantId(), $id]);
        foreach ($vendor['assessments'] as &$assessment) {
            $assessment['answers'] = json_decode((string) $assessment['answers'], true) ?: [];
        }
        return $vendor;
    }

    public function findFullOrFail(int $id): array
    {
        return $this->findFull($id) ?? $this->findOrFail($id);
    }

    /** @param array<string, mixed> $data @param list<int> $activities */
    public function create(array $data, array $activities): int
    {
        return Database::transaction(function () use ($data, $activities): int {
            $id = $this->insert($data + ['seq' => $this->nextSeq(), 'created_at' => date('Y-m-d H:i:s')]);
            $this->syncPivot('vendor_activities', 'vendor_id', $id, 'activity_id', $activities);
            return $id;
        });
    }

    /** @param array<string, mixed> $data @param list<int>|null $activities */
    public function save(int $id, array $data, ?array $activities = null): void
    {
        Database::transaction(function () use ($id, $data, $activities): void {
            $this->update($id, $data + ['updated_at' => date('Y-m-d H:i:s')]);
            if ($activities !== null) {
                $this->syncPivot('vendor_activities', 'vendor_id', $id, 'activity_id', $activities);
            }
        });
    }

    /** @param array<string, string> $answers */
    public function addAssessment(int $vendorId, array $answers, int $score, string $level, ?string $notes, ?int $userId, ?string $nextReview): void
    {
        Database::transaction(function () use ($vendorId, $answers, $score, $level, $notes, $userId, $nextReview): void {
            $now = date('Y-m-d H:i:s');
            Database::insert('vendor_assessments', [
                'tenant_id' => $this->tenantId(), 'vendor_id' => $vendorId, 'answers' => json_encode($answers),
                'score' => $score, 'risk_level' => $level, 'notes' => $notes, 'assessed_by' => $userId, 'assessed_at' => $now,
            ]);
            $this->update($vendorId, ['risk_level' => $level, 'last_score' => $score, 'last_assessment_at' => $now, 'next_review_date' => $nextReview]);
        });
    }

    /** @param array<string, mixed> $data */
    public function addContract(int $vendorId, array $data): int
    {
        return Database::insert('vendor_contracts', $data + ['tenant_id' => $this->tenantId(), 'vendor_id' => $vendorId, 'created_at' => date('Y-m-d H:i:s')]);
    }

    public function removeContract(int $vendorId, int $contractId): int
    {
        return Database::execute('DELETE FROM vendor_contracts WHERE tenant_id = ? AND vendor_id = ? AND id = ?', [$this->tenantId(), $vendorId, $contractId]);
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS total,
                    SUM(v.status <> 'encerrado') AS active,
                    SUM(v.status <> 'encerrado' AND v.risk_level = 'alto') AS high,
                    SUM(v.status <> 'encerrado' AND v.last_assessment_at IS NULL) AS unassessed,
                    SUM(v.status <> 'encerrado' AND v.next_review_date < CURDATE()) AS review_overdue,
                    SUM(v.status <> 'encerrado' AND v.data_access = 1 AND NOT EXISTS (
                        SELECT 1 FROM vendor_contracts c WHERE c.tenant_id = v.tenant_id AND c.vendor_id = v.id AND c.has_data_clauses = 1)) AS no_dpa
               FROM vendors v WHERE v.tenant_id = ?",
            [$this->tenantId()],
        ) ?? [];
        $expiring = (int) Database::value(
            "SELECT COUNT(*) FROM vendor_contracts c JOIN vendors v ON v.tenant_id = c.tenant_id AND v.id = c.vendor_id
              WHERE c.tenant_id = ? AND v.status <> 'encerrado' AND c.end_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY",
            [$this->tenantId()],
        );
        return array_map('intval', $row) + ['contracts_expiring' => $expiring];
    }
}
