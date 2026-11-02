<?php

declare(strict_types=1);

namespace Isis\Services;

use Isis\Auth\TenantContext;
use Isis\Core\Database;

/**
 * Índice de maturidade da gestão de privacidade, calculado só com o que o ÍSIS já mede
 * (indicador interno de organização; não é certificação nem garantia de conformidade).
 * Também monta as séries mensais dos gráficos do painel.
 */
final class MaturityService
{
    private const array MONTHS = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    private const array MONTH_NAMES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

    /**
     * @param array<string, ?array> $s estatísticas dos repositórios: inventory, risks, actions, requests, incidents, vendors, policies, trainings (null = sem acesso)
     * @return array{score: ?int, level: ?string, pillars: list<array{key: string, label: string, score: int, hint: string, link: string}>}
     */
    public static function compute(array $s): array
    {
        $pillars = [];
        $add = static function (string $key, string $label, float $score, string $hint, string $link) use (&$pillars): void {
            $pillars[] = ['key' => $key, 'label' => $label, 'score' => (int) round(max(0, min(100, $score))), 'hint' => $hint, 'link' => $link];
        };

        if (($i = $s['inventory'] ?? null) !== null) {
            $total = (int) $i['total'];
            if ($total === 0) {
                $add('inventario', 'Inventário de dados', 0, 'Nenhuma operação mapeada ainda.', '/inventario');
            } else {
                $noBasis = (int) $i['no_basis'];
                $overdue = (int) $i['review_overdue'];
                $add('inventario', 'Inventário de dados', 60 * ($total - $noBasis) / $total + 40 * ($total - $overdue) / $total,
                    $noBasis > 0 ? plural($noBasis, 'operação', 'operações') . ' sem hipótese legal.'
                        : ($overdue > 0 ? plural($overdue, 'operação', 'operações') . ' com revisão vencida.' : 'Operações com hipótese legal e revisão em dia.'),
                    '/inventario');
            }
        }

        if (($r = $s['risks'] ?? null) !== null) {
            $open = (int) $r['open'];
            if ((int) $r['total'] === 0) {
                $add('riscos', 'Gestão de riscos', 0, 'Nenhum risco avaliado ainda.', '/riscos');
            } elseif ($open === 0) {
                $add('riscos', 'Gestão de riscos', 100, 'Nenhum risco em aberto.', '/riscos');
            } else {
                $unowned = (int) $r['unowned'];
                $overdue = (int) $r['review_overdue'];
                $add('riscos', 'Gestão de riscos', 100 - 50 * $unowned / $open - 50 * $overdue / $open,
                    $unowned > 0 ? plural($unowned, 'risco aberto', 'riscos abertos') . ' sem responsável.'
                        : ($overdue > 0 ? plural($overdue, 'risco', 'riscos') . ' com revisão vencida.' : 'Riscos abertos com responsável e revisão em dia.'),
                    '/riscos');
            }
        }

        if (($a = $s['actions'] ?? null) !== null) {
            $base = (int) $a['total'] - (int) $a['cancelled'];
            if ($base <= 0) {
                $add('planos', 'Planos de ação', 0, 'Nenhuma ação registrada ainda.', '/planos');
            } else {
                $done = (int) $a['done'];
                $overdue = (int) $a['overdue'];
                $add('planos', 'Planos de ação', 70 * $done / $base + 30 * (1 - $overdue / max(1, $base - $done)),
                    $overdue > 0 ? plural($overdue, 'ação vencida', 'ações vencidas') . '; ' . $done . ' de ' . $base . ' concluídas.' : $done . ' de ' . $base . ' concluídas, sem atrasos.',
                    '/planos');
            }
        }

        $req = $s['requests'] ?? null;
        $inc = $s['incidents'] ?? null;
        if ($req !== null || $inc !== null) {
            $score = 100;
            $hints = [];
            if ($req !== null && (int) $req['open'] > 0 && (int) $req['overdue'] > 0) {
                $score -= 50 * (int) $req['overdue'] / (int) $req['open'];
                $hints[] = plural((int) $req['overdue'], 'solicitação fora do prazo', 'solicitações fora do prazo');
            }
            if ($inc !== null && (int) $inc['anpd_overdue'] > 0) {
                $score -= 50;
                $hints[] = 'comunicação à ANPD atrasada';
            } elseif ($inc !== null && (int) $inc['anpd_pending'] > 0) {
                $score -= 25;
                $hints[] = 'comunicação à ANPD pendente';
            }
            $add('direitos', 'Titulares e incidentes', $score,
                $hints ? ucfirst(implode('; ', $hints)) . '.' : 'Prazos de titulares e da ANPD em dia.',
                $req !== null ? '/titulares' : '/incidentes');
        }

        if (($v = $s['vendors'] ?? null) !== null) {
            $active = (int) $v['active'];
            if ($active === 0) {
                $add('terceiros', 'Fornecedores', 0, 'Nenhum fornecedor cadastrado ainda.', '/fornecedores');
            } else {
                $unassessed = (int) $v['unassessed'];
                $noDpa = (int) $v['no_dpa'];
                $add('terceiros', 'Fornecedores', 100 * (1 - ($unassessed + $noDpa) / (2 * $active)),
                    $noDpa > 0 ? plural($noDpa, 'fornecedor', 'fornecedores') . ' sem cláusulas de proteção.'
                        : ($unassessed > 0 ? plural($unassessed, 'fornecedor', 'fornecedores') . ' sem avaliação.' : 'Fornecedores avaliados e com contrato.'),
                    '/fornecedores');
            }
        }

        $pol = $s['policies'] ?? null;
        $tr = $s['trainings'] ?? null;
        if ($pol !== null || $tr !== null) {
            $parts = [];
            $hints = [];
            if ($pol !== null) {
                $parts[] = (int) $pol['total'] > 0 ? 100 * (int) $pol['published'] / (int) $pol['total'] : 0;
                $hints[] = plural((int) $pol['published'], 'política vigente', 'políticas vigentes');
            }
            if ($tr !== null) {
                $parts[] = (int) $tr['enrolled'] > 0 ? 100 * (int) $tr['completed'] / (int) $tr['enrolled'] : 0;
                $hints[] = (int) $tr['enrolled'] > 0 ? (int) round(100 * (int) $tr['completed'] / (int) $tr['enrolled']) . '% dos treinamentos concluídos' : 'nenhum treinamento designado';
            }
            $add('pessoas', 'Políticas e treinamentos', array_sum($parts) / count($parts), ucfirst(implode('; ', $hints)) . '.', $pol !== null ? '/politicas' : '/treinamentos');
        }

        if ($pillars === []) {
            return ['score' => null, 'level' => null, 'pillars' => []];
        }
        $score = (int) round(array_sum(array_column($pillars, 'score')) / count($pillars));
        return ['score' => $score, 'level' => self::level($score), 'pillars' => $pillars];
    }

