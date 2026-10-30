<?php

declare(strict_types=1);

use Isis\Auth\Permission as P;
use Isis\Controllers\ActionController;
use Isis\Controllers\AuditController;
use Isis\Controllers\EvidenceController;
use Isis\Controllers\InventoryController;
use Isis\Controllers\RiskController;
use Isis\Controllers\AuthController;
use Isis\Controllers\DashboardController;
use Isis\Controllers\DpiaController;
use Isis\Controllers\HomeController;
use Isis\Controllers\IncidentController;
use Isis\Controllers\PolicyController;
use Isis\Controllers\PublicRequestController;
use Isis\Controllers\TrainingController;
use Isis\Controllers\VendorController;
use Isis\Controllers\ReportController;
use Isis\Controllers\RequestController;
use Isis\Controllers\InstallController;
use Isis\Controllers\InvitationController;
use Isis\Controllers\MfaController;
use Isis\Controllers\OnboardingController;
use Isis\Controllers\PasswordController;
use Isis\Controllers\ProfileController;
use Isis\Controllers\RegisterController;
use Isis\Controllers\SettingsController;
use Isis\Controllers\SubscriptionController;
use Isis\Controllers\PlatformController;
use Isis\Controllers\UserController;
use Isis\Core\Router;

/*
 * Rotas. Todas no mesmo domínio; a empresa vem sempre da sessão autenticada.
 * Middleware: guest, auth, onboarded, billing (somente leitura se a assinatura venceu), perm:<permissão>, platform. Todo POST exige token CSRF (no Router).
 */
