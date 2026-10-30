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
            ['label' => 'Concluir a configuração inicial', 'hint' => 'Segmento, setores e canal de privacidade.', 'done' => $tenant['onboarding_completed_at'] !== null, 'link' => null],
            ['label' => 'Nomear o encarregado (DPO)', 'hint' => $tenant['dpo_name'] ? 'Encarregado: ' . $tenant['dpo_name'] : 'Defina quem responde pela proteção de dados.', 'done' => !empty($tenant['dpo_name']), 'link' => '/configuracoes'],
            ['label' => 'Convidar a equipe', 'hint' => 'Encarregado, gestores, colaboradores e auditor.', 'done' => $stats['users'] > 1 || $stats['invites'] > 0, 'link' => '/usuarios'],
            ['label' => 'Mapear as operações de tratamento', 'hint' => $inventory ? $inventory['total'] . ' operação(ões) no inventário.' : 'Inventário de dados pessoais.', 'done' => ($inventory['active'] ?? 0) >= 3, 'link' => '/inventario'],
            ['label' => 'Definir as hipóteses legais', 'hint' => $inventory && $inventory['no_basis'] > 0 ? $inventory['no_basis'] . ' operação(ões) sem hipótese legal.' : 'Toda operação com sua base legal.', 'done' => $inventory !== null && $inventory['total'] > 0 && $inventory['no_basis'] === 0, 'link' => '/inventario'],
            ['label' => 'Avaliar os riscos', 'hint' => $risks ? $risks['total'] . ' risco(s) avaliado(s).' : 'Probabilidade e impacto.', 'done' => ($risks['total'] ?? 0) > 0, 'link' => '/riscos'],
            ['label' => 'Avaliar os fornecedores', 'hint' => $vendorStats ? $vendorStats['active'] . ' fornecedor(es); ' . $vendorStats['unassessed'] . ' sem avaliação.' : 'Terceiros que tratam dados.', 'done' => ($vendorStats['active'] ?? 0) > 0 && ($vendorStats['unassessed'] ?? 1) === 0, 'link' => '/fornecedores'],
            ['label' => 'Aprovar políticas e treinar a equipe', 'hint' => $policyStats || $trainingStats ? ($policyStats['published'] ?? 0) . ' política(s) vigente(s); ' . ($trainingStats['completed'] ?? 0) . ' treinamento(s) concluído(s).' : 'Políticas vigentes e equipe capacitada.', 'done' => ($policyStats['published'] ?? 0) > 0 && ($trainingStats['completed'] ?? 0) > 0, 'link' => '/politicas'],
            ['label' => 'Executar os planos de ação', 'hint' => $actionStats ? $actionStats['done'] . ' de ' . $actionStats['total'] . ' ação(ões) concluída(s).' : 'Medidas com responsável e prazo.', 'done' => ($actionStats['total'] ?? 0) > 0 && $actionStats['overdue'] === 0 && $actionStats['done'] >= ($actionStats['total'] - $actionStats['cancelled']) / 2, 'link' => '/planos'],
        ];
        $done = count(array_filter($journey, static fn (array $s): bool => $s['done']));

        // Pendências que pedem atenção agora
        $alerts = [];
        if ($incidentStats && $incidentStats['anpd_overdue'] > 0) {
            $alerts[] = ['danger', $incidentStats['anpd_overdue'] . ' incidente(s) com prazo de comunicação à ANPD vencido.', '/incidentes?abertos=1'];
        } elseif ($incidentStats && $incidentStats['anpd_pending'] > 0) {
            $alerts[] = ['danger', $incidentStats['anpd_pending'] . ' incidente(s) com risco relevante aguardando comunicação à ANPD.', '/incidentes?abertos=1'];
        }
        if ($requestStats && $requestStats['overdue'] > 0) {
            $alerts[] = ['danger', $requestStats['overdue'] . ' solicitação(ões) de titular fora do prazo.', '/titulares?vencidas=1'];
        }
        if ($incidentStats && $incidentStats['untriaged'] > 0) {
            $alerts[] = ['warn', $incidentStats['untriaged'] . ' incidente(s) comunicado(s) aguardando análise.', '/incidentes?status=comunicado'];
        }
        if ($requestStats && $requestStats['due_soon'] > 0) {
            $alerts[] = ['warn', $requestStats['due_soon'] . ' solicitação(ões) de titular vencendo nos próximos 3 dias.', '/titulares?abertas=1'];
        }
        if ($actionStats && $actionStats['overdue'] > 0) {
            $alerts[] = ['danger', $actionStats['overdue'] . ' ação(ões) com prazo vencido.', '/planos?vencidas=1'];
        }
        if ($risks && $risks['critical'] + $risks['high'] > 0) {
            $alerts[] = ['danger', ($risks['critical'] + $risks['high']) . ' risco(s) alto(s) ou crítico(s) em aberto.', '/riscos?abertos=1&nivel=' . ($risks['critical'] > 0 ? 'critico' : 'alto')];
        }
        if ($inventory && $inventory['no_basis'] > 0) {
            $alerts[] = ['warn', $inventory['no_basis'] . ' operação(ões) sem hipótese legal definida.', '/inventario'];
        }
        if ($inventory && $inventory['review_overdue'] > 0) {
            $alerts[] = ['warn', $inventory['review_overdue'] . ' operação(ões) com revisão vencida.', '/inventario'];
        }
        if ($risks && $risks['unowned'] > 0) {
            $alerts[] = ['warn', $risks['unowned'] . ' risco(s) aberto(s) sem responsável.', '/riscos?abertos=1'];
        }
        if ($actionStats && !$onlyOwn && $actionStats['unowned'] > 0) {
            $alerts[] = ['warn', $actionStats['unowned'] . ' ação(ões) sem responsável.', '/planos?abertas=1'];
        }
        if ($policyStats && $policyStats['expired'] > 0) {
            $alerts[] = ['danger', $policyStats['expired'] . ' política(s) com validade vencida.', '/politicas'];
        }
        if ($policyStats && $policyStats['awaiting'] > 0 && Auth::can(P::POLICIES_APPROVE)) {
            $alerts[] = ['warn', $policyStats['awaiting'] . ' política(s) aguardando sua aprovação.', '/politicas'];
        }
        if ($vendorStats && $vendorStats['high'] > 0) {
            $alerts[] = ['warn', $vendorStats['high'] . ' fornecedor(es) de risco alto.', '/fornecedores?risco=alto'];
        }
        if ($vendorStats && $vendorStats['no_dpa'] > 0) {
            $alerts[] = ['warn', $vendorStats['no_dpa'] . ' fornecedor(es) que tratam dados sem contrato com cláusulas de proteção.', '/fornecedores'];
        }
        if ($vendorStats && $vendorStats['contracts_expiring'] > 0) {
            $alerts[] = ['warn', $vendorStats['contracts_expiring'] . ' contrato(s) de fornecedor vencendo em 30 dias.', '/fornecedores'];
        }
        if ($trainingStats && $trainingStats['overdue'] > 0) {
            $alerts[] = ['warn', $trainingStats['overdue'] . ' participação(ões) em treinamento com prazo vencido.', '/treinamentos'];
        }

        return $this->view('dashboard/index', [
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
            'progress' => (int) round($done / count($journey) * 100),
            'guidance' => SegmentRepository::template($tenant['segment_code'])['guidance'] ?? [],
            'recent' => Auth::can(P::AUDIT_VIEW) ? (new AuditRepository())->recent(6) : [],
            'role' => Auth::role(),
            'onlyOwn' => $onlyOwn,
        ]);
    }
}
