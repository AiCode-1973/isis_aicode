<?php

use Isis\Auth\Auth;
use Isis\Auth\Permission as P;

$user = Auth::user();
$tenant = Auth::tenant();

// Menu lateral: seção => itens [permissão (null = todos; lista = qualquer uma), caminho, ícone, rótulo]
$menu = [
    'Visão geral' => [
        [P::DASHBOARD_VIEW, '/painel', 'dashboard', 'Painel de conformidade'],
        [P::DASHBOARD_VIEW, '/tarefas', 'clock', 'O que preciso fazer?'],
        [[P::INVENTORY_EDIT, P::RISKS_EDIT, P::ACTIONS_EDIT, P::REQUESTS_MANAGE, P::INCIDENTS_REPORT], '/modelos', 'layers', 'Modelos prontos'],
        [P::REPORTS_VIEW, '/relatorios', 'file', 'Relatório de conformidade'],
    ],
    'Conformidade' => [
        [P::INVENTORY_VIEW, '/inventario', 'database', 'Inventário de dados'],
        [P::RISKS_VIEW, '/riscos', 'target', 'Riscos'],
        [P::DPIA_VIEW, '/ripd', 'scale', 'Relatórios de impacto'],
        [P::ACTIONS_VIEW, '/planos', 'check', Isis\Services\AccessScope::onlyOwnActions() ? 'Minhas tarefas' : 'Planos de ação'],
        [P::REQUESTS_VIEW, '/titulares', 'inbox', 'Solicitações de titulares'],
        [[P::INCIDENTS_VIEW, P::INCIDENTS_REPORT], '/incidentes', 'siren', 'Incidentes de segurança'],
    ],
    'Programa de privacidade' => [
        [P::VENDORS_VIEW, '/fornecedores', 'truck', 'Fornecedores'],
        [P::POLICIES_VIEW, '/politicas', 'file', 'Políticas e documentos'],
        [P::TRAININGS_VIEW, '/treinamentos', 'graduation', can(P::TRAININGS_MANAGE) ? 'Treinamentos' : 'Meus treinamentos'],
    ],
    'Governança' => [
        [P::USERS_VIEW, '/usuarios', 'users', 'Usuários e perfis'],
        [P::SETTINGS_MANAGE, '/configuracoes', 'settings', 'Configurações'],
        [P::SETTINGS_MANAGE, '/plano', 'layers', 'Plano e assinatura'],
        [P::AUDIT_VIEW, '/auditoria', 'audit', 'Trilha de auditoria'],
    ],
    'Conta' => [
        [null, '/perfil', 'user', 'Meu perfil'],
        [null, '/manual', 'info', 'Manual do sistema'],
    ],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
<?= Isis\Core\View::partial('partials/head', ['title' => $title ?? '']) ?>
<script src="<?= e(asset('js/sidebar-state.js')) ?>"></script>
</head>
<body class="app-shell">
<a class="skip-link" href="#main">Pular para o conteúdo</a>

<aside class="sidebar" id="sidebar" aria-label="Menu principal">
    <?= Isis\Core\View::partial('partials/brand') ?>
    <?php if ($tenant): ?>
    <div class="tenant-chip" title="<?= e($tenant['trade_name'] ?: $tenant['legal_name']) ?>">
        <span class="tenant-initials" aria-hidden="true"><?= e(initials($tenant['trade_name'] ?: $tenant['legal_name'])) ?></span>
        <span class="tenant-text">
            <strong><?= e($tenant['trade_name'] ?: $tenant['legal_name']) ?></strong>
            <span><?= e($tenant['segment_name'] ?? '') ?></span>
        </span>
    </div>
    <?php endif; ?>

    <nav>
        <?php foreach ($menu as $section => $items):
            $visible = array_filter($items, static fn (array $i): bool => $i[0] === null
                || array_filter((array) $i[0], static fn (string $p): bool => can($p)) !== []);
            if (!$visible) {
                continue;
            }
        ?>
        <?php
            // Grupo que abre e fecha; o da página atual sempre abre (estado salvo em sidebar-state.js e app.js)
            $key = Isis\Support\Text::slug($section);
            $current = array_filter($visible, static fn (array $i): bool => nav_active($i[1]) !== '') !== [];
        ?>
        <div class="nav-group<?= $current ? ' has-active' : '' ?>" data-nav-group="<?= e($key) ?>">
            <button type="button" class="nav-section" aria-expanded="true" aria-controls="nav-<?= e($key) ?>"><span><?= e($section) ?></span><?= icon('chevron-down') ?></button>
            <ul class="nav" id="nav-<?= e($key) ?>">
                <?php foreach ($visible as [, $path, $iconName, $label]): ?>
                <li><a class="<?= nav_active($path) ?>" href="<?= e($path) ?>" title="<?= e($label) ?>"><?= icon($iconName) ?><span class="nav-label"><?= e($label) ?></span></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">ÍSIS v<?= e(APP_VERSION) ?> · LGPD • Governança • Segurança • Conformidade</div>
</aside>
<div class="sidebar-backdrop" data-close-sidebar></div>

<div class="main-wrap">
    <header class="topbar">
        <button type="button" class="icon-btn menu-btn" data-toggle-sidebar aria-controls="sidebar" aria-expanded="true" aria-label="Recolher menu" title="Recolher ou expandir o menu"><?= icon('menu') ?></button>
        <?php
        // Caminho de navegação (seção › item do menu), no lugar de repetir o título da página
        $crumb = null;
        foreach ($menu as $section => $items) {
            foreach ($items as [, $path, , $label]) {
                if (nav_active($path) !== '') {
                    $crumb = [$section, $label, $path];
                }
            }
        }
        ?>
        <nav class="topbar-title" aria-label="Você está em">
            <?php if ($crumb): ?>
            <span class="crumb-section"><?= e($crumb[0]) ?></span><span class="crumb-sep" aria-hidden="true">›</span><a href="<?= e($crumb[2]) ?>"><?= e($crumb[1]) ?></a>
            <?php else: ?>
            <span><?= e($tenant['trade_name'] ?? $tenant['legal_name'] ?? '') ?></span>
            <?php endif; ?>
        </nav>
        <?= Isis\Core\View::partial('partials/help') ?>
        <?php if ($user): ?>
        <details class="user-menu">
            <summary aria-label="Menu do usuário">
                <span class="user-meta">
                    <strong><?= e($user['name']) ?></strong>
                    <span><?= e(role_label($user['role'])) ?></span>
                </span>
                <span class="avatar" aria-hidden="true"><?= e(initials($user['name'])) ?></span>
            </summary>
            <div class="menu-pop">
                <a href="/perfil"><?= icon('user') ?> Meu perfil</a>
                <a href="/manual"><?= icon('info') ?> Manual do sistema</a>
                <?php if (can(P::SETTINGS_MANAGE)): ?><a href="/plano"><?= icon('layers') ?> Plano e assinatura</a><?php endif; ?>
                <form method="post" action="/sair" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit"><?= icon('logout') ?> Sair</button>
                </form>
            </div>
        </details>
        <?php endif; ?>
    </header>

    <main id="main" class="content" tabindex="-1">
        <?= Isis\Core\View::partial('partials/billing-banner') ?>
        <?= Isis\Core\View::partial('partials/flash') ?>
        <?= $content ?>
    </main>

    <footer class="app-footer">
        <strong>ÍSIS</strong> · Gestão da Privacidade e Proteção de Dados ·
        <?= Isis\Core\View::partial('partials/disclaimer') ?>
    </footer>
</div>
</body>
</html>
