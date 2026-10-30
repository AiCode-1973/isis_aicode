<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Database;

/**
 * Ações dos planos de ação, com histórico de andamento.
 */
final class ActionRepository extends TenantScopedRepository
{
    protected const string TABLE = 'actions';

    public const string OPEN = "x.status IN ('pendente','em_andamento')";

    private const string BASE_SELECT = "
        SELECT x.*, u.name AS owner_name, d.name AS department_name,
               r.seq AS risk_seq, r.title AS risk_title, r.likelihood AS risk_likelihood, r.impact AS risk_impact,
               a.seq AS activity_seq, a.name AS activity_name,
               (x.due_date IS NOT NULL AND x.due_date < CURDATE() AND x.status IN ('pendente','em_andamento')) AS overdue,
               (SELECT COUNT(*) FROM attachments t WHERE t.tenant_id = x.tenant_id AND t.entity_type = 'action' AND t.entity_id = x.id) AS evidence_count
          FROM actions x
     LEFT JOIN users u ON u.tenant_id = x.tenant_id AND u.id = x.owner_user_id
     LEFT JOIN departments d ON d.tenant_id = x.tenant_id AND d.id = x.department_id
     LEFT JOIN risks r ON r.tenant_id = x.tenant_id AND r.id = x.risk_id
     LEFT JOIN processing_activities a ON a.tenant_id = x.tenant_id AND a.id = x.activity_id
         WHERE x.tenant_id = ?";

    /** @param array{q?: ?string, status?: ?string, owner_id?: ?int, risk_id?: ?int, overdue?: bool, open?: bool, only_owner?: ?int} $f */
    public function search(array $f): array
    {
        $sql = self::BASE_SELECT;
        $params = [$this->tenantId()];
        if (!empty($f['q'])) {
            $sql .= ' AND (x.title LIKE ? OR x.description LIKE ?)';
            $like = '%' . addcslashes((string) $f['q'], '%_\\') . '%';
            array_push($params, $like, $like);
        }
        if (!empty($f['status'])) {
            $sql .= ' AND x.status = ?';
            $params[] = $f['status'];
        }
        if (!empty($f['owner_id'])) {
            $sql .= ' AND x.owner_user_id = ?';
            $params[] = $f['owner_id'];
        }
        if (!empty($f['only_owner'])) {
            $sql .= ' AND x.owner_user_id = ?';
            $params[] = $f['only_owner'];
        }
        if (!empty($f['risk_id'])) {
            $sql .= ' AND x.risk_id = ?';
            $params[] = $f['risk_id'];
        }
        if (!empty($f['overdue'])) {
            $sql .= " AND x.due_date < CURDATE() AND x.status IN ('pendente','em_andamento')";
        }
        if (!empty($f['open'])) {
            $sql .= ' AND ' . self::OPEN;
        }
        $sql .= " ORDER BY (x.status IN ('concluida','cancelada')) ASC, x.due_date IS NULL ASC, x.due_date ASC, FIELD(x.priority, 'alta', 'media', 'baixa'), x.seq DESC";
        return Database::fetchAll($sql, $params);
    }

    public function findFull(int $id): ?array
    {
        return Database::fetch(self::BASE_SELECT . ' AND x.id = ?', [$this->tenantId(), $id]);
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
        $this->delete($id);
    }

    public function addUpdate(int $actionId, ?int $userId, ?string $from, ?string $to, ?string $note): void
    {
        Database::insert('action_updates', [
            'tenant_id' => $this->tenantId(),
            'action_id' => $actionId,
            'user_id' => $userId,
            'status_from' => $from,
            'status_to' => $to,
            'note' => $note,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function history(int $actionId): array
    {
        return Database::fetchAll(
            'SELECT h.*, u.name AS user_name FROM action_updates h
          LEFT JOIN users u ON u.tenant_id = h.tenant_id AND u.id = h.user_id
              WHERE h.tenant_id = ? AND h.action_id = ? ORDER BY h.id DESC',
            [$this->tenantId(), $actionId],
        );
    }

    /** Prazos: vencidas e as que vencem nos próximos dias. */
    public function upcoming(int $days = 30, int $limit = 8, ?int $onlyOwner = null): array
    {
        $sql = self::BASE_SELECT . ' AND ' . self::OPEN . ' AND x.due_date IS NOT NULL AND x.due_date <= (CURDATE() + INTERVAL ? DAY)';
        $params = [$this->tenantId(), $days];
        if ($onlyOwner !== null) {
            $sql .= ' AND x.owner_user_id = ?';
            $params[] = $onlyOwner;
        }
        return Database::fetchAll($sql . ' ORDER BY x.due_date ASC LIMIT ' . max(1, $limit), $params);
    }

    /** @return array<string, int> */
    public function stats(?int $onlyOwner = null): array
    {
        $sql = "SELECT COUNT(*) AS total,
                       SUM(x.status = 'pendente') AS pending,
                       SUM(x.status = 'em_andamento') AS in_progress,
                       SUM(x.status = 'concluida') AS done,
                       SUM(x.status = 'cancelada') AS cancelled,
                       SUM(" . self::OPEN . " AND x.due_date < CURDATE()) AS overdue,
                       SUM(" . self::OPEN . " AND x.owner_user_id IS NULL) AS unowned,
                       SUM(x.status = 'concluida' AND x.due_date IS NOT NULL AND DATE(x.completed_at) <= x.due_date) AS done_on_time
                  FROM actions x WHERE x.tenant_id = ?";
        $params = [$this->tenantId()];
        if ($onlyOwner !== null) {
            $sql .= ' AND x.owner_user_id = ?';
            $params[] = $onlyOwner;
        }
        return array_map('intval', Database::fetch($sql, $params) ?? []);
    }

    /** Ações abertas por responsável (carga de trabalho). */
    public function byOwner(int $limit = 6): array
    {
        return Database::fetchAll(
            'SELECT COALESCE(u.name, \'Sem responsável\') AS owner_name, COUNT(*) AS open_count,
                    SUM(x.due_date < CURDATE()) AS overdue_count
               FROM actions x LEFT JOIN users u ON u.tenant_id = x.tenant_id AND u.id = x.owner_user_id
              WHERE x.tenant_id = ? AND ' . self::OPEN . '
           GROUP BY x.owner_user_id, u.name ORDER BY open_count DESC LIMIT ' . max(1, $limit),
            [$this->tenantId()],
        );
    }
}
