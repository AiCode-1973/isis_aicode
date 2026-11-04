<?php

declare(strict_types=1);

namespace Isis\Services;

use Isis\Auth\Auth;
use Isis\Auth\Permission as P;
use Isis\Auth\TenantContext;
use Isis\Core\Database;
use Isis\Support\Labels;

/**
 * Central "O que preciso fazer?": junta numa lista única o que está pendente em todos os módulos,
 * com prazo, responsável, prioridade e o próximo passo recomendado. Só lê; cada item leva à tela do módulo.
 *
 * Cada fonte respeita a permissão do perfil (e o colaborador só vê as próprias tarefas).
 */
final class TaskCenterService
{
    public const array PRIORITIES = [4 => 'Urgente', 3 => 'Alta', 2 => 'Média', 1 => 'Baixa'];

    public const array MODULES = [
        'acoes' => 'Planos de ação',
        'titulares' => 'Solicitações de titulares',
        'incidentes' => 'Incidentes',
        'riscos' => 'Riscos',
        'inventario' => 'Inventário',
        'ripd' => 'Relatórios de impacto',
        'fornecedores' => 'Fornecedores',
        'politicas' => 'Políticas',
        'treinamentos' => 'Treinamentos',
    ];

    public const array ICONS = [
        'acoes' => 'check', 'titulares' => 'inbox', 'incidentes' => 'siren', 'riscos' => 'target', 'inventario' => 'database',
        'ripd' => 'scale', 'fornecedores' => 'truck', 'politicas' => 'file', 'treinamentos' => 'graduation',
    ];

    /** Riscos, inventário e fornecedores entram na lista quando a revisão vence nesta janela. */
    private const int REVIEW_WINDOW_DAYS = 30;

    private int $tenantId;
    private int $me;
    private string $today;

    public function __construct(?string $today = null)
    {
        $this->tenantId = (int) TenantContext::id();
        $this->me = (int) Auth::id();
        $this->today = $today ?? date('Y-m-d');
    }

    /**
     * @return list<array{key: string, module: string, code: string, title: string, reason: string, next: string, link: string,
     *                    due: ?string, owner_id: ?int, owner: ?string, priority: int, mine: bool}>
     */
    public function tasks(): array
    {
        $tasks = [
            ...(Auth::can(P::ACTIONS_VIEW) ? $this->actions() : []),
            ...(Auth::can(P::REQUESTS_VIEW) ? $this->requests() : []),
            ...(Auth::can(P::INCIDENTS_VIEW) ? $this->incidents() : []),
            ...(Auth::can(P::RISKS_VIEW) ? $this->risks() : []),
            ...(Auth::can(P::INVENTORY_VIEW) ? $this->inventory() : []),
            ...(Auth::can(P::DPIA_VIEW) ? $this->dpias() : []),
            ...(Auth::can(P::VENDORS_VIEW) ? $this->vendors() : []),
            ...$this->policies(),
            ...$this->trainings(),
        ];
        usort($tasks, static fn (array $a, array $b): int => [$b['priority'], $a['due'] === null, $a['due'], $a['module'], $a['code']]
            <=> [$a['priority'], $b['due'] === null, $b['due'], $b['module'], $b['code']]);
        return $tasks;
    }

    /**
     * @param list<array> $tasks
     * @param array{escopo?: string, prioridade?: string, modulo?: string, prazo?: string} $filters
     * @return list<array>
     */
    public function filter(array $tasks, array $filters): array
    {
        return array_values(array_filter($tasks, function (array $t) use ($filters): bool {
            if (($filters['escopo'] ?? '') === 'minhas' && !$t['mine']) {
                return false;
            }
            if (($filters['escopo'] ?? '') === 'sem_responsavel' && $t['owner_id'] !== null) {
                return false;
            }
            if (($p = $filters['prioridade'] ?? '') !== '' && (string) $t['priority'] !== $p) {
                return false;
            }
            if (($m = $filters['modulo'] ?? '') !== '' && $t['module'] !== $m) {
                return false;
            }
            return match ($filters['prazo'] ?? '') {
                'vencidas' => $t['due'] !== null && $t['due'] < $this->today,
                '7' => $t['due'] !== null && $t['due'] <= $this->plus(7),
                '30' => $t['due'] !== null && $t['due'] <= $this->plus(30),
                default => true,
            };
        }));
    }

