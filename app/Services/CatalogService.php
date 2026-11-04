<?php

declare(strict_types=1);

namespace Isis\Services;

use Isis\Repositories\CatalogRepository;
use Isis\Repositories\SegmentRepository;

/**
 * Sugestões de catálogo a partir do segmento (só acrescenta o que ainda não existe).
 */
final class CatalogService
{
    /** Palavras que indicam dado sensível (art. 5º, II) nas sugestões dos modelos. */
    private const array SENSITIVE_HINTS = ['saúde', 'genétic', 'biométric', 'religi', 'étnic', 'racial', 'sindical', 'vida sexual', 'polític'];
    private const array VULNERABLE_HINTS = ['menores', 'crianças', 'adolescentes', 'idosos'];

    /** @return array{data: int, subjects: int} quantidades criadas */
    public static function importSuggestions(string $segmentCode): array
    {
        $template = SegmentRepository::template($segmentCode);
        $created = ['data' => 0, 'subjects' => 0];

        $data = new CatalogRepository('dados');
        foreach ($template['data_categories'] ?? [] as $name) {
            if (!$data->nameExists($name)) {
                $data->create($name, self::isSensitive($name) ? 1 : 0);
                $created['data']++;
            }
        }
        $subjects = new CatalogRepository('titulares');
        foreach ($template['subject_categories'] ?? [] as $name) {
            if (!$subjects->nameExists($name)) {
                $subjects->create($name, self::isVulnerable($name) ? 1 : 0);
                $created['subjects']++;
            }
        }
        return $created;
    }

    /**
     * Acrescenta só as categorias escolhidas (assistente de configuração), marcando sensível e vulnerável pelo nome.
     *
     * @param list<string> $dataNames
     * @param list<string> $subjectNames
     * @return array{data: int, subjects: int}
     */
    public static function importSelected(array $dataNames, array $subjectNames): array
    {
        $created = ['data' => 0, 'subjects' => 0];
        $data = new CatalogRepository('dados');
        foreach ($dataNames as $name) {
            if (!$data->nameExists($name)) {
                $data->create($name, self::isSensitive($name) ? 1 : 0);
                $created['data']++;
            }
        }
        $subjects = new CatalogRepository('titulares');
        foreach ($subjectNames as $name) {
            if (!$subjects->nameExists($name)) {
                $subjects->create($name, self::isVulnerable($name) ? 1 : 0);
                $created['subjects']++;
            }
        }
        return $created;
    }

    public static function isSensitive(string $name): bool
    {
        return self::matches($name, self::SENSITIVE_HINTS);
    }

    public static function isVulnerable(string $name): bool
    {
        return self::matches($name, self::VULNERABLE_HINTS);
    }

    /** @param list<string> $hints */
    private static function matches(string $name, array $hints): bool
    {
        $lower = mb_strtolower($name);
        foreach ($hints as $hint) {
            if (str_contains($lower, $hint)) {
                return true;
            }
        }
        return false;
    }
}
