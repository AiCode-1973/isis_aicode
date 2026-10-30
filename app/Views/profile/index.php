<div class="page-head">
    <div>
        <h1>Meu perfil</h1>
        <p><?= e($me['email']) ?> · <?= e(role_label($me['role'])) ?></p>
    </div>
</div>

<div class="grid grid-2">
    <form method="post" action="/perfil" class="card" novalidate>
        <?= csrf_field() ?>
        <h2>Dados pessoais</h2>
        <div class="field section-gap-sm">
            <label for="f-name">Nome</label>
            <input type="text" id="f-name" name="name" value="<?= e(old('name', $me['name'])) ?>" required maxlength="150" autocomplete="name"<?= error_attrs('name') ?>>
            <?= field_error('name') ?>
        </div>
        <div class="field">
            <label for="f-job">Cargo</label>
            <input type="text" id="f-job" name="job_title" value="<?= e(old('job_title', $me['job_title'] ?? '')) ?>" maxlength="120"<?= error_attrs('job_title') ?>>
            <?= field_error('job_title') ?>
        </div>
        <div class="field">
            <label for="f-email">E-mail</label>
            <input type="email" id="f-email" value="<?= e($me['email']) ?>" readonly>
            <p class="hint">Para trocar o e-mail, peça ao administrador da empresa.</p>
        </div>
        <button type="submit" class="btn btn-primary" data-loading="Salvando...">Salvar</button>
    </form>

    <div class="stack">
        <form method="post" action="/perfil/senha" class="card" id="senha" novalidate>
            <?= csrf_field() ?>
            <h2>Alterar senha</h2>
            <div class="section-gap-sm">
                <?= Isis\Core\View::partial('partials/password', ['name' => 'current_password', 'label' => 'Senha atual', 'autocomplete' => 'current-password']) ?>
                <?= Isis\Core\View::partial('partials/password', ['name' => 'password', 'label' => 'Nova senha', 'hint' => 'Mínimo de 10 caracteres, com maiúsculas, minúsculas e números.']) ?>
                <?= Isis\Core\View::partial('partials/password', ['name' => 'password_confirmation', 'label' => 'Confirme a nova senha']) ?>
            </div>
            <button type="submit" class="btn btn-primary" data-loading="Salvando...">Alterar senha</button>
        </form>

        <section class="card">
            <h2>Sessões</h2>
            <p class="small muted">Último acesso: <?= e(fmt_datetime($me['last_login_at'])) ?>. A sessão expira após <?= (int) config('session.idle_minutes') ?> minutos sem uso.</p>
            <form method="post" action="/perfil/sessoes/encerrar" data-confirm="Encerrar sua sessão em todos os outros dispositivos?">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-ghost"><?= icon('logout') ?> Sair de todos os outros dispositivos</button>
            </form>
        </section>
    </div>
</div>
