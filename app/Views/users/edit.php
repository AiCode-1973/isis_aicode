<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/usuarios">Usuários</a></p>
        <h1><?= e($editUser['name']) ?></h1>
        <p><?= e($editUser['email']) ?> · último acesso: <?= e(fmt_datetime($editUser['last_login_at'])) ?></p>
    </div>
</div>

<div class="grid grid-main">
    <form method="post" action="/usuarios/<?= (int) $editUser['id'] ?>" class="card" novalidate>
        <?= csrf_field() ?>
        <?php if ($isSelf): ?>
        <div class="alert alert-info"><?= icon('info') ?><p>Esta é a sua conta. Perfil e situação só podem ser alterados por outro administrador.</p></div>
        <?php endif; ?>
        <div class="form-grid">
            <div class="field">
                <label for="f-name">Nome</label>
                <input type="text" id="f-name" name="name" value="<?= e(old('name', $editUser['name'])) ?>" required maxlength="150"<?= error_attrs('name') ?>>
                <?= field_error('name') ?>
            </div>
            <div class="field">
                <label for="f-job">Cargo <span class="muted">(opcional)</span></label>
                <input type="text" id="f-job" name="job_title" value="<?= e(old('job_title', $editUser['job_title'] ?? '')) ?>" maxlength="120"<?= error_attrs('job_title') ?>>
                <?= field_error('job_title') ?>
            </div>
            <div class="field">
                <label for="f-role">Perfil</label>
                <select id="f-role" name="role"<?= $isSelf ? ' disabled' : '' ?><?= error_attrs('role') ?>>
                    <?php foreach ($roles as $role): ?>
                    <option value="<?= e($role->value) ?>"<?= old('role', $editUser['role']) === $role->value ? ' selected' : '' ?>><?= e($role->label()) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($isSelf): ?><input type="hidden" name="role" value="<?= e($editUser['role']) ?>"><?php endif; ?>
                <?= field_error('role') ?>
            </div>
            <div class="field">
                <label for="f-department">Setor</label>
                <select id="f-department" name="department_id"<?= error_attrs('department_id') ?>>
                    <option value="">Sem setor</option>
                    <?php foreach ($departments as $id => $name): ?>
                    <option value="<?= (int) $id ?>"<?= old('department_id', (string) ($editUser['department_id'] ?? '')) === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error('department_id') ?>
            </div>
            <div class="field full">
                <label for="f-status">Situação</label>
                <select id="f-status" name="status"<?= $isSelf ? ' disabled' : '' ?>>
                    <option value="active"<?= old('status', $editUser['status']) === 'active' ? ' selected' : '' ?>>Ativo</option>
                    <option value="inactive"<?= old('status', $editUser['status']) === 'inactive' ? ' selected' : '' ?>>Inativo (bloqueia o acesso imediatamente)</option>
                </select>
                <?php if ($isSelf): ?><input type="hidden" name="status" value="<?= e($editUser['status']) ?>"><?php endif; ?>
            </div>
        </div>
        <div class="actions section-gap">
            <button type="submit" class="btn btn-primary" data-loading="Salvando...">Salvar alterações</button>
            <a class="btn btn-ghost" href="/usuarios">Cancelar</a>
        </div>
    </form>

    <div class="stack">
    <?php if (!$isSelf): ?>
    <section class="card">
        <h2>Verificação em duas etapas</h2>
        <?php if (Isis\Services\MfaService::isEnabled($editUser)): ?>
        <p class="small section-gap-sm"><span class="badge badge-ok">Ativada</span> desde <?= e(fmt_date($editUser['mfa_enabled_at'])) ?>.</p>
        <form method="post" action="/usuarios/<?= (int) $editUser['id'] ?>/dois-fatores/redefinir" class="section-gap-sm" data-confirm="Redefinir a verificação em duas etapas de <?= e($editUser['name']) ?>? Use só se a pessoa perdeu o celular e os códigos de recuperação. Confirme a identidade dela antes.">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost"><?= icon('refresh') ?> Redefinir</button>
        </form>
        <p class="hint section-gap-sm">Use quando a pessoa perder o celular e os códigos de recuperação. Ela entra só com a senha e cadastra o aplicativo de novo.</p>
        <?php else: ?>
        <p class="small muted section-gap-sm">Não ativada.</p>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <aside class="card">
        <h2>Boas práticas</h2>
        <ul class="guidance">
            <li>Conceda o perfil com o menor privilégio que permita o trabalho.</li>
            <li>Desative contas de quem saiu da empresa no mesmo dia.</li>
            <li>Mudanças de perfil encerram as sessões abertas da pessoa.</li>
            <li>Toda alteração fica registrada na trilha de auditoria.</li>
        </ul>
    </aside>
    </div>
</div>
