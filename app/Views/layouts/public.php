<!doctype html>
<html lang="pt-BR">
<head>
<?= Isis\Core\View::partial('partials/head', ['title' => $title ?? '']) ?>
</head>
<body>
<header class="site-header">
    <div class="container inner">
        <?= Isis\Core\View::partial('partials/brand') ?>
        <nav class="actions nav-links" aria-label="Acesso">
            <a class="btn btn-ghost" href="/entrar">Entrar</a>
            <a class="btn btn-primary" href="/cadastro">Criar conta</a>
        </nav>
    </div>
</header>
<main id="main">
    <?= $content ?>
</main>
<footer class="site-footer">
    <div class="container inner">
        <span>© <?= date('Y') ?> ISIS · Gestão da Privacidade e Proteção de Dados · AiCode</span>
        <a href="/termos">Termos de uso e aviso de privacidade</a>
    </div>
</footer>
</body>
</html>
