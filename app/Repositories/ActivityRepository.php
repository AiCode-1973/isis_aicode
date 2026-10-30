<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Database;

/**
 * Operações de tratamento de dados pessoais (inventário / registro do art. 37).
 */
final class ActivityRepository extends TenantScopedRepository
{
    protected const string TABLE = 'processing_activities';

    private const string BASE_SELECT = "
        SELECT a.*, d.name AS department_name, u.name AS owner_name,
               lb.article AS legal_basis_article, lb.name AS legal_basis_name,
               slb.article AS sensitive_basis_article, slb.name AS sensitive_basis_name,
               EXISTS (SELECT 1 FROM activity_data_categories x JOIN data_categories c ON c.tenant_id = x.tenant_id AND c.id = x.category_id
                        WHERE x.tenant_id = a.tenant_id AND x.activity_id = a.id AND c.is_sensitive = 1) AS has_sensitive,
               (SELECT COUNT(*) FROM risks r WHERE r.tenant_id = a.tenant_id AND r.activity_id = a.id) AS risks_count
          FROM processing_activities a
     LEFT JOIN departments d ON d.tenant_id = a.tenant_id AND d.id = a.department_id
     LEFT JOIN users u ON u.tenant_id = a.tenant_id AND u.id = a.owner_user_id
     LEFT JOIN legal_bases lb ON lb.code = a.legal_basis_code
     LEFT JOIN legal_bases slb ON slb.code = a.sensitive_legal_basis_code
         WHERE a.tenant_id = ?";