return static function (Router $r): void {
    $app = static fn (string $permission): array => ['auth', 'onboarded', 'billing', 'perm:' . $permission];

    // Público
    $r->get('/', [HomeController::class, 'index']);
    $r->get('/termos', [HomeController::class, 'terms']);
    $r->get('/manual', [HomeController::class, 'manual']);
    $r->get('/precos', [HomeController::class, 'pricing']);

    // Canal público do titular (mesmo domínio; a empresa vem do endereço)
    $r->get('/titular/{slug}', [PublicRequestController::class, 'form']);
    $r->post('/titular/{slug}', [PublicRequestController::class, 'store']);
    $r->get('/titular/{slug}/enviado', [PublicRequestController::class, 'sent']);
    $r->get('/titular/{slug}/acompanhar', [PublicRequestController::class, 'track']);
    $r->get('/certificado/{code}', [TrainingController::class, 'verify']);
    $r->post('/titular/{slug}/acompanhar', [PublicRequestController::class, 'track']);
    $r->get('/instalar', [InstallController::class, 'show']);
    $r->post('/instalar', [InstallController::class, 'run']);

    // Cadastro, acesso e recuperação
    $r->get('/cadastro', [RegisterController::class, 'show'], ['guest']);
    $r->post('/cadastro', [RegisterController::class, 'store'], ['guest']);
    $r->get('/cadastro/confirme-email', [RegisterController::class, 'pending'], ['guest']);
    $r->post('/cadastro/reenviar', [RegisterController::class, 'resend'], ['guest']);
    $r->get('/verificar-email/{token}', [RegisterController::class, 'verify']);
    $r->get('/entrar', [AuthController::class, 'showLogin'], ['guest']);
    $r->post('/entrar', [AuthController::class, 'login'], ['guest']);
    $r->get('/entrar/verificacao', [MfaController::class, 'challenge'], ['guest']);
    $r->post('/entrar/verificacao', [MfaController::class, 'verify'], ['guest']);
    $r->post('/sair', [AuthController::class, 'logout'], ['auth']);
    $r->get('/esqueci-senha', [PasswordController::class, 'showForgot'], ['guest']);
    $r->post('/esqueci-senha', [PasswordController::class, 'sendLink'], ['guest']);
    $r->get('/redefinir-senha/{token}', [PasswordController::class, 'showReset'], ['guest']);
    $r->post('/redefinir-senha/{token}', [PasswordController::class, 'reset'], ['guest']);
    $r->get('/convite/{token}', [InvitationController::class, 'show']);
    $r->post('/convite/{token}', [InvitationController::class, 'accept']);

    // Configuração inicial
    $r->get('/configuracao-inicial', [OnboardingController::class, 'show'], ['auth', 'perm:' . P::SETTINGS_MANAGE]);
    $r->post('/configuracao-inicial', [OnboardingController::class, 'store'], ['auth', 'perm:' . P::SETTINGS_MANAGE]);

    // Área da empresa
    $r->get('/painel', [DashboardController::class, 'index'], $app(P::DASHBOARD_VIEW));

    // Inventário de operações de tratamento
    $r->get('/inventario', [InventoryController::class, 'index'], $app(P::INVENTORY_VIEW));
    $r->get('/inventario/exportar', [InventoryController::class, 'export'], $app(P::INVENTORY_VIEW));
    $r->get('/inventario/nova', [InventoryController::class, 'create'], $app(P::INVENTORY_EDIT));
    $r->post('/inventario', [InventoryController::class, 'store'], $app(P::INVENTORY_EDIT));
    $r->get('/inventario/catalogos', [InventoryController::class, 'catalogs'], $app(P::INVENTORY_VIEW));
    $r->post('/inventario/catalogos/importar', [InventoryController::class, 'catalogImport'], $app(P::INVENTORY_EDIT));
    $r->post('/inventario/catalogos/{type}', [InventoryController::class, 'catalogStore'], $app(P::INVENTORY_EDIT));
    $r->post('/inventario/catalogos/{type}/{id}', [InventoryController::class, 'catalogUpdate'], $app(P::INVENTORY_EDIT));
    $r->post('/inventario/catalogos/{type}/{id}/excluir', [InventoryController::class, 'catalogDelete'], $app(P::INVENTORY_EDIT));
    $r->get('/inventario/{id}', [InventoryController::class, 'show'], $app(P::INVENTORY_VIEW));
    $r->get('/inventario/{id}/editar', [InventoryController::class, 'edit'], $app(P::INVENTORY_EDIT));
    $r->post('/inventario/{id}', [InventoryController::class, 'update'], $app(P::INVENTORY_EDIT));
    $r->post('/inventario/{id}/excluir', [InventoryController::class, 'delete'], $app(P::INVENTORY_EDIT));

    // Riscos
    $r->get('/riscos', [RiskController::class, 'index'], $app(P::RISKS_VIEW));
    $r->get('/riscos/novo', [RiskController::class, 'create'], $app(P::RISKS_EDIT));
    $r->post('/riscos', [RiskController::class, 'store'], $app(P::RISKS_EDIT));
    $r->get('/riscos/{id}', [RiskController::class, 'show'], $app(P::RISKS_VIEW));
    $r->get('/riscos/{id}/editar', [RiskController::class, 'edit'], $app(P::RISKS_EDIT));
    $r->post('/riscos/{id}', [RiskController::class, 'update'], $app(P::RISKS_EDIT));
    $r->post('/riscos/{id}/excluir', [RiskController::class, 'delete'], $app(P::RISKS_EDIT));

    // Relatório de Impacto à Proteção de Dados Pessoais (RIPD)
    $r->get('/ripd', [DpiaController::class, 'index'], $app(P::DPIA_VIEW));
    $r->get('/ripd/novo', [DpiaController::class, 'create'], $app(P::DPIA_EDIT));
    $r->post('/ripd', [DpiaController::class, 'store'], $app(P::DPIA_EDIT));
    $r->get('/ripd/{id}', [DpiaController::class, 'show'], $app(P::DPIA_VIEW));
    $r->get('/ripd/{id}/editar', [DpiaController::class, 'edit'], $app(P::DPIA_EDIT));
    $r->get('/ripd/{id}/documento', [DpiaController::class, 'document'], $app(P::DPIA_VIEW));
    $r->get('/ripd/{id}/versoes/{item}', [DpiaController::class, 'version'], $app(P::DPIA_VIEW));
    $r->post('/ripd/{id}', [DpiaController::class, 'update'], $app(P::DPIA_EDIT));
    $r->post('/ripd/{id}/enviar', [DpiaController::class, 'submit'], $app(P::DPIA_EDIT));
    $r->post('/ripd/{id}/aprovar', [DpiaController::class, 'approve'], $app(P::DPIA_APPROVE));
    $r->post('/ripd/{id}/devolver', [DpiaController::class, 'reject'], $app(P::DPIA_APPROVE));
    $r->post('/ripd/{id}/revisar', [DpiaController::class, 'revise'], $app(P::DPIA_EDIT));
    $r->post('/ripd/{id}/excluir', [DpiaController::class, 'delete'], $app(P::DPIA_EDIT));

    // Planos de ação
    $r->get('/planos', [ActionController::class, 'index'], $app(P::ACTIONS_VIEW));
    $r->get('/planos/nova', [ActionController::class, 'create'], $app(P::ACTIONS_EDIT));
    $r->post('/planos', [ActionController::class, 'store'], $app(P::ACTIONS_EDIT));
    $r->get('/planos/{id}', [ActionController::class, 'show'], $app(P::ACTIONS_VIEW));
    $r->get('/planos/{id}/editar', [ActionController::class, 'edit'], $app(P::ACTIONS_EDIT));
    $r->post('/planos/{id}', [ActionController::class, 'update'], $app(P::ACTIONS_EDIT));
    $r->post('/planos/{id}/andamento', [ActionController::class, 'progress'], $app(P::ACTIONS_VIEW));
    $r->post('/planos/{id}/excluir', [ActionController::class, 'delete'], $app(P::ACTIONS_EDIT));

    // Solicitações de titulares
    $r->get('/titulares', [RequestController::class, 'index'], $app(P::REQUESTS_VIEW));
    $r->get('/titulares/nova', [RequestController::class, 'create'], $app(P::REQUESTS_MANAGE));
    $r->post('/titulares', [RequestController::class, 'store'], $app(P::REQUESTS_MANAGE));
    $r->get('/titulares/{id}', [RequestController::class, 'show'], $app(P::REQUESTS_VIEW));
    $r->post('/titulares/{id}/atendimento', [RequestController::class, 'handling'], $app(P::REQUESTS_MANAGE));
    $r->post('/titulares/{id}/andamento', [RequestController::class, 'progress'], $app(P::REQUESTS_MANAGE));
    $r->post('/titulares/{id}/resposta', [RequestController::class, 'respond'], $app(P::REQUESTS_MANAGE));

    // Incidentes de segurança (colaboradores comunicam; encarregado e administrador conduzem)
    $r->get('/incidentes', [IncidentController::class, 'index'], $app(P::INCIDENTS_VIEW . '|' . P::INCIDENTS_REPORT));
    $r->get('/incidentes/novo', [IncidentController::class, 'create'], $app(P::INCIDENTS_REPORT . '|' . P::INCIDENTS_MANAGE));
    $r->post('/incidentes', [IncidentController::class, 'store'], $app(P::INCIDENTS_REPORT . '|' . P::INCIDENTS_MANAGE));
    $r->get('/incidentes/{id}', [IncidentController::class, 'show'], $app(P::INCIDENTS_VIEW . '|' . P::INCIDENTS_REPORT));
    $r->post('/incidentes/{id}/classificacao', [IncidentController::class, 'classify'], $app(P::INCIDENTS_MANAGE));
    $r->post('/incidentes/{id}/tratamento', [IncidentController::class, 'treatment'], $app(P::INCIDENTS_MANAGE));
    $r->post('/incidentes/{id}/andamento', [IncidentController::class, 'progress'], $app(P::INCIDENTS_MANAGE));
    $r->post('/incidentes/{id}/comunicacao', [IncidentController::class, 'communication'], $app(P::INCIDENTS_MANAGE));

    // Fornecedores
    $r->get('/fornecedores', [VendorController::class, 'index'], $app(P::VENDORS_VIEW));
    $r->get('/fornecedores/novo', [VendorController::class, 'create'], $app(P::VENDORS_EDIT));
    $r->post('/fornecedores', [VendorController::class, 'store'], $app(P::VENDORS_EDIT));
    $r->get('/fornecedores/{id}', [VendorController::class, 'show'], $app(P::VENDORS_VIEW));
    $r->get('/fornecedores/{id}/editar', [VendorController::class, 'edit'], $app(P::VENDORS_EDIT));
    $r->post('/fornecedores/{id}', [VendorController::class, 'update'], $app(P::VENDORS_EDIT));
    $r->post('/fornecedores/{id}/avaliacao', [VendorController::class, 'assess'], $app(P::VENDORS_EDIT));
    $r->post('/fornecedores/{id}/contratos', [VendorController::class, 'addContract'], $app(P::VENDORS_EDIT));
    $r->post('/fornecedores/{id}/contratos/{item}/excluir', [VendorController::class, 'removeContract'], $app(P::VENDORS_EDIT));

    // Políticas e documentos
    $r->get('/politicas', [PolicyController::class, 'index'], $app(P::POLICIES_VIEW));
    $r->get('/politicas/nova', [PolicyController::class, 'create'], $app(P::POLICIES_EDIT));
    $r->post('/politicas', [PolicyController::class, 'store'], $app(P::POLICIES_EDIT));
    $r->post('/politicas/modelos', [PolicyController::class, 'importTemplates'], $app(P::POLICIES_EDIT));
    $r->get('/politicas/{id}', [PolicyController::class, 'show'], $app(P::POLICIES_VIEW));
    $r->get('/politicas/{id}/editar', [PolicyController::class, 'edit'], $app(P::POLICIES_EDIT));
    $r->post('/politicas/{id}', [PolicyController::class, 'update'], $app(P::POLICIES_EDIT));
    $r->post('/politicas/{id}/enviar', [PolicyController::class, 'submit'], $app(P::POLICIES_EDIT));
    $r->post('/politicas/{id}/aprovar', [PolicyController::class, 'approve'], $app(P::POLICIES_APPROVE));
    $r->post('/politicas/{id}/devolver', [PolicyController::class, 'reject'], $app(P::POLICIES_APPROVE));
    $r->post('/politicas/{id}/ciencia', [PolicyController::class, 'acknowledge'], $app(P::POLICIES_VIEW));
    $r->post('/politicas/{id}/arquivar', [PolicyController::class, 'archive'], $app(P::POLICIES_EDIT));

    // Treinamentos
    $r->get('/treinamentos', [TrainingController::class, 'index'], $app(P::TRAININGS_VIEW));
    $r->get('/treinamentos/novo', [TrainingController::class, 'create'], $app(P::TRAININGS_MANAGE));
    $r->post('/treinamentos', [TrainingController::class, 'store'], $app(P::TRAININGS_MANAGE));
    $r->post('/treinamentos/modelo', [TrainingController::class, 'importTemplate'], $app(P::TRAININGS_MANAGE));
    $r->get('/treinamentos/certificado/{code}', [TrainingController::class, 'certificate'], $app(P::TRAININGS_VIEW));
    $r->get('/treinamentos/{id}', [TrainingController::class, 'show'], $app(P::TRAININGS_VIEW));
    $r->get('/treinamentos/{id}/editar', [TrainingController::class, 'edit'], $app(P::TRAININGS_MANAGE));
    $r->post('/treinamentos/{id}', [TrainingController::class, 'update'], $app(P::TRAININGS_MANAGE));
    $r->post('/treinamentos/{id}/situacao', [TrainingController::class, 'status'], $app(P::TRAININGS_MANAGE));
    $r->post('/treinamentos/{id}/designar', [TrainingController::class, 'assign'], $app(P::TRAININGS_MANAGE));
    $r->get('/treinamentos/{id}/avaliacao', [TrainingController::class, 'quiz'], $app(P::TRAININGS_VIEW));
    $r->post('/treinamentos/{id}/avaliacao', [TrainingController::class, 'submit'], $app(P::TRAININGS_VIEW));

    // Relatórios
    $r->get('/relatorios', [ReportController::class, 'index'], $app(P::REPORTS_VIEW));
    $r->get('/relatorios/exportar/{type}', [ReportController::class, 'export'], $app(P::REPORTS_VIEW));

    // Evidências (permissão conferida sobre o registro de origem)
    $r->post('/evidencias', [EvidenceController::class, 'upload'], $app(P::DASHBOARD_VIEW));
    $r->get('/evidencias/{id}', [EvidenceController::class, 'download'], $app(P::DASHBOARD_VIEW));
    $r->post('/evidencias/{id}/excluir', [EvidenceController::class, 'delete'], $app(P::DASHBOARD_VIEW));

    $r->get('/usuarios', [UserController::class, 'index'], $app(P::USERS_VIEW));
    $r->post('/usuarios/convites', [UserController::class, 'invite'], $app(P::USERS_MANAGE));
    $r->post('/usuarios/convites/{id}/reenviar', [UserController::class, 'resendInvite'], $app(P::USERS_MANAGE));
    $r->post('/usuarios/convites/{id}/cancelar', [UserController::class, 'revokeInvite'], $app(P::USERS_MANAGE));
    $r->get('/usuarios/{id}/editar', [UserController::class, 'edit'], $app(P::USERS_MANAGE));
    $r->post('/usuarios/{id}', [UserController::class, 'update'], $app(P::USERS_MANAGE));
    $r->post('/usuarios/{id}/dois-fatores/redefinir', [UserController::class, 'resetMfa'], $app(P::USERS_MANAGE));

    $r->get('/configuracoes', [SettingsController::class, 'index'], $app(P::SETTINGS_MANAGE));
    $r->post('/configuracoes/empresa', [SettingsController::class, 'updateCompany'], $app(P::SETTINGS_MANAGE));
    $r->post('/configuracoes/setores', [SettingsController::class, 'addDepartment'], $app(P::SETTINGS_MANAGE));
    $r->post('/configuracoes/setores/{id}', [SettingsController::class, 'updateDepartment'], $app(P::SETTINGS_MANAGE));
    $r->post('/configuracoes/setores/{id}/excluir', [SettingsController::class, 'deleteDepartment'], $app(P::SETTINGS_MANAGE));

    $r->get('/auditoria', [AuditController::class, 'index'], $app(P::AUDIT_VIEW));
    $r->get('/auditoria/exportar', [AuditController::class, 'export'], $app(P::AUDIT_VIEW));
    $r->post('/auditoria/verificar', [AuditController::class, 'verify'], $app(P::AUDIT_VIEW));

    // Plano e assinatura da empresa (fica fora do filtro "billing" para a empresa poder regularizar)
    $r->get('/plano', [SubscriptionController::class, 'show'], ['auth', 'onboarded', 'perm:' . P::SETTINGS_MANAGE]);
    $r->post('/plano/solicitar', [SubscriptionController::class, 'request'], ['auth', 'onboarded', 'perm:' . P::SETTINGS_MANAGE]);

    // Painel da plataforma (equipe AiCode), com login próprio
    $r->get('/plataforma/entrar', [PlatformController::class, 'showLogin']);
    $r->post('/plataforma/entrar', [PlatformController::class, 'login']);
    $r->post('/plataforma/sair', [PlatformController::class, 'logout'], ['platform']);
    $r->get('/plataforma', [PlatformController::class, 'index'], ['platform']);
    $r->get('/plataforma/empresas/{id}', [PlatformController::class, 'tenant'], ['platform']);
    $r->post('/plataforma/empresas/{id}/assinatura', [PlatformController::class, 'updateSubscription'], ['platform']);
    $r->post('/plataforma/empresas/{id}/faturas', [PlatformController::class, 'createInvoice'], ['platform']);
    $r->post('/plataforma/empresas/{id}/faturas/{item}/pagamento', [PlatformController::class, 'payInvoice'], ['platform']);
    $r->post('/plataforma/empresas/{id}/faturas/{item}/cancelar', [PlatformController::class, 'cancelInvoice'], ['platform']);
    $r->get('/plataforma/planos', [PlatformController::class, 'plans'], ['platform']);
    $r->post('/plataforma/planos/{slug}', [PlatformController::class, 'updatePlan'], ['platform']);
    $r->get('/plataforma/configuracoes', [PlatformController::class, 'settings'], ['platform']);
    $r->post('/plataforma/configuracoes', [PlatformController::class, 'saveSettings'], ['platform']);
    $r->post('/plataforma/senha', [PlatformController::class, 'password'], ['platform']);

    $r->get('/perfil', [ProfileController::class, 'show'], ['auth']);
    $r->post('/perfil', [ProfileController::class, 'update'], ['auth']);
    $r->post('/perfil/senha', [ProfileController::class, 'password'], ['auth']);
    $r->post('/perfil/sessoes/encerrar', [ProfileController::class, 'logoutOthers'], ['auth']);
    $r->get('/perfil/dois-fatores', [MfaController::class, 'show'], ['auth']);
    $r->post('/perfil/dois-fatores/ativar', [MfaController::class, 'enable'], ['auth']);
    $r->post('/perfil/dois-fatores/desativar', [MfaController::class, 'disable'], ['auth']);
    $r->post('/perfil/dois-fatores/codigos', [MfaController::class, 'regenerate'], ['auth']);
};