    /** @param list<array> $tasks @return array<string, int> */
    public function summary(array $tasks): array
    {
        $count = static fn (callable $f): int => count(array_filter($tasks, $f));
        return [
            'total' => count($tasks),
            'urgent' => $count(static fn (array $t): bool => $t['priority'] === 4),
            'overdue' => $count(fn (array $t): bool => $t['due'] !== null && $t['due'] < $this->today),
            'week' => $count(fn (array $t): bool => $t['due'] !== null && $t['due'] >= $this->today && $t['due'] <= $this->plus(7)),
            'unowned' => $count(static fn (array $t): bool => $t['owner_id'] === null),
            'mine' => $count(static fn (array $t): bool => $t['mine']),
        ];
    }

    /** Dias até o prazo (negativo = vencido). */
    public function daysLeft(?string $due): ?int
    {
        return $due === null ? null : (int) ((strtotime($due) - strtotime($this->today)) / 86400);
    }

    // ---------------------------------------------------------------- fontes

    private function actions(): array
    {
        $own = AccessScope::onlyOwnActions();
        $rows = Database::fetchAll(
            "SELECT x.id, x.seq, x.title, x.priority, x.status, x.due_date, x.owner_user_id, u.name AS owner_name
               FROM actions x LEFT JOIN users u ON u.tenant_id = x.tenant_id AND u.id = x.owner_user_id
              WHERE x.tenant_id = ? AND x.status IN ('pendente','em_andamento')" . ($own ? ' AND x.owner_user_id = ?' : ''),
            $own ? [$this->tenantId, $this->me] : [$this->tenantId],
        );
        $base = ['alta' => 3, 'media' => 2, 'baixa' => 1];
        return array_map(fn (array $r): array => $this->task(
            'acoes', (int) $r['id'], code('ACT', (int) $r['seq']), $r['title'],
            $r['status'] === 'pendente' ? 'Ação ainda não iniciada' : 'Ação em andamento',
            match (true) {
                $r['owner_user_id'] === null => 'Definir um responsável e um prazo para a ação.',
                $r['due_date'] === null => 'Definir um prazo para a ação.',
                $r['status'] === 'pendente' => 'Iniciar a ação e registrar o primeiro andamento.',
                default => 'Registrar o andamento ou concluir com a evidência.',
            },
            '/planos/' . $r['id'], $r['due_date'], $r['owner_user_id'], $r['owner_name'], $base[$r['priority']] ?? 2,
        ), $rows);
    }

    private function requests(): array
    {
        $rows = Database::fetchAll(
            "SELECT r.id, r.protocol, r.right_type, r.status, r.due_date, r.assigned_user_id, u.name AS owner_name
               FROM dsar_requests r LEFT JOIN users u ON u.tenant_id = r.tenant_id AND u.id = r.assigned_user_id
              WHERE r.tenant_id = ? AND r.status IN ('recebida','em_verificacao','em_atendimento')",
            [$this->tenantId],
        );
        return array_map(fn (array $r): array => $this->task(
            'titulares', (int) $r['id'], $r['protocol'], 'Pedido do titular: ' . mb_strtolower(self::shortRight($r['right_type'])),
            label(Labels::REQUEST_STATUS, $r['status']) . '. A lei dá 15 dias para responder.',
            match ($r['status']) {
                'recebida' => $r['assigned_user_id'] === null ? 'Designar quem vai atender e confirmar a identidade do titular.' : 'Confirmar a identidade do titular.',
                'em_verificacao' => 'Concluir a verificação de identidade e iniciar o atendimento.',
                default => 'Responder ao titular e registrar a resposta.',
            },
            '/titulares/' . $r['id'], $r['due_date'], $r['assigned_user_id'], $r['owner_name'], 3,
        ), $rows);
    }