    /** @param array{q?: ?string, status?: ?string, department_id?: ?int, sensitive?: bool} $filters */
    public function search(array $filters): array
    {
        $sql = self::BASE_SELECT;
        $params = [$this->tenantId()];
        if (!empty($filters['q'])) {
            $sql .= ' AND (a.name LIKE ? OR a.purpose LIKE ?)';
            $like = '%' . addcslashes((string) $filters['q'], '%_\\') . '%';
            array_push($params, $like, $like);
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND a.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['department_id'])) {
            $sql .= ' AND (a.department_id = ? OR EXISTS (SELECT 1 FROM activity_departments ad WHERE ad.tenant_id = a.tenant_id AND ad.activity_id = a.id AND ad.department_id = ?))';
            array_push($params, $filters['department_id'], $filters['department_id']);
        }
        $sql .= ' ORDER BY a.seq DESC';
        $rows = Database::fetchAll($sql, $params);
        if (!empty($filters['sensitive'])) {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => (bool) $r['has_sensitive']));
        }
        return $rows;
    }

    public function findFull(int $id): ?array
    {
        $activity = Database::fetch(self::BASE_SELECT . ' AND a.id = ?', [$this->tenantId(), $id]);
        if ($activity === null) {
            return null;
        }
        $tid = $this->tenantId();
        $activity['data_categories'] = Database::fetchAll(
            'SELECT c.id, c.name, c.is_sensitive FROM activity_data_categories x JOIN data_categories c ON c.tenant_id = x.tenant_id AND c.id = x.category_id
              WHERE x.tenant_id = ? AND x.activity_id = ? ORDER BY c.name', [$tid, $id]);
        $activity['subject_categories'] = Database::fetchAll(
            'SELECT c.id, c.name, c.vulnerable FROM activity_subject_categories x JOIN subject_categories c ON c.tenant_id = x.tenant_id AND c.id = x.category_id
              WHERE x.tenant_id = ? AND x.activity_id = ? ORDER BY c.name', [$tid, $id]);
        $activity['systems'] = Database::fetchAll(
            'SELECT s.id, s.name, s.hosting FROM activity_systems x JOIN systems s ON s.tenant_id = x.tenant_id AND s.id = x.system_id
              WHERE x.tenant_id = ? AND x.activity_id = ? ORDER BY s.name', [$tid, $id]);
        $activity['departments'] = Database::fetchAll(
            'SELECT d.id, d.name FROM activity_departments x JOIN departments d ON d.tenant_id = x.tenant_id AND d.id = x.department_id
              WHERE x.tenant_id = ? AND x.activity_id = ? ORDER BY d.name', [$tid, $id]);
        $activity['sharings'] = Database::fetchAll(
            'SELECT * FROM activity_sharings WHERE tenant_id = ? AND activity_id = ? ORDER BY id', [$tid, $id]);
        return $activity;
    }

    public function findFullOrFail(int $id): array
    {
        $activity = $this->findFull($id);
        if ($activity === null) {
            $this->findOrFail($id); // registra a tentativa e responde 404
        }
        return $activity;
    }

    /**
     * @param array<string, mixed> $data
     * @param array{data: list<int>, subjects: list<int>, systems: list<int>, departments: list<int>, sharings: list<array>} $relations
     */
    public function create(array $data, array $relations): int
    {
        return Database::transaction(function () use ($data, $relations): int {
            $id = $this->insert($data + ['seq' => $this->nextSeq(), 'created_at' => date('Y-m-d H:i:s')]);
            $this->syncRelations($id, $relations);
            return $id;
        });
    }

    /**
     * @param array<string, mixed> $data
     * @param array{data: list<int>, subjects: list<int>, systems: list<int>, departments: list<int>, sharings: list<array>} $relations
     */
    public function save(int $id, array $data, array $relations): void
    {
        Database::transaction(function () use ($id, $data, $relations): void {
            $this->update($id, $data + ['updated_at' => date('Y-m-d H:i:s')]);
            $this->syncRelations($id, $relations);
        });
    }

    /** Exclui a operação; riscos e ações vinculados permanecem, sem o vínculo. */
    public function remove(int $id): void
    {
        Database::transaction(function () use ($id): void {
            Database::execute('UPDATE risks SET activity_id = NULL WHERE tenant_id = ? AND activity_id = ?', [$this->tenantId(), $id]);
            Database::execute('UPDATE actions SET activity_id = NULL WHERE tenant_id = ? AND activity_id = ?', [$this->tenantId(), $id]);
            $this->delete($id);
        });
    }

    /** @return array<int, string> id => "OP-001 · nome" */
    public function options(): array
    {
        $options = [];
        foreach ($this->select("status <> 'descontinuada'", [], 'seq ASC') as $row) {
            $options[(int) $row['id']] = code('OP', (int) $row['seq']) . ' · ' . $row['name'];
        }
        return $options;
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'ativa') AS active,
                    SUM(status = 'rascunho') AS drafts,
                    SUM(review_due_date IS NOT NULL AND review_due_date < CURDATE() AND status <> 'descontinuada') AS review_overdue,
                    SUM(legal_basis_code IS NULL AND status <> 'descontinuada') AS no_basis,
                    SUM(international_transfer = 1 AND status <> 'descontinuada') AS international
               FROM processing_activities WHERE tenant_id = ?",
            [$this->tenantId()],
        ) ?? [];
        $sensitive = (int) Database::value(
            "SELECT COUNT(DISTINCT a.id) FROM processing_activities a
               JOIN activity_data_categories x ON x.tenant_id = a.tenant_id AND x.activity_id = a.id
               JOIN data_categories c ON c.tenant_id = x.tenant_id AND c.id = x.category_id
              WHERE a.tenant_id = ? AND c.is_sensitive = 1 AND a.status <> 'descontinuada'",
            [$this->tenantId()],
        );
        return array_map('intval', $row) + ['sensitive' => $sensitive];
    }

    /** Operações com pendências de preenchimento (para o painel). */
    public function withIssues(int $limit = 6): array
    {
        $result = [];
        foreach ($this->search([]) as $row) {
            if ($row['status'] === 'descontinuada') {
                continue;
            }
            $issues = self::issues($this->findFull((int) $row['id']) ?? []);
            if ($issues !== []) {
                $result[] = ['activity' => $row, 'issues' => $issues];
            }
            if (count($result) >= $limit) {
                break;
            }
        }
        return $result;
    }

    /**
     * Pontos a completar ou revisar numa operação (orientação, não parecer jurídico).
     *
     * @return list<string>
     */
    public static function issues(array $a): array
    {
        if ($a === []) {
            return [];
        }
        $issues = [];
        if (trim((string) $a['purpose']) === '') {
            $issues[] = 'Finalidade não descrita.';
        }
        if (empty($a['legal_basis_code'])) {
            $issues[] = 'Hipótese legal não definida.';
        }
        $sensitive = array_filter($a['data_categories'] ?? [], static fn (array $c): bool => (bool) $c['is_sensitive']);
        if ($sensitive !== [] && empty($a['sensitive_legal_basis_code'])) {
            $issues[] = 'Há dados sensíveis sem hipótese legal do art. 11.';
        }
        if (($a['data_categories'] ?? []) === []) {
            $issues[] = 'Nenhuma categoria de dados informada.';
        }
        if (($a['subject_categories'] ?? []) === []) {
            $issues[] = 'Nenhuma categoria de titulares informada.';
        }
        if (trim((string) $a['retention_period']) === '') {
            $issues[] = 'Prazo de retenção não definido.';
        }
        if (empty($a['owner_user_id'])) {
            $issues[] = 'Sem responsável.';
        }
        if ((int) $a['international_transfer'] === 1 && trim((string) $a['international_details']) === '') {
            $issues[] = 'Transferência internacional sem detalhes (país e mecanismo).';
        }
        if (!empty($a['review_due_date']) && $a['review_due_date'] < date('Y-m-d')) {
            $issues[] = 'Revisão vencida em ' . fmt_date($a['review_due_date']) . '.';
        }
        $vulnerable = array_filter($a['subject_categories'] ?? [], static fn (array $c): bool => (bool) $c['vulnerable']);
        if ($vulnerable !== [] && trim((string) $a['legal_basis_notes']) === '') {
            $issues[] = 'Envolve titulares vulneráveis (ex.: crianças): registre as salvaguardas nas observações.';
        }
        return $issues;
    }

    /** Linhas para o registro das operações (exportação). */
    public function exportRows(): array
    {
        $rows = [];
        foreach ($this->search([]) as $row) {
            $rows[] = $this->findFull((int) $row['id']);
        }
        return $rows;
    }

    /** @param array{data: list<int>, subjects: list<int>, systems: list<int>, departments: list<int>, sharings: list<array>} $r */
    private function syncRelations(int $id, array $r): void
    {
        $this->syncPivot('activity_data_categories', 'activity_id', $id, 'category_id', $r['data']);
        $this->syncPivot('activity_subject_categories', 'activity_id', $id, 'category_id', $r['subjects']);
        $this->syncPivot('activity_systems', 'activity_id', $id, 'system_id', $r['systems']);
        $this->syncPivot('activity_departments', 'activity_id', $id, 'department_id', $r['departments']);
        Database::execute('DELETE FROM activity_sharings WHERE tenant_id = ? AND activity_id = ?', [$this->tenantId(), $id]);
        foreach ($r['sharings'] as $s) {
            Database::insert('activity_sharings', [
                'tenant_id' => $this->tenantId(),
                'activity_id' => $id,
                'recipient' => $s['recipient'],
                'recipient_type' => $s['recipient_type'],
                'purpose' => $s['purpose'],
                'international' => $s['international'] ? 1 : 0,
            ]);
        }
    }
}
