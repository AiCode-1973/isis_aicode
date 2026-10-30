<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Auth\Auth;
use Isis\Auth\Permission;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Repositories\AuditRepository;
use Isis\Repositories\DepartmentRepository;
use Isis\Repositories\InvitationRepository;
use Isis\Repositories\SegmentRepository;
use Isis\Repositories\TenantRepository;
use Isis\Repositories\UserRepository;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = (new TenantRepository())->current();
        $users = new UserRepository();
        $invites = new InvitationRepository();
        $departments = new DepartmentRepository();
        $audit = new AuditRepository();

        $stats = [
            'users' => $users->countActive(),
            'invites' => $invites->countPending(),
            'departments' => $departments->total(),
            'events' => $audit->countSince(date('Y-m-d H:i:s', strtotime('-7 days'))),
        ];

        // Jornada de implantação: reflete só o que já existe no sistema.
        $journey = [
            ['label' => 'Cadastrar a empresa', 'hint' => 'Conta institucional criada.', 'done' => true],
            ['label' => 'Concluir a configuração inicial', 'hint' => 'Segmento, setores e canal de privacidade.', 'done' => $tenant['onboarding_completed_at'] !== null],
            ['label' => 'Nomear o encarregado (DPO)', 'hint' => $tenant['dpo_name'] ? 'Encarregado: ' . $tenant['dpo_name'] : 'Defina quem responde pela proteção de dados.', 'done' => !empty($tenant['dpo_name'])],
            ['label' => 'Convidar a equipe', 'hint' => 'Encarregado, gestores, colaboradores e auditor.', 'done' => $stats['users'] > 1 || $stats['invites'] > 0],
            ['label' => 'Organizar os setores', 'hint' => $stats['departments'] . ' setor(es) cadastrado(s).', 'done' => $stats['departments'] >= 2],
        ];
        $done = count(array_filter($journey, static fn (array $s): bool => $s['done']));

        return $this->view('dashboard/index', [
            'title' => 'Painel de conformidade',
            'tenant' => $tenant,
            'stats' => $stats,
            'journey' => $journey,
            'progress' => (int) round($done / count($journey) * 100),
            'guidance' => SegmentRepository::template($tenant['segment_code'])['guidance'] ?? [],
            'recent' => Auth::can(Permission::AUDIT_VIEW) ? $audit->recent(8) : [],
            'role' => Auth::role(),
        ]);
    }
}
