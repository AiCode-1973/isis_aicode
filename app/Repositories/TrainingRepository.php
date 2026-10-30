<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Database;

/**
 * Treinamentos: módulos, perguntas de avaliação, participantes, progresso e certificados.
 */
final class TrainingRepository extends TenantScopedRepository
{
    protected const string TABLE = 'training_modules';

    public function modules(): array
    {
        return Database::fetchAll(
            "SELECT m.*,
                    (SELECT COUNT(*) FROM training_questions q WHERE q.tenant_id = m.tenant_id AND q.module_id = m.id) AS questions_count,
                    (SELECT COUNT(*) FROM training_enrollments e WHERE e.tenant_id = m.tenant_id AND e.module_id = m.id) AS enrolled,
                    (SELECT COUNT(*) FROM training_enrollments e WHERE e.tenant_id = m.tenant_id AND e.module_id = m.id AND e.status = 'concluido') AS completed,
                    (SELECT COUNT(*) FROM training_enrollments e WHERE e.tenant_id = m.tenant_id AND e.module_id = m.id AND e.status <> 'concluido' AND e.due_date < CURDATE()) AS overdue
               FROM training_modules m WHERE m.tenant_id = ? ORDER BY (m.status = 'arquivado'), m.seq DESC",
            [$this->tenantId()],
        );
    }