    private function incidents(): array
    {
        $rows = Database::fetchAll(
            "SELECT i.id, i.seq, i.title, i.status, i.severity, i.risk_to_subjects, i.anpd_deadline, i.anpd_notified_at, i.owner_user_id, u.name AS owner_name
               FROM incidents i LEFT JOIN users u ON u.tenant_id = i.tenant_id AND u.id = i.owner_user_id
              WHERE i.tenant_id = ? AND i.status IN ('comunicado','em_analise','contido')",
            [$this->tenantId],
        );
        return array_map(function (array $r): array {
            $notify = $r['risk_to_subjects'] === 'relevante' && $r['anpd_notified_at'] === null;
            return $this->task(
                'incidentes', (int) $r['id'], code('INC', (int) $r['seq']), $r['title'],
                $notify ? 'Risco relevante aos titulares sem comunicação à ANPD' : label(Labels::INCIDENT_STATUS, $r['status']),
                match (true) {
                    $notify => 'Comunicar a ANPD e os titulares afetados (art. 48) e registrar no incidente.',
                    $r['status'] === 'comunicado' => 'Fazer a triagem: avaliar a gravidade e o risco aos titulares.',
                    $r['status'] === 'em_analise' => 'Conter o incidente e registrar a investigação.',
                    default => 'Registrar as medidas finais e encerrar o incidente.',
                },
                '/incidentes/' . $r['id'], $notify ? $r['anpd_deadline'] : null, $r['owner_user_id'], $r['owner_name'],
                $notify ? 4 : ($r['status'] === 'comunicado' || in_array($r['severity'], ['alta', 'critica'], true) ? 3 : 2),
            );
        }, $rows);
    }

    private function risks(): array
    {
        $rows = Database::fetchAll(
            "SELECT r.id, r.seq, r.title, r.likelihood * r.impact AS score, r.review_date, r.owner_user_id, u.name AS owner_name,
                    (SELECT COUNT(*) FROM actions x WHERE x.tenant_id = r.tenant_id AND x.risk_id = r.id AND x.status IN ('pendente','em_andamento','concluida')) AS action_count
               FROM risks r LEFT JOIN users u ON u.tenant_id = r.tenant_id AND u.id = r.owner_user_id
              WHERE r.tenant_id = ? AND r.status IN ('identificado','em_tratamento')
                AND (r.likelihood * r.impact >= 10 OR r.owner_user_id IS NULL OR r.review_date <= ?)",
            [$this->tenantId, $this->plus(self::REVIEW_WINDOW_DAYS)],
        );
        $tasks = [];
        foreach ($rows as $r) {
            $score = (int) $r['score'];
            $level = RiskMatrix::label(RiskMatrix::level($score));
            $reviewDue = $r['review_date'] !== null && $r['review_date'] <= $this->plus(self::REVIEW_WINDOW_DAYS);
            $tasks[] = $this->task(
                'riscos', (int) $r['id'], code('RSK', (int) $r['seq']), $r['title'],
                'Risco ' . mb_strtolower($level) . ' em aberto' . ($reviewDue ? ', com revisão marcada' : ''),
                match (true) {
                    $r['owner_user_id'] === null => 'Definir o responsável pelo risco.',
                    $score >= 10 && (int) $r['action_count'] === 0 => 'Criar ações de tratamento para reduzir o risco.',
                    $reviewDue => 'Revisar a probabilidade, o impacto e o tratamento.',
                    default => 'Acompanhar as ações de tratamento até reduzir o risco.',
                },
                '/riscos/' . $r['id'], $reviewDue ? $r['review_date'] : null, $r['owner_user_id'], $r['owner_name'],
                $score >= 16 ? 3 : ($score >= 10 ? 2 : 1),
            );
        }
        return $tasks;
    }

