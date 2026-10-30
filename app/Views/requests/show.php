<?php

use Isis\Support\Deadline;
use Isis\Support\Labels;

$open = in_array($r['status'], ['recebida', 'em_verificacao', 'em_atendimento'], true);
$left = Deadline::daysLeft($r['due_date']);
$statusBadge = ['recebida' => 'badge-warn', 'em_verificacao' => 'badge-brand', 'em_atendimento' => 'badge-brand', 'respondida' => 'badge-ok', 'indeferida' => 'badge', 'cancelada' => 'badge'];
$eventLabel = ['criada' => 'Registro', 'status' => 'Situação alterada', 'nota' => 'Observação', 'resposta' => 'Resposta ao titular', 'verificacao' => 'Identidade', 'atribuicao' => 'Responsável'];
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/titulares">Solicitações de titulares</a> · protocolo</p>
        <h1 class="mono-title"><?= e($r['protocol']) ?></h1>
        <p>
            <span class="badge <?= $statusBadge[$r['status']] ?? '' ?>"><?= e(label(Labels::REQUEST_STATUS, $r['status'])) ?></span>
            <?php if ($open && $left !== null): ?>
            <span class="badge <?= $left < 0 ? 'badge-danger' : ($left <= 3 ? 'badge-warn' : '') ?>"><?= $left < 0 ? abs($left) . ' dia(s) de atraso' : ($left === 0 ? 'vence hoje' : 'faltam ' . $left . ' dia(s)') ?></span>
            <?php endif; ?>
            · <?= e(label(Labels::RIGHT_TYPE, $r['right_type'])) ?>
        </p>
    </div>
</div>

