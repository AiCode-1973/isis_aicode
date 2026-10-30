<?php

declare(strict_types=1);

namespace Isis\Tests\Unit;

use Isis\Repositories\ActivityRepository;
use Isis\Services\RiskMatrix;
use PHPUnit\Framework\TestCase;

final class RiskMatrixTest extends TestCase
{
    public function testClassificacaoPorFaixas(): void
    {
        self::assertSame('baixo', RiskMatrix::levelOf(1, 1));
        self::assertSame('baixo', RiskMatrix::levelOf(2, 2));
        self::assertSame('medio', RiskMatrix::levelOf(1, 5));
        self::assertSame('medio', RiskMatrix::levelOf(3, 3));
        self::assertSame('alto', RiskMatrix::levelOf(2, 5));
        self::assertSame('alto', RiskMatrix::levelOf(3, 5));
        self::assertSame('critico', RiskMatrix::levelOf(4, 4));
        self::assertSame('critico', RiskMatrix::levelOf(5, 5));
    }

    public function testFaixasCobremTodaAEscalaSemSobreposicao(): void
    {
        foreach (range(1, 25) as $score) {
            $level = RiskMatrix::level($score);
            [$min, $max] = RiskMatrix::range($level);
            self::assertTrue($score >= $min && $score <= $max, "score {$score}");
        }
    }

    public function testEscalaForaDoIntervaloERecusada(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RiskMatrix::score(0, 6);
    }

    public function testPendenciasDoInventario(): void
    {
        $base = [
            'purpose' => 'Atender clientes', 'legal_basis_code' => 'art7_v', 'sensitive_legal_basis_code' => null, 'legal_basis_notes' => '',
            'retention_period' => '2 anos', 'owner_user_id' => 1, 'international_transfer' => 0, 'international_details' => null,
            'review_due_date' => null, 'data_categories' => [['is_sensitive' => 0]], 'subject_categories' => [['vulnerable' => 0]],
        ];
        self::assertSame([], ActivityRepository::issues($base));

        $sensitive = $base;
        $sensitive['data_categories'][] = ['is_sensitive' => 1];
        self::assertContains('Há dados sensíveis sem hipótese legal do art. 11.', ActivityRepository::issues($sensitive));

        $incomplete = ['purpose' => '', 'legal_basis_code' => null, 'retention_period' => '', 'owner_user_id' => null,
            'international_transfer' => 1, 'international_details' => '', 'review_due_date' => '2000-01-01', 'legal_basis_notes' => '',
            'data_categories' => [], 'subject_categories' => [['vulnerable' => 1]]];
        // finalidade, hipótese legal, categorias de dados, retenção, responsável, transferência sem detalhes, revisão vencida, vulneráveis
        self::assertCount(8, ActivityRepository::issues($incomplete));
    }
}