    public static function level(int $score): string
    {
        return match (true) {
            $score < 40 => 'Inicial',
            $score < 70 => 'Em evolução',
            $score < 85 => 'Estruturada',
            default => 'Avançada',
        };
    }

    /**
     * Solicitações de titulares recebidas por mês (últimos N meses, incluindo o atual).
     *
     * @return list<array{label: string, value: int, title: string}>
     */
    public static function requestsByMonth(int $months = 6): array
    {
        return self::series(
            "SELECT DATE_FORMAT(received_at, '%Y-%m') AS ym, COUNT(*) AS n FROM dsar_requests WHERE tenant_id = ? AND received_at >= ? GROUP BY ym",
            $months, 'solicitação recebida', 'solicitações recebidas',
        );
    }

    /** Ações concluídas por mês. @return list<array{label: string, value: int, title: string}> */
    public static function actionsDoneByMonth(int $months = 6): array
    {
        return self::series(
            "SELECT DATE_FORMAT(completed_at, '%Y-%m') AS ym, COUNT(*) AS n FROM actions WHERE tenant_id = ? AND status = 'concluida' AND completed_at >= ? GROUP BY ym",
            $months, 'ação concluída', 'ações concluídas',
        );
    }

    /** @return list<array{label: string, value: int, title: string}> */
    private static function series(string $sql, int $months, string $one, string $many): array
    {
        $start = new \DateTimeImmutable('first day of this month midnight');
        $start = $start->modify('-' . ($months - 1) . ' months');
        $counts = array_column(Database::fetchAll($sql, [TenantContext::id(), $start->format('Y-m-d H:i:s')]), 'n', 'ym');
        $out = [];
        for ($k = 0; $k < $months; $k++) {
            $month = $start->modify("+{$k} months");
            $n = (int) ($counts[$month->format('Y-m')] ?? 0);
            $m = (int) $month->format('n') - 1;
            $out[] = ['label' => self::MONTHS[$m], 'value' => $n, 'title' => self::MONTH_NAMES[$m] . ' de ' . $month->format('Y') . ': ' . plural($n, $one, $many)];
        }
        return $out;
    }
}