    private function inventory(): array
    {
        $rows = Database::fetchAll(
            "SELECT a.id, a.seq, a.name, a.status, a.legal_basis_code, a.review_due_date, a.owner_user_id, u.name AS owner_name
               FROM processing_activities a LEFT JOIN users u ON u.tenant_id = a.tenant_id AND u.id = a.owner_user_id
              WHERE a.tenant_id = ? AND a.status <> 'descontinuada'
                AND (a.legal_basis_code IS NULL OR a.status IN ('rascunho','em_revisao') OR a.review_due_date <= ?)",
            [$this->tenantId, $this->plus(self::REVIEW_WINDOW_DAYS)],
        );
        return array_map(function (array $r): array {
            $noBasis = $r['legal_basis_code'] === null;
            $reviewDue = $r['review_due_date'] !== null && $r['review_due_date'] <= $this->plus(self::REVIEW_WINDOW_DAYS);
            return $this->task(
                'inventario', (int) $r['id'], code('OP', (int) $r['seq']), $r['name'],
                match (true) {
                    $noBasis => 'Operação sem hipótese legal',
                    $r['status'] === 'rascunho' => 'Cadastro em rascunho',
                    $r['status'] === 'em_revisao' => 'Operação em revisão',
                    $r['review_due_date'] < $this->today => 'Revisão periódica vencida',
                    default => 'Revisão periódica marcada',
                },
                match (true) {
                    $noBasis => 'Definir a hipótese legal (art. 7º ou 11) que justifica o tratamento.',
                    $r['status'] === 'rascunho' => 'Completar o cadastro e marcar a operação como ativa.',
                    $r['status'] === 'em_revisao' => 'Concluir a revisão e reativar a operação.',
                    default => 'Revisar a operação: dados, finalidade, compartilhamentos e retenção.',
                },
                '/inventario/' . $r['id'], $reviewDue ? $r['review_due_date'] : null, $r['owner_user_id'], $r['owner_name'],
                $noBasis ? 3 : 1,
            );
        }, $rows);
    }

    private function dpias(): array
    {
        $rows = Database::fetchAll(
            "SELECT d.id, d.seq, d.title, d.status, d.review_due_date, d.owner_user_id, u.name AS owner_name
               FROM dpias d LEFT JOIN users u ON u.tenant_id = d.tenant_id AND u.id = d.owner_user_id
              WHERE d.tenant_id = ? AND (d.status IN ('rascunho','em_aprovacao') OR d.review_due_date <= ?)",
            [$this->tenantId, $this->plus(self::REVIEW_WINDOW_DAYS)],
        );
        return array_map(fn (array $r): array => $this->task(
            'ripd', (int) $r['id'], code('RIPD', (int) $r['seq']), $r['title'],
            label(Labels::DPIA_STATUS, $r['status']),
            match ($r['status']) {
                'rascunho' => 'Concluir a elaboração e enviar para aprovação.',
                'em_aprovacao' => Auth::can(P::DPIA_APPROVE) ? 'Analisar e aprovar o relatório.' : 'Aguardar a aprovação do encarregado.',
                default => 'Revisar o relatório e aprovar uma nova versão.',
            },
            '/ripd/' . $r['id'], $r['status'] === 'aprovado' ? $r['review_due_date'] : null, $r['owner_user_id'], $r['owner_name'],
            $r['status'] === 'em_aprovacao' && Auth::can(P::DPIA_APPROVE) ? 3 : 2,
        ), $rows);
    }

