<?php

declare(strict_types=1);

namespace Isis\Services;

use Isis\Audit\AuditLogger;
use Isis\Core\Database;
use Isis\Repositories\TrainingRepository;

/**
 * Correção da avaliação, aprovação e emissão de certificado.
 */
final class TrainingService
{
    /**
     * @param list<array{id: int|string, correct_index: int|string}> $questions
     * @param array<int|string, string> $answers id da pergunta => índice escolhido
     */
    public static function score(array $questions, array $answers): int
    {
        if ($questions === []) {
            return 0;
        }
        $right = 0;
        foreach ($questions as $q) {
            $given = $answers[$q['id']] ?? null;
            if ($given !== null && ctype_digit((string) $given) && (int) $given === (int) $q['correct_index']) {
                $right++;
            }
        }
        return (int) round($right / count($questions) * 100);
    }

    /** Código de certificado verificável: ISIS-XXXX-XXXX. */
    public static function certificateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $raw = '';
            for ($i = 0; $i < 8; $i++) {
                $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = 'ISIS-' . substr($raw, 0, 4) . '-' . substr($raw, 4);
        } while (Database::value('SELECT 1 FROM training_enrollments WHERE certificate_code = ?', [$code]) !== null);
        return $code;
    }

    /**
     * Registra a tentativa. Retorna [nota, aprovado, código do certificado].
     *
     * @param array<int|string, string> $answers
     * @return array{score: int, passed: bool, certificate: ?string}
     */
    public static function submit(array $module, array $enrollment, array $answers): array
    {
        $repo = new TrainingRepository();
        $score = self::score($repo->questions((int) $module['id']), $answers);
        $passed = $score >= (int) $module['passing_score'];
        $data = [
            'attempts' => (int) $enrollment['attempts'] + 1,
            'best_score' => max($score, (int) ($enrollment['best_score'] ?? 0)),
        ];
        $certificate = $enrollment['certificate_code'];
        if ($passed && $enrollment['status'] !== 'concluido') {
            $certificate = self::certificateCode();
            $data += [
                'status' => 'concluido',
                'completed_at' => date('Y-m-d H:i:s'),
                'certificate_code' => $certificate,
                'certificate_valid_until' => date('Y-m-d', strtotime('+' . max(1, (int) $module['certificate_months']) . ' months')),
            ];
        } elseif (!$passed && $enrollment['status'] !== 'concluido') {
            $data['status'] = 'reprovado';
        }
        $repo->saveEnrollment((int) $enrollment['id'], $data);
        AuditLogger::log($passed ? 'training.passed' : 'training.failed', 'training', (int) $module['id'], null, [
            'nota' => $score, 'minimo' => (int) $module['passing_score'], 'tentativa' => $data['attempts'], 'certificado' => $passed ? $certificate : null,
        ]);
        return ['score' => $score, 'passed' => $passed, 'certificate' => $passed ? $certificate : null];
    }
}
