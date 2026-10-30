<h1>Instalação do ÍSIS</h1>
<p class="lead">Confira os requisitos e informe o token de instalação definido no arquivo <span class="mono">.env</span>.</p>

<div class="table-wrap">
    <table>
        <tbody>
        <?php foreach ($checks as $label => $check): ?>
        <tr>
            <td><?= e($label) ?></td>
            <td><?= $check['ok'] ? '<span class="badge badge-ok">ok</span>' : '<span class="badge badge-danger">' . e($check['detail']) . '</span>' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($suggestedKey): ?>
<div class="alert alert-warn section-gap"><?= icon('alert') ?>
    <p>Defina a chave no <span class="mono">.env</span> antes de instalar (guarde uma cópia segura):<br><span class="mono"><?= e('APP_KEY=' . $suggestedKey) ?></span></p>
</div>
<?php endif; ?>

<?php if (!$tokenConfigured): ?>
<div class="alert alert-error section-gap"><?= icon('lock') ?>
    <p>Instalador desativado: defina <span class="mono">INSTALL_TOKEN</span> no <span class="mono">.env</span> com pelo menos 16 caracteres.</p>
</div>
<?php else: ?>
<form method="post" action="/instalar" class="section-gap" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="f-token">Token de instalação</label>
        <input type="password" id="f-token" name="install_token" required autocomplete="off">
    </div>
    <div class="field">
        <label class="check"><input type="checkbox" name="demo" value="1"> <span>Criar empresas e usuários fictícios de demonstração</span></label>
    </div>
    <button type="submit" class="btn btn-primary btn-block" data-loading="Instalando...">Instalar</button>
</form>
<?php endif; ?>
