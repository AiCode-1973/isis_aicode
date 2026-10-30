<?php

declare(strict_types=1);

namespace Isis\Services;

/**
 * Matriz de risco 5x5 (probabilidade x impacto). Nível = produto, classificado em quatro faixas.
 */
final class RiskMatrix
{
    public static function score(int $likelihood, int $impact): int
    {
        if ($likelihood < 1 || $likelihood > 5 || $impact < 1 || $impact > 5) {
            throw new \InvalidArgumentException('Probabilidade e impacto vão de 1 a 5.');
        }
        return $likelihood * $impact;
    }

    /** @return 'baixo'|'medio'|'alto'|'critico' */
    public static function level(int $score): string
    {
        return match (true) {
            $score >= 16 => 'critico',
            $score >= 10 => 'alto',
            $score >= 5 => 'medio',
            default => 'baixo',
        };
    }

    public static function levelOf(int $likelihood, int $impact): string
    {
        return self::level(self::score($likelihood, $impact));
    }

    public static function label(string $level): string
    {
        return ['baixo' => 'Baixo', 'medio' => 'Médio', 'alto' => 'Alto', 'critico' => 'Crítico'][$level] ?? $level;
    }

    /** Classe CSS do selo do nível. */
    public static function badge(string $level): string
    {
        return ['baixo' => 'badge-ok', 'medio' => 'badge-warn', 'alto' => 'badge-orange', 'critico' => 'badge-danger'][$level] ?? '';
    }

    /** Faixa de score (mínimo) de cada nível, para filtros SQL. @return array{0: int, 1: int} */
    public static function range(string $level): array
    {
        return ['baixo' => [1, 4], 'medio' => [5, 9], 'alto' => [10, 15], 'critico' => [16, 25]][$level] ?? [1, 25];
    }
}
