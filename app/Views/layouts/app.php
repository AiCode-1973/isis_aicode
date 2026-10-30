<?php

use Isis\Auth\Auth;
use Isis\Auth\Permission as P;

$user = Auth::user();
$tenant = Auth::tenant();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<?= Isis\Core\View::partial('partials/head', ['title' => $title ?? '']) ?>
</head>
<body class="app-shell">
<a class="skip-link" href="#main">Pular para o conteúdo</a>

<aside class="sidebar" id="sidebar" aria-label="Menu principal">
    <?= Isis\Core\View::partial('partials/brand') ?>
    <?php if ($tenant): ?>
    <div class="tenant-chip">
        <strong><?= e($tenant['trade_name'] ?: $tenant['legal_name']) ?></strong>
        <span><?= e($tenant['segment_name'] ?? '') ?></span>
    </div>
    <?php endif; ?>

    <nav>
        <p class="nav-section">Visão geral</p>
        <ul class="nav">
            <?php if (can(P::DASHBOARD_VIEW)): ?>
            <li><a class="<?= nav_active('/painel') ?>" href="/painel"><?= icon('dashboard') ?> Painel de conformidade</a></li>
            <?php endif; ?>
        </ul>

        <?php if (can(P::USERS_VIEW) || can(P::SETTINGS_MANAGE) || can(P::AUDIT_VIEW)): ?>
        <p class="nav-section">Governança</p>
        <ul class="nav">
            <?php if (can(P::USERS_VIEW)): ?>
            <li><a class="<?= nav_active('/usuarios') ?>" href="/usuarios"><?= icon('users') ?> Usuários e perfis</a></li>
            <?php endif; ?>
            <?php if (can(P::SETTINGS_MANAGE)): ?>
            <li><a class="<?= nav_active('/configuracoes') ?>" href="/configuracoes"><?= icon('settings') ?> Configurações</a></li>
            <?php endif; ?>
            <?php if (can(P::AUDIT_VIEW)): ?>
            <li><a class="<?= nav_active('/auditoria') ?>" href="/auditoria"><?= icon('audit') ?> Trilha de auditoria</a></li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>

        <p class="nav-section">Conta</p>
        <ul class="nav">
            <li><a class="<?= nav_active('/perfil') ?>" href="/perfil"><?= icon('user') ?> Meu perfil</a></li>
        </ul>
    </nav>

    <div class="sidebar-foot">ISIS v<?= e(APP_VERSION) ?> · LGPD • Governança • Segurança • Conformidade</div>
</aside>
<div class="sidebar-backdrop" data-close-sidebar></div>

<div class="main-wrap">
    <header class="topbar">
        <button type="button" class="icon-btn menu-btn" data-toggle-sidebar aria-controls="sidebar" aria-expanded="false" aria-label="Abrir menu"><?= icon('menu') ?></button>
        <div class="topbar-title"><?= e($title ?? '') ?></div>
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
                <form method="post" action="/sair" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit"><?= icon('logout') ?> Sair</button>
                </form>
            </div>
        </details>
        <?php endif; ?>
    </header>

    <main id="main" class="content" tabindex="-1">
        <?= Isis\Core\View::partial('partials/flash') ?>
        <?= $content ?>
    </main>

    <footer class="app-footer">
        <strong>ISIS</strong> · Gestão da Privacidade e Proteção de Dados ·
        <?= Isis\Core\View::partial('partials/disclaimer') ?>
    </footer>
</div>
</body>
</html>
