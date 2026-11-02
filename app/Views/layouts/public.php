<!doctype html>
<html lang="pt-BR">
<head>
<?= Isis\Core\View::partial('partials/head', ['title' => $title ?? '']) ?>
</head>
<body class="public">
<header class="site-header">
    <div class="container inner">
        <?= Isis\Core\View::partial('partials/brand') ?>
        <nav class="site-nav" aria-label="Seções do site">
            <a href="/#modulos">Módulos</a>
            <a href="/#seguranca">Segurança</a>
            <a href="/#planos">Planos</a>
            <a href="/#duvidas">Dúvidas</a>
        </nav>
        <nav class="actions nav-links" aria-label="Acesso">
            <a class="btn btn-glass" href="/entrar">Entrar</a>
            <a class="btn btn-teal" href="/cadastro">Criar conta</a>
        </nav>
    </div>
</header>
<main id="main">
    <?= $content ?>
</main>
<footer class="site-footer">
    <div class="container inner">
        <span>© <?= date('Y') ?> ÍSIS · Gestão da Privacidade e Proteção de Dados · AiCode</span>
        <span class="actions"><a href="/#planos">Planos e preços</a> · <a href="/manual">Manual do sistema</a> · <a href="/termos">Termos de uso</a> · <a href="/privacidade">Aviso de privacidade</a> · <a href="/acordo-de-tratamento">Acordo de tratamento de dados</a></span>
    </div>
</footer>
</body>
</html>
