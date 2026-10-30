<?php

declare(strict_types=1);

namespace Isis\Support;

/**
 * Prazos de referência. Feriados não são considerados: o sistema avisa que o prazo é estimado.
 */
final class Deadline
{
    /** Prazo de resposta ao titular: 15 dias (art. 19, II, para a declaração completa). */
    public const int REQUEST_DAYS = 15;

    /** Comunicação de incidente à ANPD: 3 dias úteis (Resolução CD/ANPD nº 15/2024). */
    public const int INCIDENT_BUSINESS_DAYS = 3;

    public static function requestDue(\DateTimeImmutable $received): string
    {
        return $received->modify('+' . self::REQUEST_DAYS . ' days')->format('Y-m-d');
    }

    public static function addBusinessDays(\DateTimeImmutable $start, int $days): string
    {
        $date = $start;
        while ($days > 0) {
            $date = $date->modify('+1 day');
            if ((int) $date->format('N') < 6) {
                $days--;
            }
        }
        return $date->format('Y-m-d');
    }

    /** Dias até o prazo (negativo = vencido). */
    public static function daysLeft(?string $due): ?int
    {
        if ($due === null || $due === '') {
            return null;
        }
        $today = new \DateTimeImmutable('today');
        $diff = $today->diff(new \DateTimeImmutable($due));
        return $diff->invert ? -$diff->days : $diff->days;
    }
}
