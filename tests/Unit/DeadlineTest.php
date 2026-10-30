<?php

declare(strict_types=1);

namespace Isis\Tests\Unit;

use Isis\Support\Deadline;
use PHPUnit\Framework\TestCase;

final class DeadlineTest extends TestCase
{
    public function testPrazoDoTitularEDe15Dias(): void
    {
        self::assertSame('2026-11-13', Deadline::requestDue(new \DateTimeImmutable('2026-10-29 23:00')));
    }

    public function testTresDiasUteisPulamFimDeSemana(): void
    {
        // Quarta -> segunda (qui, sex, seg)
        self::assertSame('2026-11-02', Deadline::addBusinessDays(new \DateTimeImmutable('2026-10-28 09:15'), 3));
        // Sexta -> quarta (seg, ter, qua)
        self::assertSame('2026-11-04', Deadline::addBusinessDays(new \DateTimeImmutable('2026-10-30'), 3));
        // Sábado -> quarta
        self::assertSame('2026-11-04', Deadline::addBusinessDays(new \DateTimeImmutable('2026-10-31'), 3));
    }

    public function testDiasRestantes(): void
    {
        self::assertSame(0, Deadline::daysLeft(date('Y-m-d')));
        self::assertSame(2, Deadline::daysLeft(date('Y-m-d', strtotime('+2 days'))));
        self::assertSame(-3, Deadline::daysLeft(date('Y-m-d', strtotime('-3 days'))));
        self::assertNull(Deadline::daysLeft(null));
    }
}
