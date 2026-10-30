<?php

declare(strict_types=1);

namespace Isis\Services;

use Isis\Repositories\ActivityRepository;
use Isis\Repositories\DpiaRepository;
use Isis\Repositories\RiskRepository;
use Isis\Repositories\TenantRepository;
use Isis\Support\Labels;

/**
 * Regras do RIPD: quando ele é recomendado, o que falta para enviar à aprovação e o conteúdo
 * completo do relatório (o mesmo usado na impressão e guardado na aprovação).
 */
final class DpiaService
{
    /** Seções que precisam estar preenchidas para enviar à aprovação. */
    public const array REQUIRED_TEXT = [
        'nature' => 'Natureza do tratamento',
        'scope' => 'Escopo do tratamento',
        'context' => 'Contexto do tratamento',
        'purpose' => 'Finalidade do tratamento',
        'necessity' => 'Necessidade e proporcionalidade',
    ];

    /**
     * Indicadores de alto risco que o inventário já mostra para uma operação (dados completos de findFull).
     *
     * @return list<string> códigos de Labels::DPIA_TRIGGER
     */
    public static function suggestTriggers(array $activity): array
    {
        $triggers = [];
        if (array_filter($activity['data_categories'] ?? [], static fn (array $c): bool => (bool) $c['is_sensitive']) !== []) {
            $triggers[] = 'dados_sensiveis';
        }
        if (array_filter($activity['subject_categories'] ?? [], static fn (array $c): bool => (bool) $c['vulnerable']) !== []) {
            $triggers[] = 'vulneraveis';
        }
        if (in_array($activity['subjects_volume'] ?? null, ['ate_100000', 'acima'], true)) {
            $triggers[] = 'larga_escala';
        }
        if (($activity['legal_basis_code'] ?? null) === 'art7_ix') {
            $triggers[] = 'legitimo_interesse';
        }
        if ((int) ($activity['international_transfer'] ?? 0) === 1) {
            $triggers[] = 'transferencia_internacional';
        }
        return $triggers;
    }

    /**
     * Operações ativas com indicadores de alto risco e ainda sem RIPD.
     *
     * @return list<array{id: int, seq: int, name: string, triggers: list<string>}>
     */
    public static function candidates(): array
    {
        $repo = new ActivityRepository();
        $covered = array_flip((new DpiaRepository())->coveredActivityIds());
        $list = [];
        foreach ($repo->search([]) as $row) {
            if ($row['status'] === 'descontinuada' || isset($covered[(int) $row['id']])) {
                continue;
            }
            $triggers = self::suggestTriggers($repo->findFull((int) $row['id']) ?? []);
            if ($triggers !== []) {
                $list[] = ['id' => (int) $row['id'], 'seq' => (int) $row['seq'], 'name' => $row['name'], 'triggers' => $triggers];
            }
        }
        return $list;
    }

    /**
     * O que falta para enviar o RIPD à aprovação.
     *
     * @return list<string>
     */
    public static function missing(array $dpia): array
    {
        $missing = [];
        if (($dpia['activity_ids'] ?? []) === []) {
            $missing[] = 'Vincule ao menos uma operação do inventário.';
        }
        foreach (self::REQUIRED_TEXT as $field => $label) {
            if (trim((string) ($dpia[$field] ?? '')) === '') {
                $missing[] = "Preencha \"{$label}\".";
            }
        }
        if (empty($dpia['conclusion'])) {
            $missing[] = 'Registre a conclusão sobre os riscos.';
        }
        if (trim((string) ($dpia['dpo_opinion'] ?? '')) === '') {
            $missing[] = 'Registre o parecer do encarregado.';
        }
        return $missing;
    }

