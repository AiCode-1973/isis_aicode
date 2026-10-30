<!doctype html>
<html lang="pt-BR">
<head>
<?= Isis\Core\View::partial('partials/head', ['title' => $title ?? '']) ?>
</head>
<body>
<div class="auth-shell">
    <aside class="auth-aside">
        <a class="brand" href="/" aria-label="ISIS - página inicial">
            <img src="<?= e(asset('img/logo.svg')) ?>" alt="" width="46" height="46">
            <span>
                <span class="brand-name">ISIS</span>
                <span class="brand-sub">Gestão da Privacidade e Proteção de Dados</span>
            </span>
        </a>
        <div>
            <h2>Organize a proteção de dados da sua empresa em um só lugar.</h2>
            <p>Inventário, riscos, solicitações de titulares, incidentes, fornecedores, políticas, treinamentos e auditoria, com acesso por perfil e trilha de cada ação.</p>
            <div class="pillars"><span>LGPD</span><span>Governança</span><span>Segurança da Informação</span><span>Conformidade</span></div>
        </div>
        <p class="auth-aside-foot"><?= Isis\Core\View::partial('partials/disclaimer') ?></p>
    </aside>
    <main class="auth-main" id="main">
        <div class="auth-card<?= !empty($wide) ? ' wide' : '' ?>">
            <div class="auth-mobile-brand"><?= Isis\Core\View::partial('partials/brand') ?></div>
            <?= Isis\Core\View::partial('partials/flash') ?>
            <?= $content ?>
        </div>
    </main>
</div>
</body>
</html>
