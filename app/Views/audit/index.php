<div class="page-head">
    <div>
        <h1>Trilha de auditoria</h1>
        <p>Registro de acessos e ações: usuário, data, horário, IP e alterações. Os registros não podem ser editados nem excluídos.</p>
    </div>
    <div class="actions">
        <form method="post" action="/auditoria/verificar" class="inline-form">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost" data-loading="Verificando..."><?= icon('shield') ?> Verificar integridade</button>
        </form>
        <a class="btn btn-primary" href="/auditoria/exportar<?= $query !== '' ? '?' . e($query) : '' ?>"><?= icon('download') ?> Exportar CSV</a>
    </div>
</div>

<form method="get" action="/auditoria" class="card filters" aria-label="Filtros">
    <div class="field">
        <label for="f-acao">Ação</label>
        <select id="f-acao" name="acao">
            <option value="">Todas</option>
            <?php foreach ($actions as $action): ?>
            <option value="<?= e($action) ?>"<?= $filters['action'] === $action ? ' selected' : '' ?>><?= e(audit_label($action)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="f-usuario">Usuário</label>
        <select id="f-usuario" name="usuario">
            <option value="">Todos</option>
            <?php foreach ($users as $u): ?>
            <option value="<?= (int) $u['id'] ?>"<?= $filters['user_id'] === (int) $u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="f-de">De</label>
        <input type="date" id="f-de" name="de" value="<?= e($filters['from'] ?? '') ?>">
    </div>
    <div class="field">
        <label for="f-ate">Até</label>
        <input type="date" id="f-ate" name="ate" value="<?= e($filters['to'] ?? '') ?>">
    </div>
    <div class="actions">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <a class="btn btn-ghost" href="/auditoria">Limpar</a>
    </div>
</form>

<section class="card section-gap" aria-label="Registros">
    <p class="small muted"><?= (int) $total ?> registro(s) encontrados.</p>
    <?php if (!$rows): ?>
    <p class="empty">Nenhum registro para os filtros escolhidos.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table-stack">
            <thead><tr><th>Data e hora</th><th>Usuário</th><th>Ação</th><th>Registro</th><th>IP</th><th>Detalhes</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td data-label="Data e hora" class="mono"><?= e(date('d/m/Y H:i:s', strtotime((string) $row['created_at']))) ?></td>
                <td data-label="Usuário"><?= e($row['user_name'] ?? 'Sistema') ?></td>
                <td data-label="Ação">
                    <?= e(audit_label($row['action'])) ?>
                    <?php if (str_starts_with($row['action'], 'access.') || $row['action'] === 'auth.login_failed' || $row['action'] === 'auth.login_blocked'): ?>
                    <span class="badge badge-danger">segurança</span>
                    <?php endif; ?>
                </td>
                <td data-label="Registro"><?= e($row['entity_type'] ?? '—') ?><?= $row['entity_id'] ? ' #' . (int) $row['entity_id'] : '' ?></td>
                <td data-label="IP" class="mono"><?= e($row['ip'] ?? '—') ?></td>
                <td data-label="Detalhes">
                    <?php if ($row['changes']): ?>
                    <details class="changes">
                        <summary>Ver alterações</summary>
                        <pre><?= e(json_encode(json_decode((string) $row['changes'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                    </details>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Paginação">
        <?php $base = '/auditoria?' . ($query !== '' ? $query . '&' : ''); ?>
        <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e($base . 'pagina=' . ($page - 1)) ?>">Anterior</a><?php endif; ?>
        <span class="small muted">Página <?= (int) $page ?> de <?= (int) $pages ?></span>
        <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= e($base . 'pagina=' . ($page + 1)) ?>">Próxima</a><?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</section>
