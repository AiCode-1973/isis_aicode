<?php

declare(strict_types=1);

namespace Isis\Tests\Unit;

use Isis\Services\MaturityService;
use PHPUnit\Framework\TestCase;

/**
 * Índice de maturidade: cálculo por pilar, pilares ausentes por falta de acesso e níveis.
 */
final class MaturityTest extends TestCase
{
    public function testEmpresaRecemCriadaComecaDoZero(): void
    {
        $r = MaturityService::compute([
            'inventory' => ['total' => 0, 'no_basis' => 0, 'review_overdue' => 0],
            'risks' => ['total' => 0, 'open' => 0, 'unowned' => 0, 'review_overdue' => 0],
            'actions' => ['total' => 0, 'cancelled' => 0, 'done' => 0, 'overdue' => 0],
        ]);
        self::assertSame(0, $r['score']);
        self::assertSame('Inicial', $r['level']);
        self::assertSame(['inventario', 'riscos', 'planos'], array_column($r['pillars'], 'key'));
    }

    public function testTudoEmDiaChegaA100(): void
    {
        $r = MaturityService::compute([
            'inventory' => ['total' => 4, 'no_basis' => 0, 'review_overdue' => 0],
            'risks' => ['total' => 3, 'open' => 2, 'unowned' => 0, 'review_overdue' => 0],
            'actions' => ['total' => 5, 'cancelled' => 1, 'done' => 4, 'overdue' => 0],
            'requests' => ['open' => 1, 'overdue' => 0],
            'incidents' => ['anpd_overdue' => 0, 'anpd_pending' => 0],
            'vendors' => ['active' => 2, 'unassessed' => 0, 'no_dpa' => 0],
            'policies' => ['total' => 2, 'published' => 2],
            'trainings' => ['enrolled' => 4, 'completed' => 4],
        ]);
        self::assertSame(100, $r['score']);
        self::assertSame('Avançada', $r['level']);
    }

    public function testPendenciasReduzemOPilar(): void
    {
        $r = MaturityService::compute([
            'inventory' => ['total' => 5, 'no_basis' => 1, 'review_overdue' => 1],
            'requests' => ['open' => 2, 'overdue' => 1],
            'incidents' => ['anpd_overdue' => 1, 'anpd_pending' => 0],
        ]);
        $byKey = array_column($r['pillars'], null, 'key');
        self::assertSame(80, $byKey['inventario']['score']); // 60*4/5 + 40*4/5
        self::assertSame(25, $byKey['direitos']['score']);   // 100 - 50*1/2 - 50
        self::assertStringContainsString('1 operação sem hipótese legal', $byKey['inventario']['hint']);
        self::assertSame(53, $r['score']);
        self::assertSame('Em evolução', $r['level']);
    }

    public function testSemAcessoANadaNaoHaIndice(): void
    {
        $r = MaturityService::compute(['inventory' => null, 'risks' => null]);
        self::assertNull($r['score']);
        self::assertSame([], $r['pillars']);
    }

    public function testFaixasDosNiveis(): void
    {
        self::assertSame('Inicial', MaturityService::level(39));
        self::assertSame('Em evolução', MaturityService::level(40));
        self::assertSame('Estruturada', MaturityService::level(70));
        self::assertSame('Avançada', MaturityService::level(85));
    }
}
