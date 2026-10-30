<?php

use Isis\Auth\PlatformAuth;

$admin = PlatformAuth::admin();
$menu = [
    ['/plataforma', 'users', 'Empresas e assinaturas'],
    ['/plataforma/planos', 'file', 'Planos e preços'],
    ['/plataforma/configuracoes', 'settings', 'Configurações'],
];
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
?>
<!doctype html>
<html lang="pt-BR">
<head>
<?= Isis\Core\View::partial('partials/head', ['title' => ($title ?? '') . ' · Plataforma']) ?>
<script src="<?= e(asset('js/sidebar-state.js')) ?>"></script>
</head>
<body class="app-shell platform-shell">
<a class="skip-link" href="#main">Pular para o conteúdo</a>

<aside class="sidebar" id="sidebar" aria-label="Menu da plataforma">
    <?= Isis\Core\View::partial('partials/brand') ?>
    <div class="tenant-chip" title="Painel da plataforma">
        <span class="tenant-initials" aria-hidden="true">AI</span>
        <span class="tenant-text"><strong>AiCode</strong><span>Painel da plataforma</span></span>
    </div>
    <nav>
        <p class="nav-section"><span>Plataforma</span></p>
        <ul class="nav">
            <?php foreach ($menu as [$href, $iconName, $label]):
                $active = $href === '/plataforma' ? ($path === '/plataforma' || str_starts_with($path, '/plataforma/empresas')) : str_starts_with($path, $href); ?>
            <li><a class="<?= $active ? ' is-active' : '' ?>" href="<?= e($href) ?>" title="<?= e($label) ?>"><?= icon($iconName) ?><span class="nav-label"><?= e($label) ?></span></a></li>
            <?php endforeach; ?>
        </ul>
        <p class="nav-section"><span>Público</span></p>
        <ul class="nav">
            <li><a href="/precos" title="Página de preços"><?= icon('info') ?><span class="nav-label">Página de preços</span></a></li>
        </ul>
    </nav>
    <div class="sidebar-foot">ÍSIS v<?= e(APP_VERSION) ?> · Painel da plataforma</div>
</aside>
<div class="sidebar-backdrop" data-close-sidebar></div>

<div class="main-wrap">
    <header class="topbar">
        <button type="button" class="icon-btn menu-btn" data-toggle-sidebar aria-controls="sidebar" aria-expanded="true" aria-label="Recolher menu" title="Recolher ou expandir o menu"><?= icon('menu') ?></button>
        <div class="topbar-title"><?= e($title ?? '') ?></div>
        <?php if ($admin): ?>
        <details class="user-menu">
            <summary aria-label="Menu do administrador">
                <span class="user-meta"><strong><?= e($admin['name']) ?></strong><span>Equipe AiCode</span></span>
                <span class="avatar" aria-hidden="true"><?= e(initials($admin['name'])) ?></span>
            </summary>
            <div class="menu-pop">
                <a href="/plataforma/configuracoes#senha"><?= icon('lock') ?> Alterar senha</a>
                <form method="post" action="/plataforma/sair" class="inline-form">
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

    <footer class="app-footer"><strong>ÍSIS</strong> · Painel da plataforma · uso restrito à equipe AiCode</footer>
</div>
</body>
</html>