    /** Treinamentos do usuário: designados a ele e publicados. */
    public function forUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT m.id, m.seq, m.title, m.summary, m.duration_minutes, m.passing_score,
                    e.status AS enrollment_status, e.due_date, e.best_score, e.attempts, e.completed_at, e.certificate_code, e.certificate_valid_until
               FROM training_modules m
               JOIN training_enrollments e ON e.tenant_id = m.tenant_id AND e.module_id = m.id AND e.user_id = ?
              WHERE m.tenant_id = ? AND m.status = 'publicado'
           ORDER BY (e.status = 'concluido'), e.due_date IS NULL, e.due_date, m.title",
            [$userId, $this->tenantId()],
        );
    }

    public function questions(int $moduleId): array
    {
        $rows = Database::fetchAll(
            'SELECT * FROM training_questions WHERE tenant_id = ? AND module_id = ? ORDER BY position',
            [$this->tenantId(), $moduleId],
        );
        foreach ($rows as &$row) {
            $row['options'] = json_decode((string) $row['options'], true) ?: [];
        }
        return $rows;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array{question: string, options: list<string>, correct: int}> $questions
     */
    public function createModule(array $data, array $questions): int
    {
        return Database::transaction(function () use ($data, $questions): int {
            $id = $this->insert($data + ['seq' => $this->nextSeq(), 'created_at' => date('Y-m-d H:i:s')]);
            $this->replaceQuestions($id, $questions);
            return $id;
        });
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array{question: string, options: list<string>, correct: int}> $questions
     */
    public function saveModule(int $id, array $data, array $questions): void
    {
        Database::transaction(function () use ($id, $data, $questions): void {
            $this->update($id, $data + ['updated_at' => date('Y-m-d H:i:s')]);
            $this->replaceQuestions($id, $questions);
        });
    }

    public function setStatus(int $id, string $status): void
    {
        $this->update($id, ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function enrollments(int $moduleId): array
    {
        return Database::fetchAll(
            "SELECT e.*, u.name AS user_name, d.name AS department_name,
                    (e.status <> 'concluido' AND e.due_date < CURDATE()) AS overdue
               FROM training_enrollments e
               JOIN users u ON u.tenant_id = e.tenant_id AND u.id = e.user_id
          LEFT JOIN departments d ON d.tenant_id = u.tenant_id AND d.id = u.department_id
              WHERE e.tenant_id = ? AND e.module_id = ?
           ORDER BY (e.status = 'concluido'), u.name",
            [$this->tenantId(), $moduleId],
        );
    }

    public function enrollment(int $moduleId, int $userId): ?array
    {
        return Database::fetch('SELECT * FROM training_enrollments WHERE tenant_id = ? AND module_id = ? AND user_id = ?', [$this->tenantId(), $moduleId, $userId]);
    }

    /** Designa usuários (ignora quem já está inscrito). Retorna quantos foram incluídos. @param list<int> $userIds */
    public function enroll(int $moduleId, array $userIds, ?int $assignedBy, ?string $dueDate): int
    {
        $count = 0;
        foreach (array_unique($userIds) as $userId) {
            $count += Database::execute(
                'INSERT IGNORE INTO training_enrollments (tenant_id, module_id, user_id, assigned_by, assigned_at, due_date) VALUES (?, ?, ?, ?, NOW(), ?)',
                [$this->tenantId(), $moduleId, $userId, $assignedBy, $dueDate],
            );
        }
        return $count;
    }

    public function markStarted(int $enrollmentId): void
    {
        Database::execute(
            "UPDATE training_enrollments SET status = IF(status = 'pendente', 'em_andamento', status), started_at = COALESCE(started_at, NOW())
              WHERE tenant_id = ? AND id = ?",
            [$this->tenantId(), $enrollmentId],
        );
    }

    /** @param array<string, mixed> $data */
    public function saveEnrollment(int $enrollmentId, array $data): void
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            Database::assertIdentifier($column);
            $sets[] = "`{$column}` = ?";
        }
        Database::execute('UPDATE training_enrollments SET ' . implode(', ', $sets) . ' WHERE tenant_id = ? AND id = ?',
            [...array_values($data), $this->tenantId(), $enrollmentId]);
    }

    /** Certificado pelo código (empresa atual). */
    public function certificate(string $code): ?array
    {
        return Database::fetch(
            'SELECT e.*, u.name AS user_name, m.title AS module_title, m.duration_minutes, m.seq AS module_seq
               FROM training_enrollments e
               JOIN users u ON u.tenant_id = e.tenant_id AND u.id = e.user_id
               JOIN training_modules m ON m.tenant_id = e.tenant_id AND m.id = e.module_id
              WHERE e.tenant_id = ? AND e.certificate_code = ?',
            [$this->tenantId(), $code],
        );
    }

    /** Verificação pública: só o necessário para confirmar a autenticidade. */
    public static function verifyCertificate(string $code): ?array
    {
        return Database::fetch(
            "SELECT u.name AS user_name, m.title AS module_title, m.duration_minutes, e.completed_at, e.certificate_valid_until, e.best_score,
                    COALESCE(t.trade_name, t.legal_name) AS company
               FROM training_enrollments e
               JOIN users u ON u.tenant_id = e.tenant_id AND u.id = e.user_id
               JOIN training_modules m ON m.tenant_id = e.tenant_id AND m.id = e.module_id
               JOIN tenants t ON t.id = e.tenant_id
              WHERE e.certificate_code = ? AND e.status = 'concluido'",
            [$code],
        );
    }

    /** @return array<string, int> */
    public function stats(?int $userId = null): array
    {
        $sql = "SELECT COUNT(*) AS enrolled, SUM(e.status = 'concluido') AS completed,
                       SUM(e.status <> 'concluido' AND e.due_date < CURDATE()) AS overdue,
                       SUM(e.status <> 'concluido') AS pending
                  FROM training_enrollments e JOIN training_modules m ON m.tenant_id = e.tenant_id AND m.id = e.module_id AND m.status = 'publicado'
                  JOIN users u ON u.tenant_id = e.tenant_id AND u.id = e.user_id AND u.status = 'active'
                 WHERE e.tenant_id = ?";
        $params = [$this->tenantId()];
        if ($userId !== null) {
            $sql .= ' AND e.user_id = ?';
            $params[] = $userId;
        }
        $row = array_map('intval', Database::fetch($sql, $params) ?? []);
        $row['modules'] = (int) Database::value("SELECT COUNT(*) FROM training_modules WHERE tenant_id = ? AND status = 'publicado'", [$this->tenantId()]);
        return $row;
    }

    public function titleExists(string $title): bool
    {
        return $this->count('title = ?', [$title]) > 0;
    }

    /** @param list<array{question: string, options: list<string>, correct: int}> $questions */
    private function replaceQuestions(int $moduleId, array $questions): void
    {
        Database::execute('DELETE FROM training_questions WHERE tenant_id = ? AND module_id = ?', [$this->tenantId(), $moduleId]);
        foreach (array_values($questions) as $i => $q) {
            Database::insert('training_questions', [
                'tenant_id' => $this->tenantId(), 'module_id' => $moduleId, 'position' => $i + 1,
                'question' => $q['question'], 'options' => json_encode(array_values($q['options']), JSON_UNESCAPED_UNICODE), 'correct_index' => $q['correct'],
            ]);
        }
    }
}