    /**
     * Conteúdo completo do relatório: dados do RIPD, da empresa, das operações e dos riscos.
     * É o que a impressão mostra e o que fica guardado (congelado) em cada aprovação.
     */
    public static function document(array $dpia): array
    {
        $activities = [];
        $risks = [];
        $activityRepo = new ActivityRepository();
        $riskRepo = new RiskRepository();
        foreach ($dpia['activity_ids'] as $activityId) {
            $a = $activityRepo->findFull($activityId);
            if ($a === null) {
                continue;
            }
            $activities[] = [
                'id' => (int) $a['id'],
                'code' => code('OP', (int) $a['seq']),
                'name' => $a['name'],
                'purpose' => $a['purpose'],
                'controller_role' => Labels::of(Labels::CONTROLLER_ROLE, $a['controller_role']),
                'legal_basis' => $a['legal_basis_code'] ? $a['legal_basis_article'] . ' · ' . $a['legal_basis_name'] : null,
                'sensitive_basis' => $a['sensitive_legal_basis_code'] ? $a['sensitive_basis_article'] . ' · ' . $a['sensitive_basis_name'] : null,
                'data' => array_map(static fn (array $c): string => $c['name'] . ($c['is_sensitive'] ? ' (sensível)' : ''), $a['data_categories']),
                'subjects' => array_map(static fn (array $c): string => $c['name'] . ($c['vulnerable'] ? ' (vulnerável)' : ''), $a['subject_categories']),
                'volume' => Labels::of(Labels::SUBJECTS_VOLUME, $a['subjects_volume']),
                'source' => Labels::of(Labels::DATA_SOURCE, $a['data_source']),
                'systems' => array_column($a['systems'], 'name'),
                'sharings' => array_map(static fn (array $s): string => $s['recipient'] . ($s['purpose'] ? ' · ' . $s['purpose'] : '') . ($s['international'] ? ' (internacional)' : ''), $a['sharings']),
                'retention' => $a['retention_period'],
                'disposal' => $a['disposal_method'],
                'international' => (int) $a['international_transfer'] === 1 ? ($a['international_details'] ?: 'Sim') : null,
                'security' => $a['security_measures'],
                'department' => $a['department_name'],
                'owner' => $a['owner_name'],
            ];
            foreach ($riskRepo->search(['activity_id' => $activityId]) as $r) {
                $risks[(int) $r['id']] = [
                    'code' => code('RSK', (int) $r['seq']),
                    'title' => $r['title'],
                    'activity' => code('OP', (int) $a['seq']),
                    'category' => Labels::of(Labels::RISK_CATEGORY, $r['category']),
                    'cause' => $r['cause'],
                    'consequence' => $r['consequence'],
                    'likelihood' => (int) $r['likelihood'],
                    'impact' => (int) $r['impact'],
                    'residual_likelihood' => $r['residual_likelihood'] !== null ? (int) $r['residual_likelihood'] : null,
                    'residual_impact' => $r['residual_impact'] !== null ? (int) $r['residual_impact'] : null,
                    'treatment' => Labels::of(Labels::RISK_TREATMENT, $r['treatment']),
                    'treatment_notes' => $r['treatment_notes'],
                    'status' => Labels::of(Labels::RISK_STATUS, $r['status']),
                    'owner' => $r['owner_name'],
                    'actions_count' => (int) $r['actions_count'],
                    'actions_done' => (int) $r['actions_done'],
                ];
            }
        }
        $risks = array_values($risks);
        usort($risks, static fn (array $x, array $y): int => $y['likelihood'] * $y['impact'] <=> $x['likelihood'] * $x['impact']);

        $tenant = (new TenantRepository())->current();
        $fields = ['title', 'version', 'need_justification', 'nature', 'scope', 'context', 'purpose', 'stakeholders', 'necessity',
            'safeguards', 'conclusion', 'conclusion_notes', 'dpo_opinion', 'owner_name', 'review_due_date'];
        return [
            'dpia' => ['code' => code('RIPD', (int) $dpia['seq'])] + array_intersect_key($dpia, array_flip($fields))
                + ['triggers' => array_map(static fn (string $t): string => Labels::of(Labels::DPIA_TRIGGER, $t), $dpia['triggers'])],
            'tenant' => [
                'legal_name' => $tenant['legal_name'], 'trade_name' => $tenant['trade_name'], 'cnpj' => $tenant['cnpj'],
                'segment' => $tenant['segment_name'] ?? null, 'dpo_name' => $tenant['dpo_name'], 'dpo_email' => $tenant['dpo_email'],
            ],
            'activities' => $activities,
            'risks' => $risks,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }
}
