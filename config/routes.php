<?php

declare(strict_types=1);

use Isis\Auth\Permission as P;
use Isis\Controllers\AuditController;
use Isis\Controllers\AuthController;
use Isis\Controllers\DashboardController;
use Isis\Controllers\HomeController;
use Isis\Controllers\InstallController;
use Isis\Controllers\InvitationController;
use Isis\Controllers\OnboardingController;
use Isis\Controllers\PasswordController;
use Isis\Controllers\ProfileController;
use Isis\Controllers\RegisterController;
use Isis\Controllers\SettingsController;
use Isis\Controllers\UserController;
use Isis\Core\Router;

/*
 * Rotas. Todas no mesmo domínio; a empresa vem sempre da sessão autenticada.
 * Middleware: guest, auth, onboarded, perm:<permissão>. Todo POST exige token CSRF (no Router).
 */
return static function (Router $r): void {
    $app = static fn (string $permission): array => ['auth', 'onboarded', 'perm:' . $permission];

    // Público
    $r->get('/', [HomeController::class, 'index']);
    $r->get('/termos', [HomeController::class, 'terms']);
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

    $r->get('/usuarios', [UserController::class, 'index'], $app(P::USERS_VIEW));
    $r->post('/usuarios/convites', [UserController::class, 'invite'], $app(P::USERS_MANAGE));
    $r->post('/usuarios/convites/{id}/reenviar', [UserController::class, 'resendInvite'], $app(P::USERS_MANAGE));
    $r->post('/usuarios/convites/{id}/cancelar', [UserController::class, 'revokeInvite'], $app(P::USERS_MANAGE));
    $r->get('/usuarios/{id}/editar', [UserController::class, 'edit'], $app(P::USERS_MANAGE));
    $r->post('/usuarios/{id}', [UserController::class, 'update'], $app(P::USERS_MANAGE));

    $r->get('/configuracoes', [SettingsController::class, 'index'], $app(P::SETTINGS_MANAGE));
    $r->post('/configuracoes/empresa', [SettingsController::class, 'updateCompany'], $app(P::SETTINGS_MANAGE));
    $r->post('/configuracoes/setores', [SettingsController::class, 'addDepartment'], $app(P::SETTINGS_MANAGE));
    $r->post('/configuracoes/setores/{id}', [SettingsController::class, 'updateDepartment'], $app(P::SETTINGS_MANAGE));
    $r->post('/configuracoes/setores/{id}/excluir', [SettingsController::class, 'deleteDepartment'], $app(P::SETTINGS_MANAGE));

    $r->get('/auditoria', [AuditController::class, 'index'], $app(P::AUDIT_VIEW));
    $r->get('/auditoria/exportar', [AuditController::class, 'export'], $app(P::AUDIT_VIEW));
    $r->post('/auditoria/verificar', [AuditController::class, 'verify'], $app(P::AUDIT_VIEW));

    $r->get('/perfil', [ProfileController::class, 'show'], ['auth']);
    $r->post('/perfil', [ProfileController::class, 'update'], ['auth']);
    $r->post('/perfil/senha', [ProfileController::class, 'password'], ['auth']);
    $r->post('/perfil/sessoes/encerrar', [ProfileController::class, 'logoutOthers'], ['auth']);
};