    private function vendors(): array
    {
        $rows = Database::fetchAll(
            "SELECT v.id, v.seq, v.name, v.risk_level, v.last_assessment_at, v.next_review_date, v.owner_user_id, u.name AS owner_name,
                    (v.data_access = 1 AND NOT EXISTS (SELECT 1 FROM vendor_contracts c WHERE c.tenant_id = v.tenant_id AND c.vendor_id = v.id AND c.has_data_clauses = 1)) AS no_dpa,
                    (SELECT MIN(c.end_date) FROM vendor_contracts c WHERE c.tenant_id = v.tenant_id AND c.vendor_id = v.id AND c.end_date BETWEEN ? AND ?) AS contract_end
               FROM vendors v LEFT JOIN users u ON u.tenant_id = v.tenant_id AND u.id = v.owner_user_id
              WHERE v.tenant_id = ? AND v.status <> 'encerrado'",
            [$this->today, $this->plus(30), $this->tenantId],
        );
        $tasks = [];
        foreach ($rows as $r) {
            $unassessed = $r['last_assessment_at'] === null;
            $reviewDue = $r['next_review_date'] !== null && $r['next_review_date'] <= $this->plus(self::REVIEW_WINDOW_DAYS);
            if (!$unassessed && !$reviewDue && !(int) $r['no_dpa'] && $r['contract_end'] === null) {
                continue;
            }
            $tasks[] = $this->task(
                'fornecedores', (int) $r['id'], code('FOR', (int) $r['seq']), $r['name'],
                self::sentence(implode('; ', array_filter([
                    match (true) {
                        $unassessed => 'Fornecedor ainda não avaliado',
                        (bool) (int) $r['no_dpa'] => 'Trata dados sem contrato com cláusulas de proteção',
                        $r['contract_end'] !== null => null,
                        default => 'Reavaliação periódica',
                    },
                    $r['contract_end'] !== null ? 'contrato vence em ' . fmt_date($r['contract_end']) : null,
                ]))),
                match (true) {
                    $unassessed => 'Aplicar o questionário de avaliação do fornecedor.',
                    (bool) (int) $r['no_dpa'] => 'Incluir no contrato as cláusulas de proteção de dados e anexar a versão assinada.',
                    $r['contract_end'] !== null => 'Renovar ou encerrar o contrato e atualizar o cadastro.',
                    default => 'Reavaliar o fornecedor e atualizar o nível de risco.',
                },
                '/fornecedores/' . $r['id'], $r['contract_end'] ?? ($reviewDue ? $r['next_review_date'] : null), $r['owner_user_id'], $r['owner_name'],
                $r['risk_level'] === 'alto' || (int) $r['no_dpa'] ? 3 : 2,
            );
        }
        return $tasks;
    }

    private function policies(): array
    {
        $tasks = [];
        if (Auth::can(P::POLICIES_EDIT) || Auth::can(P::POLICIES_APPROVE)) {
            $rows = Database::fetchAll(
                "SELECT p.id, p.seq, p.title, p.owner_user_id, u.name AS owner_name, cv.valid_until,
                        EXISTS (SELECT 1 FROM policy_versions v WHERE v.tenant_id = p.tenant_id AND v.policy_id = p.id AND v.status = 'em_aprovacao') AS awaiting
                   FROM policies p
              LEFT JOIN users u ON u.tenant_id = p.tenant_id AND u.id = p.owner_user_id
              LEFT JOIN policy_versions cv ON cv.tenant_id = p.tenant_id AND cv.id = p.current_version_id
                  WHERE p.tenant_id = ? AND p.archived_at IS NULL",
                [$this->tenantId],
            );
            foreach ($rows as $r) {
                $awaiting = (bool) (int) $r['awaiting'] && Auth::can(P::POLICIES_APPROVE);
                $expiring = $r['valid_until'] !== null && $r['valid_until'] <= $this->plus(30) && Auth::can(P::POLICIES_EDIT);
                if (!$awaiting && !$expiring) {
                    continue;
                }
                $tasks[] = $this->task(
                    'politicas', (int) $r['id'], code('POL', (int) $r['seq']), $r['title'],
                    $awaiting ? 'Nova versão aguardando aprovação' : 'Validade da versão vigente terminando',
                    $awaiting ? 'Ler a nova versão e aprovar ou devolver com comentários.' : 'Revisar a política e enviar uma nova versão para aprovação.',
                    '/politicas/' . $r['id'], $expiring ? $r['valid_until'] : null, $r['owner_user_id'], $r['owner_name'], $awaiting ? 3 : 2,
                );
            }
        }
        if (Auth::can(P::POLICIES_VIEW)) {
            $me = Auth::user();
            foreach ((new \Isis\Repositories\PolicyRepository())->pendingForUser($this->me) as $p) {
                $tasks[] = $this->task(
                    'politicas', (int) $p['id'], code('POL', (int) $p['seq']) . '·ciencia', $p['title'],
                    'Política publicada aguardando a sua ciência', 'Ler a política e registrar a ciência.',
                    '/politicas/' . $p['id'], null, $this->me, $me['name'] ?? null, 2,
                );
            }
        }
        return $tasks;
    }

