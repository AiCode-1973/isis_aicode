<?php

declare(strict_types=1);

namespace Isis\Tests\Unit;

use Isis\Services\TrainingService;
use Isis\Services\VendorQuestionnaire as Q;
use PHPUnit\Framework\TestCase;

final class ProgramRulesTest extends TestCase
{
    private static function all(string $answer): array
    {
        return array_fill_keys(array_keys(Q::QUESTIONS), $answer);
    }

    public function testFornecedorTodoSimTemRiscoBaixo(): void
    {
        self::assertSame(['score' => 100, 'level' => 'baixo', 'answered' => 12], Q::evaluate(self::all('sim'), false));
    }

    public function testFornecedorTodoNaoTemRiscoAlto(): void
    {
        $r = Q::evaluate(self::all('nao'), false);
        self::assertSame(0, $r['score']);
        self::assertSame('alto', $r['level']);
    }

    public function testNaoSeAplicaSaiDaConta(): void
    {
        $answers = self::all('sim');
        $answers['internacional'] = 'na';
        $answers['certificacao'] = 'na';
        $r = Q::evaluate($answers, false);
        self::assertSame(100, $r['score']);
        self::assertSame(10, $r['answered']);
    }

    public function testDadosSensiveisSobemORiscoMedioParaAlto(): void
    {
        $answers = self::all('parcial'); // nota 50: médio
        self::assertSame('medio', Q::evaluate($answers, false)['level']);
        self::assertSame('alto', Q::evaluate($answers, true)['level']);
    }

    public function testSemClausulasContratuaisNuncaEBaixo(): void
    {
        $answers = self::all('sim');
        $answers['contrato'] = 'nao';
        $r = Q::evaluate($answers, false);
        self::assertGreaterThanOrEqual(80, $r['score']);
        self::assertSame('medio', $r['level']);
    }

    public function testNotaDaAvaliacaoDeTreinamento(): void
    {
        $questions = [
            ['id' => 10, 'correct_index' => 1], ['id' => 11, 'correct_index' => 0],
            ['id' => 12, 'correct_index' => 2], ['id' => 13, 'correct_index' => 3],
        ];
        self::assertSame(100, TrainingService::score($questions, [10 => '1', 11 => '0', 12 => '2', 13 => '3']));
        self::assertSame(50, TrainingService::score($questions, [10 => '1', 11 => '0', 12 => '0']));
        self::assertSame(0, TrainingService::score($questions, [10 => 'x', 11 => '-1']));
        self::assertSame(0, TrainingService::score([], []));
    }
}
