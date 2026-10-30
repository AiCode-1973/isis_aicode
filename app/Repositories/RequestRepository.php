<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Crypto;
use Isis\Core\Database;

/**
 * Solicitações de titulares. Nome, contatos, documento, descrição e resposta ficam criptografados
 * com a empresa como contexto; a busca por e-mail usa índice cego.
 */
final class RequestRepository extends TenantScopedRepository
{
    protected const string TABLE = 'dsar_requests';

    public const string OPEN = "r.status IN ('recebida','em_verificacao','em_atendimento')";
    private const array ENCRYPTED = ['requester_name', 'requester_email', 'requester_phone', 'requester_document', 'description', 'response'];

    private const string BASE_SELECT = "
        SELECT r.*, u.name AS assigned_name, a.seq AS activity_seq, a.name AS activity_name,
               (" . self::OPEN . " AND r.due_date < CURDATE()) AS overdue
          FROM dsar_requests r
     LEFT JOIN users u ON u.tenant_id = r.tenant_id AND u.id = r.assigned_user_id
     LEFT JOIN processing_activities a ON a.tenant_id = r.tenant_id AND a.id = r.activity_id
         WHERE r.tenant_id = ?";

    /** @param array{status?: ?string, right_type?: ?string, overdue?: bool, open?: bool, email?: ?string} $f */
    public function search(array $f): array
    {
        $sql = self::BASE_SELECT;
        $params = [$this->tenantId()];
        if (!empty($f['status'])) {
            $sql .= ' AND r.status = ?';
            $params[] = $f['status'];
        }
        if (!empty($f['right_type'])) {
            $sql .= ' AND r.right_type = ?';
            $params[] = $f['right_type'];
        }
        if (!empty($f['overdue'])) {
            $sql .= ' AND ' . self::OPEN . ' AND r.due_date < CURDATE()';
        }
        if (!empty($f['open'])) {
            $sql .= ' AND ' . self::OPEN;
        }
        if (!empty($f['email'])) {
            $sql .= ' AND r.requester_email_index = ?';
            $params[] = self::emailIndex((string) $f['email']);
        }
        $sql .= ' ORDER BY (' . self::OPEN . ') DESC, r.due_date ASC, r.seq DESC';
        return array_map($this->decryptRow(...), Database::fetchAll($sql, $params));
    }

    public function findFull(int $id): ?array
    {
        $row = Database::fetch(self::BASE_SELECT . ' AND r.id = ?', [$this->tenantId(), $id]);
        return $row === null ? null : $this->decryptRow($row);
    }

    public function findFullOrFail(int $id): array
    {
        return $this->findFull($id) ?? $this->findOrFail($id);
    }

    /**
     * @param array<string, mixed> $data Campos em claro (requester_name, requester_email...) e demais colunas
     * @return array{id: int, protocol: string}
     */
    public function create(array $data): array
    {
        return Database::transaction(function () use ($data): array {
            $protocol = self::newProtocol();
            $row = $this->encryptFields($data) + [
                'seq' => $this->nextSeq(),
                'protocol' => $protocol,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            return ['id' => $this->insert($row), 'protocol' => $protocol];
        });
    }

    /** @param array<string, mixed> $data */
    public function save(int $id, array $data): void
    {
        $this->update($id, $this->encryptFields($data) + ['updated_at' => date('Y-m-d H:i:s')]);
    }

    public function addEvent(int $requestId, ?int $userId, string $type, ?string $note = null, ?string $from = null, ?string $to = null, bool $visible = false): void
    {
        Database::insert('dsar_events', [
            'tenant_id' => $this->tenantId(),
            'request_id' => $requestId,
            'user_id' => $userId,
            'event_type' => $type,
            'status_from' => $from,
            'status_to' => $to,
            'note_enc' => $note !== null && $note !== '' ? Crypto::encrypt($note, $this->context()) : null,
            'visible_to_requester' => $visible ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function events(int $requestId, bool $onlyPublic = false): array
    {
        $rows = Database::fetchAll(
            'SELECT e.*, u.name AS user_name FROM dsar_events e LEFT JOIN users u ON u.tenant_id = e.tenant_id AND u.id = e.user_id
              WHERE e.tenant_id = ? AND e.request_id = ?' . ($onlyPublic ? ' AND e.visible_to_requester = 1' : '') . ' ORDER BY e.id DESC',
            [$this->tenantId(), $requestId],
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
            'SELECT COUNT(*) AS total,
                    SUM(' . self::OPEN . ') AS open,
                    SUM(' . self::OPEN . ' AND r.due_date < CURDATE()) AS overdue,
                    SUM(' . self::OPEN . ' AND r.due_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 DAY) AS due_soon,
                    SUM(r.status = \'respondida\') AS answered,
                    SUM(r.status = \'respondida\' AND DATE(r.responded_at) <= r.due_date) AS answered_on_time,
                    ROUND(AVG(CASE WHEN r.responded_at IS NOT NULL THEN DATEDIFF(r.responded_at, r.received_at) END), 1) AS avg_days
               FROM dsar_requests r WHERE r.tenant_id = ?',
            [$this->tenantId()],
        ) ?? [];
        $avg = $row['avg_days'] ?? null;
        $result = array_map('intval', $row);
        $result['avg_days'] = $avg === null ? null : (float) $avg;
        return $result;
    }

    // ---------------- Canal público (sem sessão: a empresa vem do slug) ----------------

    /** Busca pelo protocolo e e-mail informados pelo titular. Retorna null se não conferir. */
    public function findForRequester(string $protocol, string $email): ?array
    {
        $row = Database::fetch(
            self::BASE_SELECT . ' AND r.protocol = ? AND r.requester_email_index = ?',
            [$this->tenantId(), strtoupper(trim($protocol)), self::emailIndex($email)],
        );
        return $row === null ? null : $this->decryptRow($row);
    }

    public static function emailIndex(string $email): string
    {
        return Crypto::blindIndex(mb_strtolower(trim($email)), 'dsar-email');
    }

    /** Protocolo legível sem caracteres ambíguos: XXXX-XXXX. */
    private static function newProtocol(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $protocol = substr($code, 0, 4) . '-' . substr($code, 4);
        } while (Database::value('SELECT 1 FROM dsar_requests WHERE protocol = ?', [$protocol]) !== null);
        return $protocol;
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
                if ($field === 'requester_email') {
                    $data['requester_email_index'] = ($value === null || $value === '') ? null : self::emailIndex((string) $value);
                }
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
