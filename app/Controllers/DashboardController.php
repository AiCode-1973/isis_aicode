<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Auth\Auth;
use Isis\Auth\Permission as P;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Repositories\ActionRepository;
use Isis\Repositories\ActivityRepository;
use Isis\Repositories\AuditRepository;
use Isis\Repositories\DepartmentRepository;
use Isis\Repositories\InvitationRepository;
use Isis\Repositories\RiskRepository;
use Isis\Repositories\SegmentRepository;
use Isis\Repositories\TenantRepository;
use Isis\Repositories\UserRepository;
use Isis\Services\AccessScope;
use Isis\Services\MaturityService;
use Isis\Services\OnboardingService;

/**
 * Painel de conformidade: pendências, riscos, indicadores, responsáveis e prazos.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = (new TenantRepository())->current();
        $onlyOwn = AccessScope::onlyOwnActions();
        $actions = new ActionRepository();

        $stats = [
            'users' => (new UserRepository())->countActive(),
            'invites' => (new InvitationRepository())->countPending(),
            'departments' => (new DepartmentRepository())->total(),
        ];
        $inventory = Auth::can(P::INVENTORY_VIEW) ? (new ActivityRepository())->stats() : null;
        $risks = Auth::can(P::RISKS_VIEW) ? (new RiskRepository())->stats() : null;
        $actionStats = Auth::can(P::ACTIONS_VIEW) ? $actions->stats($onlyOwn ? Auth::id() : null) : null;
        $requestStats = Auth::can(P::REQUESTS_VIEW) ? (new \Isis\Repositories\RequestRepository())->stats() : null;
        $incidentStats = Auth::can(P::INCIDENTS_VIEW) ? (new \Isis\Repositories\IncidentRepository())->stats() : null;
        $vendorStats = Auth::can(P::VENDORS_VIEW) ? (new \Isis\Repositories\VendorRepository())->stats() : null;
        $policyRepo = new \Isis\Repositories\PolicyRepository();
        $policyStats = Auth::can(P::POLICIES_EDIT) || Auth::can(P::POLICIES_APPROVE) ? $policyRepo->stats() : null;
        $trainingRepo = new \Isis\Repositories\TrainingRepository();
        $trainingStats = Auth::can(P::TRAININGS_MANAGE) ? $trainingRepo->stats() : null;
        // Pendências pessoais (todos os perfis)
        $myAcks = Auth::can(P::POLICIES_VIEW) ? $policyRepo->pendingForUser((int) Auth::id()) : [];
        $myTrainings = Auth::can(P::TRAININGS_VIEW)
            ? array_values(array_filter($trainingRepo->forUser((int) Auth::id()), static fn (array $t): bool => $t['enrollment_status'] !== 'concluido'))
            : [];

        // Jornada: reflete só o que já existe no sistema.
        $journey = [
            ['label' => 'Concluir a configuração inicial', 'hint' => 'Empresa, áreas, dados e responsáveis.', 'done' => $tenant['onboarding_completed_at'] !== null, 'link' => null],
            ['label' => 'Nomear o encarregado (DPO)', 'hint' => $tenant['dpo_name'] ? 'Encarregado: ' . $tenant['dpo_name'] : 'Defina quem responde pela proteção de dados.', 'done' => !empty($tenant['dpo_name']), 'link' => '/configuracoes'],
            ['label' => 'Convidar a equipe', 'hint' => 'Encarregado, gestores, colaboradores e auditor.', 'done' => $stats['users'] > 1 || $stats['invites'] > 0, 'link' => '/usuarios'],
            ['label' => 'Mapear as operações de tratamento', 'hint' => $inventory ? plural((int) $inventory['total'], 'operação', 'operações') . ' no inventário.' : 'Inventário de dados pessoais.', 'done' => ($inventory['active'] ?? 0) >= 3, 'link' => '/inventario'],
            ['label' => 'Definir as hipóteses legais', 'hint' => $inventory && $inventory['no_basis'] > 0 ? plural((int) $inventory['no_basis'], 'operação', 'operações') . ' sem hipótese legal.' : 'Toda operação com sua base legal.', 'done' => $inventory !== null && $inventory['total'] > 0 && $inventory['no_basis'] === 0, 'link' => '/inventario'],
            ['label' => 'Avaliar os riscos', 'hint' => $risks ? plural((int) $risks['total'], 'risco avaliado', 'riscos avaliados') . '.' : 'Probabilidade e impacto.', 'done' => ($risks['total'] ?? 0) > 0, 'link' => '/riscos'],
            ['label' => 'Avaliar os fornecedores', 'hint' => $vendorStats ? plural((int) $vendorStats['active'], 'fornecedor', 'fornecedores') . '; ' . $vendorStats['unassessed'] . ' sem avaliação.' : 'Terceiros que tratam dados.', 'done' => ($vendorStats['active'] ?? 0) > 0 && ($vendorStats['unassessed'] ?? 1) === 0, 'link' => '/fornecedores'],
            ['label' => 'Aprovar políticas e treinar a equipe', 'hint' => $policyStats || $trainingStats ? plural((int) ($policyStats['published'] ?? 0), 'política vigente', 'políticas vigentes') . '; ' . plural((int) ($trainingStats['completed'] ?? 0), 'treinamento concluído', 'treinamentos concluídos') . '.' : 'Políticas vigentes e equipe capacitada.', 'done' => ($policyStats['published'] ?? 0) > 0 && ($trainingStats['completed'] ?? 0) > 0, 'link' => '/politicas'],
            ['label' => 'Executar os planos de ação', 'hint' => $actionStats ? $actionStats['done'] . ' de ' . plural((int) $actionStats['total'], 'ação concluída', 'ações concluídas') . '.' : 'Medidas com responsável e prazo.', 'done' => ($actionStats['total'] ?? 0) > 0 && $actionStats['overdue'] === 0 && $actionStats['done'] >= ($actionStats['total'] - $actionStats['cancelled']) / 2, 'link' => '/planos'],
        ];
        $done = count(array_filter($journey, static fn (array $s): bool => $s['done']));

        // Pendências que pedem atenção agora
        $alerts = [];
        if ($incidentStats && $incidentStats['anpd_overdue'] > 0) {
            $alerts[] = ['danger', plural((int) $incidentStats['anpd_overdue'], 'incidente', 'incidentes') . ' com prazo de comunicação à ANPD vencido.', '/incidentes?abertos=1'];
        } elseif ($incidentStats && $incidentStats['anpd_pending'] > 0) {
            $alerts[] = ['danger', plural((int) $incidentStats['anpd_pending'], 'incidente', 'incidentes') . ' com risco relevante aguardando comunicação à ANPD.', '/incidentes?abertos=1'];
        }
        if ($requestStats && $requestStats['overdue'] > 0) {
            $alerts[] = ['danger', plural((int) $requestStats['overdue'], 'solicitação', 'solicitações') . ' de titular fora do prazo.', '/titulares?vencidas=1'];
        }
        if ($incidentStats && $incidentStats['untriaged'] > 0) {
            $alerts[] = ['warn', plural((int) $incidentStats['untriaged'], 'incidente comunicado', 'incidentes comunicados') . ' aguardando análise.', '/incidentes?status=comunicado'];
        }
        if ($requestStats && $requestStats['due_soon'] > 0) {
            $alerts[] = ['warn', plural((int) $requestStats['due_soon'], 'solicitação', 'solicitações') . ' de titular vencendo nos próximos 3 dias.', '/titulares?abertas=1'];
        }
        if ($actionStats && $actionStats['overdue'] > 0) {
            $alerts[] = ['danger', plural((int) $actionStats['overdue'], 'ação', 'ações') . ' com prazo vencido.', '/planos?vencidas=1'];
        }
        if ($risks && $risks['critical'] + $risks['high'] > 0) {
            $alerts[] = ['danger', plural((int) ($risks['critical'] + $risks['high']), 'risco alto ou crítico', 'riscos altos ou críticos') . ' em aberto.', '/riscos?abertos=1&nivel=' . ($risks['critical'] > 0 ? 'critico' : 'alto')];
        }
        if ($inventory && $inventory['no_basis'] > 0) {
            $alerts[] = ['warn', plural((int) $inventory['no_basis'], 'operação', 'operações') . ' sem hipótese legal definida.', '/inventario'];
        }
        if ($inventory && $inventory['review_overdue'] > 0) {
            $alerts[] = ['warn', plural((int) $inventory['review_overdue'], 'operação', 'operações') . ' com revisão vencida.', '/inventario'];
        }
        if ($risks && $risks['unowned'] > 0) {
            $alerts[] = ['warn', plural((int) $risks['unowned'], 'risco aberto', 'riscos abertos') . ' sem responsável.', '/riscos?abertos=1'];
        }
        if ($actionStats && !$onlyOwn && $actionStats['unowned'] > 0) {
            $alerts[] = ['warn', plural((int) $actionStats['unowned'], 'ação', 'ações') . ' sem responsável.', '/planos?abertas=1'];
        }
        if ($policyStats && $policyStats['expired'] > 0) {
            $alerts[] = ['danger', plural((int) $policyStats['expired'], 'política', 'políticas') . ' com validade vencida.', '/politicas'];
        }
        if ($policyStats && $policyStats['awaiting'] > 0 && Auth::can(P::POLICIES_APPROVE)) {
            $alerts[] = ['warn', plural((int) $policyStats['awaiting'], 'política', 'políticas') . ' aguardando sua aprovação.', '/politicas'];
        }
        if ($vendorStats && $vendorStats['high'] > 0) {
            $alerts[] = ['warn', plural((int) $vendorStats['high'], 'fornecedor', 'fornecedores') . ' de risco alto.', '/fornecedores?risco=alto'];
        }
        if ($vendorStats && $vendorStats['no_dpa'] > 0) {
            $alerts[] = ['warn', ((int) $vendorStats['no_dpa'] === 1 ? '1 fornecedor que trata' : $vendorStats['no_dpa'] . ' fornecedores que tratam') . ' dados sem contrato com cláusulas de proteção.', '/fornecedores'];
        }
        if ($vendorStats && $vendorStats['contracts_expiring'] > 0) {
            $alerts[] = ['warn', plural((int) $vendorStats['contracts_expiring'], 'contrato', 'contratos') . ' de fornecedor vencendo em 30 dias.', '/fornecedores'];
        }
        if ($trainingStats && $trainingStats['overdue'] > 0) {
            $alerts[] = ['warn', plural((int) $trainingStats['overdue'], 'participação', 'participações') . ' em treinamento com prazo vencido.', '/treinamentos'];
        }

        // Índice de maturidade (visão da gestão; quem só vê as próprias tarefas não recebe)
        $maturity = $onlyOwn ? null : MaturityService::compute([
            'inventory' => $inventory, 'risks' => $risks, 'actions' => $actionStats, 'requests' => $requestStats,
            'incidents' => $incidentStats, 'vendors' => $vendorStats, 'policies' => $policyStats, 'trainings' => $trainingStats,
        ]);

        return $this->view('dashboard/index', [
            'maturity' => $maturity !== null && $maturity['score'] !== null ? $maturity : null,
            'requestTrend' => $requestStats !== null ? MaturityService::requestsByMonth() : null,
            'actionTrend' => $actionStats !== null && !$onlyOwn ? MaturityService::actionsDoneByMonth() : null,
            'title' => 'Painel de conformidade',
            'tenant' => $tenant,
            'stats' => $stats,
            'inventory' => $inventory,
            'risks' => $risks,
            'actionStats' => $actionStats,
            'requestStats' => $requestStats,
            'vendorStats' => $vendorStats,
            'trainingStats' => $trainingStats,
            'myAcks' => $myAcks,
            'myTrainings' => $myTrainings,
            'incidentStats' => $incidentStats,
            'heatmap' => $risks !== null ? (new RiskRepository())->heatmap() : null,
            'upcoming' => $actionStats !== null ? $actions->upcoming(30, 8, $onlyOwn ? Auth::id() : null) : [],
            'byOwner' => $actionStats !== null && !$onlyOwn ? $actions->byOwner() : [],
            'issues' => Auth::can(P::INVENTORY_VIEW) ? (new ActivityRepository())->withIssues(5) : [],
            'alerts' => $alerts,
            'journey' => $journey,
            'planTop' => Auth::can(P::SETTINGS_MANAGE) && isset(OnboardingService::answers($tenant)['responsaveis'])
                ? array_slice(OnboardingService::plan(OnboardingService::answers($tenant)), 0, 3) : [],
            'progress' => (int) round($done / count($journey) * 100),
            'guidance' => SegmentRepository::template($tenant['segment_code'])['guidance'] ?? [],
            'recent' => Auth::can(P::AUDIT_VIEW) ? (new AuditRepository())->recent(6) : [],
            'role' => Auth::role(),
            'onlyOwn' => $onlyOwn,
        ]);
    }
}
