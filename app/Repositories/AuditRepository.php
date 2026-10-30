<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Audit\AuditHasher;
use Isis\Core\Database;

/**
 * Leitura da trilha de auditoria da empresa atual (a trilha não tem edição nem exclusão).
 */
final class AuditRepository extends TenantScopedRepository
{
    protected const string TABLE = 'audit_logs';

    /**
     * @param array{action?: ?string, user_id?: ?int, from?: ?string, to?: ?string} $filters
     * @return array{rows: list<array>, total: int}
     */
    public function search(array $filters, int $page = 1, int $perPage = 50): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $total = $this->count($where, $params);
        $rows = $this->select($where, $params, 'id DESC', $perPage, ($page - 1) * $perPage);
        return ['rows' => $rows, 'total' => $total];
    }

    /** @param array{action?: ?string, user_id?: ?int, from?: ?string, to?: ?string} $filters */
    public function export(array $filters, int $max = 20000): array
    {
        [$where, $params] = $this->buildWhere($filters);
        return $this->select($where, $params, 'id DESC', $max);
    }

    public function recent(int $limit = 8): array
    {
        return $this->select('', [], 'id DESC', $limit);
    }

    public function countSince(string $since): int
    {
        return $this->count('created_at >= ?', [$since]);
    }

    /** @return list<string> */
    public function distinctActions(): array
    {
        return array_column(
            Database::fetchAll('SELECT DISTINCT action FROM audit_logs WHERE tenant_id = ? ORDER BY action', [$this->tenantId()]),
            'action',
        );
    }

    /** @return array{ok: bool, checked: int, broken_id: ?int} */
    public function verifyChain(): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM audit_logs WHERE tenant_id = ? ORDER BY id ASC');
        $stmt->execute([$this->tenantId()]);
        $result = AuditHasher::verify((function () use ($stmt): \Generator {
            while ($row = $stmt->fetch()) {
                yield $row;
            }
        })());
        // A cadeia também precisa terminar no último hash conhecido (detecta exclusão do final).
        $head = Database::value('SELECT last_hash FROM audit_chain WHERE chain_key = ?', [$this->tenantId()]);
        $last = Database::value('SELECT hash FROM audit_logs WHERE tenant_id = ? ORDER BY id DESC LIMIT 1', [$this->tenantId()]);
        if ($result['ok'] && $head !== null && $head !== $last) {
            $result['ok'] = false;
        }
        return $result;
    }

    /**
     * @param array{action?: ?string, user_id?: ?int, from?: ?string, to?: ?string} $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59.999999';
        }
        return [implode(' AND ', $where), $params];
    }
}
