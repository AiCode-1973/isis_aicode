<?php

use Isis\Support\Labels;

?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/titulares">Solicitações de titulares</a></p>
        <h1>Registrar solicitação</h1>
        <p>Para pedidos recebidos por e-mail, telefone ou presencialmente. Os que chegam pelo canal on-line já entram sozinhos.</p>
    </div>
</div>

<form method="post" action="/titulares" class="card" novalidate>
    <?= csrf_field() ?>
    <section class="form-section">
        <h2>Pedido</h2>
        <div class="form-grid">
            <div class="field full">
                <label for="f-right">Tipo de pedido</label>
                <select id="f-right" name="right_type"<?= error_attrs('right_type') ?>>
                    <?php foreach (Labels::RIGHT_TYPE as $k => $v): ?><option value="<?= e($k) ?>"<?= old('right_type', 'acesso') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select>
                <?= field_error('right_type') ?>
            </div>
            <div class="field">
                <label for="f-channel">Canal de recebimento</label>
                <select id="f-channel" name="channel">
                    <?php foreach (Labels::REQUEST_CHANNEL as $k => $v): if ($k === 'portal') continue; ?><option value="<?= e($k) ?>"<?= old('channel', 'email') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-received">Data de recebimento</label>
                <input type="date" id="f-received" name="received_at" value="<?= e(old('received_at', date('Y-m-d'))) ?>" max="<?= date('Y-m-d') ?>"<?= error_attrs('received_at') ?>>
                <p class="hint">O prazo de 15 dias conta a partir desta data.</p>
                <?= field_error('received_at') ?>
            </div>
            <div class="field full">
                <label for="f-desc">Descrição do pedido</label>
                <textarea id="f-desc" name="description" maxlength="5000"><?= e(old('description')) ?></textarea>
            </div>
            <div class="field full">
                <label for="f-activity">Operação de tratamento relacionada <span class="muted">(opcional)</span></label>
                <select id="f-activity" name="activity_id">
                    <option value="">—</option>
                    <?php foreach ($activities as $id => $name): ?><option value="<?= (int) $id ?>"<?= old('activity_id') === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
    </section>

    <section class="form-section">
        <h2>Titular</h2>
        <p class="hint">Estes dados ficam criptografados e só são exibidos a quem tem permissão.</p>
        <div class="form-grid">
            <div class="field">
                <label for="f-name">Nome completo</label>
                <input type="text" id="f-name" name="requester_name" value="<?= e(old('requester_name')) ?>" required maxlength="150"<?= error_attrs('requester_name') ?>>
                <?= field_error('requester_name') ?>
            </div>
            <div class="field">
                <label for="f-relation">Quem faz o pedido</label>
                <select id="f-relation" name="requester_relation">
                    <option value="titular"<?= old('requester_relation') !== 'representante' ? ' selected' : '' ?>>O próprio titular</option>
                    <option value="representante"<?= old('requester_relation') === 'representante' ? ' selected' : '' ?>>Representante legal ou procurador</option>
                </select>
            </div>
            <div class="field">
                <label for="f-email">E-mail</label>
                <input type="email" id="f-email" name="requester_email" value="<?= e(old('requester_email')) ?>" maxlength="190"<?= error_attrs('requester_email') ?>>
                <?= field_error('requester_email') ?>
            </div>
            <div class="field">
                <label for="f-phone">Telefone</label>
                <input type="text" id="f-phone" name="requester_phone" value="<?= e(old('requester_phone')) ?>" maxlength="40">
            </div>
            <div class="field">
                <label for="f-doc">Documento <span class="muted">(opcional, para confirmar identidade)</span></label>
                <input type="text" id="f-doc" name="requester_document" value="<?= e(old('requester_document')) ?>" maxlength="40" autocomplete="off">
            </div>
        </div>
    </section>

    <div class="actions sticky-actions">
        <button type="submit" class="btn btn-primary" data-loading="Registrando...">Registrar e gerar protocolo</button>
        <a class="btn btn-ghost" href="/titulares">Cancelar</a>
    </div>
</form>
