<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Database;
use Isis\Services\RiskMatrix;

final class RiskRepository extends TenantScopedRepository
{
    protected const string TABLE = 'risks';

    /** Riscos ainda abertos (não encerrados nem aceitos formalmente). */
    public const string OPEN = "r.status IN ('identificado','em_tratamento')";

    private const string BASE_SELECT = "
        SELECT r.*, (r.likelihood * r.impact) AS score, a.seq AS activity_seq, a.name AS activity_name,
               u.name AS owner_name, d.name AS department_name,
               (SELECT COUNT(*) FROM actions x WHERE x.tenant_id = r.tenant_id AND x.risk_id = r.id) AS actions_count,
               (SELECT COUNT(*) FROM actions x WHERE x.tenant_id = r.tenant_id AND x.risk_id = r.id AND x.status = 'concluida') AS actions_done
          FROM risks r
     LEFT JOIN processing_activities a ON a.tenant_id = r.tenant_id AND a.id = r.activity_id
     LEFT JOIN users u ON u.tenant_id = r.tenant_id AND u.id = r.owner_user_id
     LEFT JOIN departments d ON d.tenant_id = r.tenant_id AND d.id = r.department_id
         WHERE r.tenant_id = ?";

    /** @param array{q?: ?string, status?: ?string, level?: ?string, category?: ?string, activity_id?: ?int, open?: bool, cell?: ?array{0:int,1:int}} $f */
    public function search(array $f): array
    {
        $sql = self::BASE_SELECT;
        $params = [$this->tenantId()];
        if (!empty($f['q'])) {
            $sql .= ' AND (r.title LIKE ? OR r.description LIKE ?)';
            $like = '%' . addcslashes((string) $f['q'], '%_\\') . '%';
            array_push($params, $like, $like);
        }
        if (!empty($f['status'])) {
            $sql .= ' AND r.status = ?';
            $params[] = $f['status'];
        }
        if (!empty($f['category'])) {
            $sql .= ' AND r.category = ?';
            $params[] = $f['category'];
        }
        if (!empty($f['activity_id'])) {
            $sql .= ' AND r.activity_id = ?';
            $params[] = $f['activity_id'];
        }
        if (!empty($f['level'])) {
            [$min, $max] = RiskMatrix::range((string) $f['level']);
            $sql .= ' AND (r.likelihood * r.impact) BETWEEN ? AND ?';
            array_push($params, $min, $max);
        }
        if (!empty($f['cell'])) {
            $sql .= ' AND r.likelihood = ? AND r.impact = ?';
            array_push($params, $f['cell'][0], $f['cell'][1]);
        }
        if (!empty($f['open'])) {
            $sql .= ' AND ' . self::OPEN;
        }
        return Database::fetchAll($sql . ' ORDER BY score DESC, r.seq DESC', $params);
    }

    public function findFull(int $id): ?array
    {
        return Database::fetch(self::BASE_SELECT . ' AND r.id = ?', [$this->tenantId(), $id]);
    }

    public function findFullOrFail(int $id): array
    {
        return $this->findFull($id) ?? $this->findOrFail($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return Database::transaction(fn (): int => $this->insert($data + ['seq' => $this->nextSeq(), 'created_at' => date('Y-m-d H:i:s')]));
    }

    /** @param array<string, mixed> $data */
    public function save(int $id, array $data): void
    {
        $this->update($id, $data + ['updated_at' => date('Y-m-d H:i:s')]);
    }

    public function remove(int $id): void
    {
        Database::transaction(function () use ($id): void {
            Database::execute('UPDATE actions SET risk_id = NULL WHERE tenant_id = ? AND risk_id = ?', [$this->tenantId(), $id]);
            $this->delete($id);
        });
    }

    /** Contagem de riscos abertos por célula da matriz. @return array<int, array<int, int>> [prob][impacto] */
    public function heatmap(): array
    {
        $grid = [];
        foreach (range(1, 5) as $l) {
            foreach (range(1, 5) as $i) {
                $grid[$l][$i] = 0;
            }
        }
        $rows = Database::fetchAll(
            'SELECT likelihood, impact, COUNT(*) AS n FROM risks r WHERE r.tenant_id = ? AND ' . self::OPEN . ' GROUP BY likelihood, impact',
            [$this->tenantId()],
        );
        foreach ($rows as $row) {
            $grid[(int) $row['likelihood']][(int) $row['impact']] = (int) $row['n'];
        }
        return $grid;
    }

    /** @return array{open: int, critical: int, high: int, medium: int, low: int, total: int, unowned: int, review_overdue: int} */
    public function stats(): array
    {
        $row = Database::fetch(
            'SELECT COUNT(*) AS total,
                    SUM(' . self::OPEN . ') AS open,
                    SUM(' . self::OPEN . ' AND likelihood * impact >= 16) AS critical,
                    SUM(' . self::OPEN . ' AND likelihood * impact BETWEEN 10 AND 15) AS high,
                    SUM(' . self::OPEN . ' AND likelihood * impact BETWEEN 5 AND 9) AS medium,
                    SUM(' . self::OPEN . ' AND likelihood * impact <= 4) AS low,
                    SUM(' . self::OPEN . ' AND owner_user_id IS NULL) AS unowned,
                    SUM(' . self::OPEN . ' AND review_date IS NOT NULL AND review_date < CURDATE()) AS review_overdue
               FROM risks r WHERE r.tenant_id = ?',
            [$this->tenantId()],
        ) ?? [];
        return array_map('intval', $row);
    }

    /** @return array<int, string> */
    public function options(): array
    {
        $options = [];
        foreach ($this->select("status IN ('identificado','em_tratamento')", [], 'seq ASC') as $row) {
            $options[(int) $row['id']] = code('RSK', (int) $row['seq']) . ' · ' . $row['title'];
        }
        return $options;
    }
}
