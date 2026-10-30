<?php

declare(strict_types=1);

namespace Isis\Services;

/**
 * Questionário de avaliação de fornecedores quanto à proteção de dados.
 * Respostas: sim, parcial, nao, na (não se aplica). Nota = pontos obtidos / pontos aplicáveis.
 */
final class VendorQuestionnaire
{
    /** chave => [pergunta, peso] */
    public const array QUESTIONS = [
        'contrato' => ['O contrato tem cláusulas de proteção de dados (finalidade, sigilo, segurança, incidentes, fim do tratamento)?', 3],
        'instrucoes' => ['O fornecedor trata os dados apenas conforme as instruções da empresa?', 2],
        'politica_seguranca' => ['Possui política de segurança da informação formalizada?', 2],
        'acesso' => ['Controla e restringe o acesso aos dados (usuários individuais, perfis, revisão periódica)?', 2],
        'criptografia' => ['Usa criptografia em trânsito e em repouso para os dados pessoais?', 2],
        'backup' => ['Mantém cópias de segurança testadas e plano de continuidade?', 1],
        'incidentes' => ['Compromete-se a comunicar incidentes à empresa em prazo curto e definido?', 3],
        'suboperadores' => ['Só usa suboperadores com autorização da empresa e mesmas obrigações?', 2],
        'internacional' => ['Se houver transferência internacional, há mecanismo previsto no art. 33?', 2],
        'certificacao' => ['Possui certificação ou relatório independente (ISO 27001, SOC 2 ou similar)?', 1],
        'treinamento' => ['Treina sua equipe em privacidade e segurança?', 1],
        'devolucao' => ['Devolve ou elimina os dados ao fim do contrato, com comprovação?', 2],
    ];

    public const array ANSWERS = ['sim' => 'Sim', 'parcial' => 'Parcialmente', 'nao' => 'Não', 'na' => 'Não se aplica'];

    /**
     * @param array<string, string> $answers
     * @return array{score: int, level: 'baixo'|'medio'|'alto', answered: int}
     */
    public static function evaluate(array $answers, bool $sensitiveData): array
    {
        $possible = 0;
        $obtained = 0.0;
        $answered = 0;
        foreach (self::QUESTIONS as $key => [, $weight]) {
            $answer = $answers[$key] ?? 'nao';
            if ($answer === 'na') {
                continue;
            }
            $answered++;
            $possible += $weight;
            $obtained += match ($answer) {
                'sim' => $weight,
                'parcial' => $weight / 2,
                default => 0,
            };
        }
        $score = $possible > 0 ? (int) round($obtained / $possible * 100) : 0;
        $level = $score >= 80 ? 'baixo' : ($score >= 50 ? 'medio' : 'alto');
        // Quem trata dados sensíveis precisa de nota alta para não ser considerado de risco elevado.
        if ($sensitiveData && $level === 'medio') {
            $level = 'alto';
        }
        // Sem cláusulas contratuais de proteção de dados o risco nunca é baixo.
        if (($answers['contrato'] ?? 'nao') === 'nao' && $level === 'baixo') {
            $level = 'medio';
        }
        return ['score' => $score, 'level' => $level, 'answered' => $answered];
    }
}