    private function trainings(): array
    {
        $tasks = [];
        if (Auth::can(P::TRAININGS_VIEW)) {
            $me = Auth::user();
            foreach ((new \Isis\Repositories\TrainingRepository())->forUser($this->me) as $t) {
                if ($t['enrollment_status'] === 'concluido') {
                    continue;
                }
                $tasks[] = $this->task(
                    'treinamentos', (int) $t['id'], code('TRN', (int) $t['seq']) . '·meu', $t['title'],
                    $t['enrollment_status'] === 'reprovado' ? 'Avaliação não atingiu a nota mínima' : 'Treinamento designado para você',
                    $t['enrollment_status'] === 'reprovado' ? 'Rever o conteúdo e refazer a avaliação.' : 'Fazer o treinamento e a avaliação.',
                    '/treinamentos/' . $t['id'], $t['due_date'], $this->me, $me['name'] ?? null, 1,
                );
            }
        }
        if (Auth::can(P::TRAININGS_MANAGE)) {
            // Para quem gerencia: participações vencidas de outras pessoas, para cobrar.
            $rows = Database::fetchAll(
                "SELECT m.id, m.seq, m.title, e.due_date, e.user_id, u.name AS owner_name
                   FROM training_enrollments e
                   JOIN training_modules m ON m.tenant_id = e.tenant_id AND m.id = e.module_id AND m.status = 'publicado'
                   JOIN users u ON u.tenant_id = e.tenant_id AND u.id = e.user_id AND u.status = 'active'
                  WHERE e.tenant_id = ? AND e.status <> 'concluido' AND e.due_date < ? AND e.user_id <> ?",
                [$this->tenantId, $this->today, $this->me],
            );
            foreach ($rows as $r) {
                $tasks[] = $this->task(
                    'treinamentos', (int) $r['id'], code('TRN', (int) $r['seq']) . '·' . $r['user_id'], $r['title'],
                    'Participação de ' . $r['owner_name'] . ' com prazo vencido',
                    'Lembrar ' . $r['owner_name'] . ' de concluir ou ajustar o prazo.',
                    '/treinamentos/' . $r['id'], $r['due_date'], (int) $r['user_id'], $r['owner_name'], 1,
                );
            }
        }
        return $tasks;
    }

    // ---------------------------------------------------------------- apoio

    /** Monta a tarefa; o prazo sobe a prioridade (vencido = urgente, até 3 dias = alta, até 14 dias = média). */
    private function task(string $module, int $id, string $code, string $title, string $reason, string $next, string $link,
                          ?string $due, mixed $ownerId, ?string $ownerName, int $base): array
    {
        $days = $this->daysLeft($due);
        $byDue = match (true) {
            $days === null => 1,
            $days < 0 => 4,
            $days <= 3 => 3,
            $days <= 14 => 2,
            default => 1,
        };
        $ownerId = $ownerId !== null ? (int) $ownerId : null;
        return [
            'key' => $module . ':' . $id . ':' . $code,
            'module' => $module,
            'code' => (string) preg_replace('/·.*$/u', '', $code),
            'title' => $title,
            'reason' => $reason,
            'next' => $next,
            'link' => $link,
            'due' => $due,
            'owner_id' => $ownerId,
            'owner' => $ownerName,
            'priority' => max($base, $byDue),
            'mine' => $ownerId !== null && $ownerId === $this->me,
        ];
    }

    private function plus(int $days): string
    {
        return date('Y-m-d', strtotime($this->today . " +{$days} days"));
    }

    private static function sentence(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }

    private static function shortRight(string $type): string
    {
        return (string) preg_replace('/\s*\(.*$/u', '', label(Labels::RIGHT_TYPE, $type));
    }
}
