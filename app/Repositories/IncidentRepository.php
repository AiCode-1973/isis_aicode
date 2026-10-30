<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Crypto;
use Isis\Core\Database;

/**
 * Incidentes de segurança. Descrição, contenção, causa e lições aprendidas ficam criptografadas.
 */
final class IncidentRepository extends TenantScopedRepository
{
    protected const string TABLE = 'incidents';

    public const string OPEN = "i.status IN ('comunicado','em_analise','contido')";
    private const array ENCRYPTED = ['description', 'containment', 'root_cause', 'lessons'];

    private const string BASE_SELECT = "
        SELECT i.*, o.name AS owner_name, rp.name AS reporter_name, d.name AS department_name,
               a.seq AS activity_seq, a.name AS activity_name,
               (i.risk_to_subjects = 'relevante' AND i.anpd_notified_at IS NULL AND i.anpd_deadline IS NOT NULL AND i.anpd_deadline < CURDATE()) AS anpd_overdue,
               (SELECT COUNT(*) FROM actions x WHERE x.tenant_id = i.tenant_id AND x.incident_id = i.id) AS actions_count
          FROM incidents i
     LEFT JOIN users o ON o.tenant_id = i.tenant_id AND o.id = i.owner_user_id
     LEFT JOIN users rp ON rp.tenant_id = i.tenant_id AND rp.id = i.reported_by
     LEFT JOIN departments d ON d.tenant_id = i.tenant_id AND d.id = i.department_id
     LEFT JOIN processing_activities a ON a.tenant_id = i.tenant_id AND a.id = i.activity_id
         WHERE i.tenant_id = ?";

    /** @param array{status?: ?string, severity?: ?string, open?: bool, reported_by?: ?int} $f */
    public function search(array $f): array
    {
        $sql = self::BASE_SELECT;
        $params = [$this->tenantId()];
        foreach (['status' => 'i.status', 'severity' => 'i.severity', 'reported_by' => 'i.reported_by'] as $key => $column) {
            if (!empty($f[$key])) {
                $sql .= " AND {$column} = ?";
                $params[] = $f[$key];
            }
        }
        if (!empty($f['open'])) {
            $sql .= ' AND ' . self::OPEN;
        }
        $sql .= " ORDER BY (" . self::OPEN . ") DESC, FIELD(i.severity, 'critica', 'alta', 'media', 'baixa'), i.detected_at DESC";
        return array_map($this->decryptRow(...), Database::fetchAll($sql, $params));
    }

    public function findFull(int $id): ?array
    {
        $row = Database::fetch(self::BASE_SELECT . ' AND i.id = ?', [$this->tenantId(), $id]);
        return $row === null ? null : $this->decryptRow($row);
    }

    public function findFullOrFail(int $id): array
    {
        return $this->findFull($id) ?? $this->findOrFail($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return Database::transaction(fn (): int => $this->insert(
            $this->encryptFields($data) + ['seq' => $this->nextSeq(), 'created_at' => date('Y-m-d H:i:s')],
        ));
    }

    /** @param array<string, mixed> $data */
    public function save(int $id, array $data): void
    {
        $this->update($id, $this->encryptFields($data) + ['updated_at' => date('Y-m-d H:i:s')]);
    }

    public function addEvent(int $incidentId, ?int $userId, string $type, ?string $note = null, ?string $from = null, ?string $to = null): void
    {
        Database::insert('incident_events', [
            'tenant_id' => $this->tenantId(),
            'incident_id' => $incidentId,
            'user_id' => $userId,
            'event_type' => $type,
            'status_from' => $from,
            'status_to' => $to,
            'note_enc' => $note !== null && $note !== '' ? Crypto::encrypt($note, $this->context()) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function events(int $incidentId): array
    {
        $rows = Database::fetchAll(
            'SELECT e.*, u.name AS user_name FROM incident_events e LEFT JOIN users u ON u.tenant_id = e.tenant_id AND u.id = e.user_id
              WHERE e.tenant_id = ? AND e.incident_id = ? ORDER BY e.id DESC',
            [$this->tenantId(), $incidentId],
        );
        foreach ($rows as &$row) {
            $row['note'] = $this->dec($row['note_enc']);
        }
        return $rows;
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS total,
                    SUM(" . self::OPEN . ") AS open,
                    SUM(" . self::OPEN . " AND i.severity IN ('alta','critica')) AS severe,
                    SUM(i.status = 'comunicado') AS untriaged,
                    SUM(i.risk_to_subjects = 'relevante' AND i.anpd_notified_at IS NULL) AS anpd_pending,
                    SUM(i.risk_to_subjects = 'relevante' AND i.anpd_notified_at IS NULL AND i.anpd_deadline < CURDATE()) AS anpd_overdue
               FROM incidents i WHERE i.tenant_id = ?",
            [$this->tenantId()],
        ) ?? [];
        return array_map('intval', $row);
    }

    /** @return array<int, string> */
    public function options(): array
    {
        $options = [];
        foreach ($this->select('', [], 'seq DESC', 200) as $row) {
            $options[(int) $row['id']] = code('INC', (int) $row['seq']) . ' · ' . $row['title'];
        }
        return $options;
    }

    private function context(): string
    {
        return 'tenant:' . $this->tenantId();
    }

    /** @param array<string, mixed> $data */
    private function encryptFields(array $data): array
    {
        foreach (self::ENCRYPTED as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                $data[$field . '_enc'] = ($value === null || $value === '') ? null : Crypto::encrypt((string) $value, $this->context());
                unset($data[$field]);
            }
        }
        return $data;
    }

    private function decryptRow(array $row): array
    {
        foreach (self::ENCRYPTED as $field) {
            $row[$field] = $this->dec($row[$field . '_enc'] ?? null);
            unset($row[$field . '_enc']);
        }
        return $row;
    }

    private function dec(?string $value): ?string
    {
        try {
            return Crypto::decrypt($value, $this->context());
        } catch (\RuntimeException) {
            return '[conteúdo protegido indisponível: verifique a APP_KEY]';
        }
    }
}
