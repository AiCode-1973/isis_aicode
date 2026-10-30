<?php

use Isis\Auth\Auth;
use Isis\Auth\Permission as P;

$canManage = can(P::USERS_MANAGE);
?>
<div class="page-head">
    <div>
        <h1>Usuários e perfis</h1>
        <p>Quem acessa o ambiente da empresa e com qual perfil. Conceda sempre o menor privilégio necessário.</p>
    </div>
</div>

<div class="grid grid-main wide-table">
    <div class="stack">
        <section class="card" aria-labelledby="users-title">
            <div class="card-header">
                <div><h2 id="users-title">Usuários</h2><p><?= count($users) ?> conta(s) nesta empresa.</p></div>
            </div>
            <div class="table-wrap">
                <table class="table-stack">
                    <thead><tr><th>Pessoa</th><th>Perfil</th><th>Setor</th><th>Situação</th><th>Último acesso</th><?php if ($canManage): ?><th><span class="visually-hidden">Ações</span></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td data-label="Pessoa">
                            <div class="person">
                                <span class="avatar" aria-hidden="true"><?= e(initials($u['name'])) ?></span>
                                <div>
                                    <div class="person-name"><?= e($u['name']) ?><?= (int) $u['id'] === Auth::id() ? ' <span class="badge">você</span>' : '' ?></div>
                                    <div class="small muted"><?= e($u['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Perfil"><span class="badge badge-brand"><?= e(role_label($u['role'])) ?></span></td>
                        <td data-label="Setor"><?= e($u['department_name'] ?? '—') ?></td>
                        <td data-label="Situação">
                            <?php if ($u['status'] !== 'active'): ?><span class="badge badge-danger">Inativo</span>
                            <?php elseif ($u['email_verified_at'] === null): ?><span class="badge badge-warn">E-mail não confirmado</span>
                            <?php else: ?><span class="badge badge-ok">Ativo</span><?php endif; ?>
                        </td>
                        <td data-label="Último acesso"><?= e(fmt_datetime($u['last_login_at'])) ?></td>
                        <?php if ($canManage): ?>
                        <td class="actions-cell"><a class="btn btn-ghost btn-sm" href="/usuarios/<?= (int) $u['id'] ?>/editar">Editar</a></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card" aria-labelledby="inv-title">
            <div class="card-header">
                <div><h2 id="inv-title">Convites pendentes</h2><p>Convites ainda não aceitos. Cada link vale por 7 dias.</p></div>
            </div>
            <?php if (!$invitations): ?>
            <p class="empty">Nenhum convite pendente.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table-stack">
                    <thead><tr><th>Convidado</th><th>Perfil</th><th>Setor</th><th>Validade</th><?php if ($canManage): ?><th><span class="visually-hidden">Ações</span></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($invitations as $inv): $expired = strtotime((string) $inv['expires_at']) < time(); ?>
                    <tr>
                        <td data-label="Convidado"><div class="person-name"><?= e($inv['name']) ?></div><div class="small muted"><?= e($inv['email']) ?></div></td>
                        <td data-label="Perfil"><?= e(role_label($inv['role'])) ?></td>
                        <td data-label="Setor"><?= e($inv['department_name'] ?? '—') ?></td>
                        <td data-label="Validade"><?= $expired ? '<span class="badge badge-danger">Expirado</span>' : e(fmt_datetime($inv['expires_at'])) ?></td>
                        <?php if ($canManage): ?>
                        <td class="actions-cell">
                            <div class="actions">
                                <form method="post" action="/usuarios/convites/<?= (int) $inv['id'] ?>/reenviar" class="inline-form">
                                    <?= csrf_field() ?><button type="submit" class="btn btn-ghost btn-sm"><?= icon('refresh') ?> Reenviar</button>
                                </form>
                                <form method="post" action="/usuarios/convites/<?= (int) $inv['id'] ?>/cancelar" class="inline-form" data-confirm="Cancelar o convite de <?= e($inv['email']) ?>?">
                                    <?= csrf_field() ?><button type="submit" class="btn btn-danger btn-sm">Cancelar</button>
                                </form>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="stack">
        <?php if ($canManage): ?>
        <section class="card" id="convidar" aria-labelledby="invite-title">
            <h2 id="invite-title">Convidar colaborador</h2>
            <p class="small muted">A pessoa recebe um link por e-mail para criar a própria senha.</p>
            <form method="post" action="/usuarios/convites" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="f-name">Nome</label>
                    <input type="text" id="f-name" name="name" value="<?= e(old('name')) ?>" required maxlength="150"<?= error_attrs('name') ?>>
                    <?= field_error('name') ?>
                </div>
                <div class="field">
                    <label for="f-email">E-mail</label>
                    <input type="email" id="f-email" name="email" value="<?= e(old('email')) ?>" required maxlength="190"<?= error_attrs('email') ?>>
                    <?= field_error('email') ?>
                </div>
                <div class="field">
                    <label for="f-role">Perfil</label>
                    <select id="f-role" name="role" required<?= error_attrs('role') ?>>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?= e($role->value) ?>"<?= old('role', 'colaborador') === $role->value ? ' selected' : '' ?>><?= e($role->label()) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error('role') ?>
                </div>
                <div class="field">
                    <label for="f-department">Setor <span class="muted">(opcional)</span></label>
                    <select id="f-department" name="department_id"<?= error_attrs('department_id') ?>>
                        <option value="">Sem setor</option>
                        <?php foreach ($departments as $id => $name): ?>
                        <option value="<?= (int) $id ?>"<?= old('department_id') === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error('department_id') ?>
                </div>
                <button type="submit" class="btn btn-primary btn-block" data-loading="Enviando..."><?= icon('mail') ?> Enviar convite</button>
            </form>
        </section>
        <?php endif; ?>

        <section class="card" aria-labelledby="roles-title">
            <h2 id="roles-title">O que cada perfil pode fazer</h2>
            <ul class="role-list">
                <?php foreach ($roles as $role): ?>
                <li><strong><?= e($role->label()) ?></strong><span><?= e($role->description()) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</div>
