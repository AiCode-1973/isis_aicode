<h1>Instalação concluída</h1>
<p class="lead">O banco foi preparado e o instalador web foi bloqueado. Apague o <span class="mono">INSTALL_TOKEN</span> do <span class="mono">.env</span>.</p>

<?php if ($ran): ?>
<p class="small muted">Migrations aplicadas: <?= e(implode(', ', $ran)) ?></p>
<?php endif; ?>

<?php if ($demo): ?>
<div class="alert alert-info"><?= icon('info') ?>
    <div>
        <p><strong>Contas fictícias de demonstração</strong> (senha: <span class="mono"><?= e(Isis\Database\Seeds\DemoSeeder::PASSWORD) ?></span>)</p>
        <pre class="mono"><?= e(implode("\n", $demo)) ?></pre>
    </div>
</div>
<?php endif; ?>

<a class="btn btn-primary btn-block" href="/entrar">Ir para o login</a>