<div class="grid grid-main">
    <div class="stack">
        <section class="card">
            <h2>Pedido</h2>
            <div class="detail-grid section-gap-sm">
                <div class="detail full"><span class="label">Descrição</span><div class="value"><?= $r['description'] ? e($r['description']) : '<span class="muted">—</span>' ?></div></div>
                <div class="detail"><span class="label">Canal</span><div class="value"><?= e(label(Labels::REQUEST_CHANNEL, $r['channel'])) ?></div></div>
                <div class="detail"><span class="label">Recebida em</span><div class="value"><?= e(fmt_datetime($r['received_at'])) ?></div></div>
                <div class="detail"><span class="label">Prazo de referência</span><div class="value <?= $r['overdue'] ? 'text-danger' : '' ?>"><?= e(fmt_date($r['due_date'])) ?></div></div>
                <div class="detail"><span class="label">Operação relacionada</span><div class="value"><?= $r['activity_id'] ? '<a href="/inventario/' . (int) $r['activity_id'] . '">' . e(code('OP', (int) $r['activity_seq']) . ' · ' . $r['activity_name']) . '</a>' : '—' ?></div></div>
            </div>
        </section>

        <?php if ($r['response']): ?>
        <section class="card">
            <h2>Resposta enviada</h2>
            <p class="small muted"><?= e(fmt_datetime($r['responded_at'])) ?> · <?= e(label(Labels::REQUEST_STATUS, $r['status'])) ?></p>
            <div class="value response-box"><?= e($r['response']) ?></div>
        </section>
        <?php endif; ?>

        <?php if ($canManage && $open): ?>
        <section class="card" id="resposta">
            <h2>Responder ao titular</h2>
            <p class="small muted">A resposta fica registrada e visível no acompanhamento on-line. Confirme a identidade do titular antes de enviar dados pessoais.</p>
            <form method="post" action="/titulares/<?= (int) $r['id'] ?>/resposta" class="section-gap-sm" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="f-response">Resposta</label>
                    <textarea id="f-response" name="response" maxlength="10000" rows="6"<?= error_attrs('response') ?>><?= e(old('response')) ?></textarea>
                    <?= field_error('response') ?>
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label for="f-final">Resultado</label>
                        <select id="f-final" name="final_status">
                            <option value="respondida">Atendida / respondida</option>
                            <option value="indeferida"<?= old('final_status') === 'indeferida' ? ' selected' : '' ?>>Indeferida (com justificativa)</option>
                        </select>
                    </div>
                    <div class="field">
                        <span class="label">Envio</span>
                        <label class="check"><input type="checkbox" name="notify" value="1"<?= $r['requester_email'] ? ' checked' : ' disabled' ?>> <span>Enviar a resposta por e-mail ao titular</span></label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" data-loading="Enviando...">Registrar resposta</button>
            </form>
        </section>

        <section class="card" id="andamento">
            <h2>Registrar andamento</h2>
            <form method="post" action="/titulares/<?= (int) $r['id'] ?>/andamento" class="form-grid section-gap-sm" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="f-status">Situação</label>
                    <select id="f-status" name="status">
                        <?php foreach (['recebida', 'em_verificacao', 'em_atendimento', 'cancelada'] as $k): ?><option value="<?= $k ?>"<?= old('status', $r['status']) === $k ? ' selected' : '' ?>><?= e(Labels::REQUEST_STATUS[$k]) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <span class="label">Visibilidade</span>
                    <label class="check"><input type="checkbox" name="visible" value="1"> <span>Mostrar esta observação ao titular no acompanhamento</span></label>
                </div>
                <div class="field full">
                    <label for="f-note">Observação</label>
                    <textarea id="f-note" name="note" maxlength="3000"<?= error_attrs('note') ?>><?= e(old('note')) ?></textarea>
                    <?= field_error('note') ?>
                </div>
                <div class="full"><button type="submit" class="btn btn-ghost">Registrar andamento</button></div>
            </form>
        </section>
        <?php endif; ?>

        <?= Isis\Core\View::partial('partials/evidences', ['entityType' => 'request', 'entityId' => (int) $r['id'], 'evidences' => $evidences, 'canUpload' => $canManage]) ?>

        <section class="card">
            <h2>Histórico</h2>
            <ul class="timeline section-gap-sm">
                <?php foreach ($events as $ev): ?>
                <li>
                    <div class="meta"><?= e(fmt_datetime($ev['created_at'])) ?> · <?= e($ev['user_name'] ?? 'Titular / sistema') ?> · <?= e($eventLabel[$ev['event_type']] ?? $ev['event_type']) ?><?= $ev['visible_to_requester'] ? ' · <span class="badge badge-teal badge-sm">visível ao titular</span>' : '' ?></div>
                    <?php if ($ev['status_from'] !== $ev['status_to'] && $ev['status_to']): ?><div><?= $ev['status_from'] ? e(label(Labels::REQUEST_STATUS, $ev['status_from'])) . ' → ' : '' ?><strong><?= e(label(Labels::REQUEST_STATUS, $ev['status_to'])) ?></strong></div><?php endif; ?>
                    <?php if ($ev['note']): ?><div class="note"><?= e($ev['note']) ?></div><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>

    <div class="stack">
        <section class="card">
            <h2>Titular</h2>
            <dl class="dl section-gap-sm">
                <dt>Nome</dt><dd><?= e($r['requester_name']) ?></dd>
                <dt>Quem pede</dt><dd><?= $r['requester_relation'] === 'representante' ? 'Representante legal' : 'O próprio titular' ?></dd>
                <dt>E-mail</dt><dd><?= e($r['requester_email'] ?? '—') ?></dd>
                <dt>Telefone</dt><dd><?= e($r['requester_phone'] ?? '—') ?></dd>
                <dt>Documento</dt><dd><?= e($r['requester_document'] ?? '—') ?></dd>
            </dl>
            <p class="hint section-gap-sm"><?= icon('lock') ?> Dados criptografados. Cada abertura desta página fica registrada na auditoria.</p>
        </section>

        <section class="card" id="atendimento">
            <h2>Atendimento</h2>
            <?php if ($canManage): ?>
            <form method="post" action="/titulares/<?= (int) $r['id'] ?>/atendimento" class="section-gap-sm" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="f-assigned">Responsável</label>
                    <select id="f-assigned" name="assigned_user_id">
                        <option value="">—</option>
                        <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= (string) $r['assigned_user_id'] === (string) $u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="check"><input type="checkbox" name="identity_verified" value="1"<?= $r['identity_verified'] ? ' checked' : '' ?>> <span>Identidade do titular confirmada</span></label>
                </div>
                <div class="field">
                    <label for="f-idnotes">Como foi confirmada</label>
                    <input type="text" id="f-idnotes" name="identity_notes" value="<?= e($r['identity_notes'] ?? '') ?>" maxlength="400" placeholder="Ex.: confirmação pelo e-mail cadastrado">
                </div>
                <div class="field">
                    <label for="f-act">Operação relacionada</label>
                    <select id="f-act" name="activity_id">
                        <option value="">—</option>
                        <?php foreach ($activities as $id => $name): ?><option value="<?= (int) $id ?>"<?= (string) $r['activity_id'] === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-ghost btn-block">Salvar atendimento</button>
            </form>
            <?php else: ?>
            <dl class="dl section-gap-sm">
                <dt>Responsável</dt><dd><?= e($r['assigned_name'] ?? '—') ?></dd>
                <dt>Identidade</dt><dd><?= $r['identity_verified'] ? 'Confirmada' : 'Não confirmada' ?></dd>
            </dl>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Prazos de referência</h2>
            <ul class="guidance small section-gap-sm">
                <li>Confirmação de existência ou acesso em formato simplificado: imediatamente.</li>
                <li>Declaração clara e completa: até 15 dias do pedido (art. 19, II).</li>
                <li>Se não puder atender, informe os motivos de fato ou de direito (art. 18, § 4º).</li>
            </ul>
            <p class="hint">Referências gerais; confirme com a assessoria jurídica em casos específicos.</p>
        </section>
    </div>
</div>
