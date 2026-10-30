<?php

use Isis\Auth\Permission as P;
use Isis\Support\Deadline;
use Isis\Support\Labels;

$severityBadge = ['critica' => 'badge-danger', 'alta' => 'badge-orange', 'media' => 'badge-warn', 'baixa' => 'badge-ok'];
$personal = $i['personal_data'] === null ? 'nao_sei' : ((int) $i['personal_data'] === 1 ? 'sim' : 'nao');
$left = Deadline::daysLeft($i['anpd_deadline']);
$eventLabel = ['registro' => 'Comunicação interna', 'status' => 'Situação alterada', 'providencia' => 'Providência', 'comunicacao' => 'Comunicação externa', 'classificacao' => 'Classificação', 'nota' => 'Observação'];
$text = static fn (?string $v): string => $v !== null && trim($v) !== '' ? e($v) : '<span class="muted">—</span>';
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/incidentes">Incidentes</a> · <?= e(code('INC', (int) $i['seq'])) ?></p>
        <h1><?= e($i['title']) ?></h1>
        <p>
            <span class="badge"><?= e(explode(' (', Labels::INCIDENT_STATUS[$i['status']])[0]) ?></span>
            <?= $i['severity'] ? '<span class="badge ' . $severityBadge[$i['severity']] . '">Gravidade ' . e(mb_strtolower(Labels::SEVERITY[$i['severity']])) . '</span>' : '<span class="badge badge-warn">a classificar</span>' ?>
            · <?= e(label(Labels::INCIDENT_CATEGORY, $i['category'])) ?>
        </p>
    </div>
    <?php if ($canManage && can(P::ACTIONS_EDIT)): ?>
    <a class="btn btn-ghost" href="/planos/nova?incidente=<?= (int) $i['id'] ?>"><?= icon('plus') ?> Ação corretiva</a>
    <?php endif; ?>
</div>

<?php if ($i['risk_to_subjects'] === 'relevante' && can(P::INCIDENTS_VIEW)): ?>
<section class="card anpd-box<?= $i['anpd_notified_at'] ? ' ok' : '' ?>">
    <?php if ($i['anpd_notified_at']): ?>
    <p><strong><?= icon('check') ?> ANPD comunicada em <?= e(fmt_date($i['anpd_notified_at'])) ?></strong><?= $i['anpd_protocol'] ? ' · protocolo ' . e($i['anpd_protocol']) : '' ?>. Titulares: <?= $i['subjects_notified_at'] ? 'comunicados em ' . e(fmt_date($i['subjects_notified_at'])) : '<span class="text-danger">comunicação ainda não registrada</span>' ?>.</p>
    <?php else: ?>
    <p><strong class="text-danger"><?= icon('alert') ?> Incidente com possível risco ou dano relevante aos titulares.</strong>
    Prazo de referência para comunicar a ANPD e os titulares: <strong><?= e(fmt_date($i['anpd_deadline'])) ?></strong>
    (<?= $left === null ? '—' : ($left < 0 ? abs($left) . ' dia(s) de atraso' : ($left === 0 ? 'vence hoje' : 'faltam ' . $left . ' dia(s)')) ?>).
    <span class="small muted">3 dias úteis a partir do conhecimento (Res. CD/ANPD nº 15/2024); feriados não considerados.</span></p>
    <?php endif; ?>
</section>
<?php endif; ?>

<div class="grid grid-main section-gap">
    <div class="stack">
        <section class="card">
            <h2>Relato</h2>
            <div class="detail-grid section-gap-sm">
                <div class="detail full"><span class="label">O que aconteceu</span><div class="value"><?= $text($i['description']) ?></div></div>
                <div class="detail"><span class="label">Percebido em</span><div class="value"><?= e(fmt_datetime($i['detected_at'])) ?></div></div>
                <div class="detail"><span class="label">Ocorrido em</span><div class="value"><?= e(fmt_date($i['occurred_at'])) ?></div></div>
                <div class="detail"><span class="label">Comunicado por</span><div class="value"><?= e($i['reporter_name'] ?? '—') ?></div></div>
                <div class="detail"><span class="label">Setor</span><div class="value"><?= e($i['department_name'] ?? '—') ?></div></div>
            </div>
        </section>

        <?php if ($canManage): ?>
        <section class="card" id="classificacao">
            <h2>Classificação</h2>
            <form method="post" action="/incidentes/<?= (int) $i['id'] ?>/classificacao" class="section-gap-sm" novalidate>
                <?= csrf_field() ?>
                <div class="form-grid">
                    <div class="field full">
                        <label for="c-title">Título</label>
                        <input type="text" id="c-title" name="title" value="<?= e(old('title', $i['title'])) ?>" maxlength="190" required>
                    </div>
                    <div class="field">
                        <label for="c-cat">Tipo</label>
                        <select id="c-cat" name="category"><?php foreach (Labels::INCIDENT_CATEGORY as $k => $v): ?><option value="<?= e($k) ?>"<?= $i['category'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="field">
                        <label for="c-sev">Gravidade</label>
                        <select id="c-sev" name="severity"<?= error_attrs('severity') ?>><?php foreach (Labels::SEVERITY as $k => $v): ?><option value="<?= e($k) ?>"<?= ($i['severity'] ?? 'media') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="field">
                        <label for="c-pd">Dados pessoais envolvidos</label>
                        <select id="c-pd" name="personal_data"><?php foreach (['sim' => 'Sim', 'nao' => 'Não', 'nao_sei' => 'Ainda não se sabe'] as $k => $v): ?><option value="<?= $k ?>"<?= $personal === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="field">
                        <label for="c-subj">Titulares afetados (estimativa)</label>
                        <input type="text" inputmode="numeric" id="c-subj" name="subjects_affected" value="<?= e((string) ($i['subjects_affected'] ?? '')) ?>" maxlength="9"<?= error_attrs('subjects_affected') ?>>
                        <?= field_error('subjects_affected') ?>
                    </div>
                    <div class="field full">
                        <label class="check"><input type="checkbox" name="sensitive_data" value="1"<?= $i['sensitive_data'] ? ' checked' : '' ?>> <span>Envolve dados sensíveis, de crianças ou financeiros</span></label>
                    </div>
                    <div class="field full">
                        <label for="c-risk">Risco aos titulares (art. 48)</label>
                        <select id="c-risk" name="risk_to_subjects"><?php foreach (Labels::RISK_TO_SUBJECTS as $k => $v): ?><option value="<?= e($k) ?>"<?= $i['risk_to_subjects'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
                        <p class="hint">Considere a natureza dos dados, o volume de titulares, a possibilidade de identificação e os danos possíveis. "Relevante" ativa o prazo de comunicação à ANPD.</p>
                    </div>
                    <div class="field">
                        <label for="c-owner">Responsável pela investigação</label>
                        <select id="c-owner" name="owner_user_id"><option value="">—</option><?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= (string) $i['owner_user_id'] === (string) $u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="field">
                        <label for="c-dept">Setor</label>
                        <select id="c-dept" name="department_id"><option value="">—</option><?php foreach ($departments as $id => $name): ?><option value="<?= (int) $id ?>"<?= (string) $i['department_id'] === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="field">
                        <label for="c-act">Operação de tratamento afetada</label>
                        <select id="c-act" name="activity_id"><option value="">—</option><?php foreach ($activities as $id => $name): ?><option value="<?= (int) $id ?>"<?= (string) $i['activity_id'] === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="field">
                        <label for="c-occ">Quando ocorreu</label>
                        <input type="date" id="c-occ" name="occurred_at" value="<?= e(substr((string) $i['occurred_at'], 0, 10)) ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary section-gap-sm">Salvar classificação</button>
            </form>
        </section>

        <section class="card" id="tratamento">
            <h2>Investigação e providências</h2>
            <form method="post" action="/incidentes/<?= (int) $i['id'] ?>/tratamento" class="section-gap-sm" novalidate>
                <?= csrf_field() ?>
                <div class="field"><label for="t-cont">Medidas de contenção e mitigação</label><textarea id="t-cont" name="containment" maxlength="10000"><?= e($i['containment'] ?? '') ?></textarea></div>
                <div class="field"><label for="t-root">Causa identificada</label><textarea id="t-root" name="root_cause" maxlength="10000"><?= e($i['root_cause'] ?? '') ?></textarea></div>
                <div class="field"><label for="t-less">Lições aprendidas e prevenção</label><textarea id="t-less" name="lessons" maxlength="10000"><?= e($i['lessons'] ?? '') ?></textarea></div>
                <button type="submit" class="btn btn-ghost">Salvar investigação</button>
            </form>
        </section>
        <?php else: ?>
        <section class="card">
            <h2>Acompanhamento</h2>
            <p class="muted">O encarregado de dados está cuidando deste incidente. Situação atual: <strong><?= e(explode(' (', Labels::INCIDENT_STATUS[$i['status']])[0]) ?></strong>.</p>
        </section>
        <?php endif; ?>

        <?= Isis\Core\View::partial('partials/evidences', ['entityType' => 'incident', 'entityId' => (int) $i['id'], 'evidences' => $evidences, 'canUpload' => $canManage || (int) $i['reported_by'] === (int) auth_user()['id']]) ?>

        <?php if (can(P::INCIDENTS_VIEW)): ?>
        <section class="card">
            <h2>Histórico</h2>
            <ul class="timeline section-gap-sm">
                <?php foreach ($events as $ev): ?>
                <li>
                    <div class="meta"><?= e(fmt_datetime($ev['created_at'])) ?> · <?= e($ev['user_name'] ?? 'Sistema') ?> · <?= e($eventLabel[$ev['event_type']] ?? $ev['event_type']) ?></div>
                    <?php if ($ev['status_to'] && $ev['status_from'] !== $ev['status_to']): ?><div><?= $ev['status_from'] ? e(explode(' (', Labels::INCIDENT_STATUS[$ev['status_from']] ?? $ev['status_from'])[0]) . ' → ' : '' ?><strong><?= e(explode(' (', Labels::INCIDENT_STATUS[$ev['status_to']] ?? $ev['status_to'])[0]) ?></strong></div><?php endif; ?>
                    <?php if ($ev['note']): ?><div class="note"><?= e($ev['note']) ?></div><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
    </div>

    <div class="stack">
        <?php if ($canManage): ?>
        <section class="card" id="andamento">
            <h2>Situação e providências</h2>
            <form method="post" action="/incidentes/<?= (int) $i['id'] ?>/andamento" class="section-gap-sm" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="p-status">Situação</label>
                    <select id="p-status" name="status"<?= error_attrs('status') ?>><?php foreach (Labels::INCIDENT_STATUS as $k => $v): ?><option value="<?= e($k) ?>"<?= old('status', $i['status']) === $k ? ' selected' : '' ?>><?= e(explode(' (', $v)[0]) ?></option><?php endforeach; ?></select>
                    <?= field_error('status') ?>
                </div>
                <div class="field">
                    <label for="p-note">Providência tomada</label>
                    <textarea id="p-note" name="note" maxlength="3000"<?= error_attrs('note') ?>><?= e(old('note')) ?></textarea>
                    <?= field_error('note') ?>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Registrar</button>
            </form>
        </section>

        <section class="card" id="comunicacao">
            <h2>Comunicações externas</h2>
            <form method="post" action="/incidentes/<?= (int) $i['id'] ?>/comunicacao" class="section-gap-sm" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="m-anpd">ANPD comunicada em</label>
                    <input type="date" id="m-anpd" name="anpd_notified_at" value="<?= e(substr((string) $i['anpd_notified_at'], 0, 10)) ?>" max="<?= date('Y-m-d') ?>"<?= error_attrs('anpd_notified_at') ?>>
                    <?= field_error('anpd_notified_at') ?>
                </div>
                <div class="field">
                    <label for="m-proto">Protocolo na ANPD</label>
                    <input type="text" id="m-proto" name="anpd_protocol" value="<?= e($i['anpd_protocol'] ?? '') ?>" maxlength="80">
                </div>
                <div class="field">
                    <label for="m-subj">Titulares comunicados em</label>
                    <input type="date" id="m-subj" name="subjects_notified_at" value="<?= e(substr((string) $i['subjects_notified_at'], 0, 10)) ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="field">
                    <label for="m-note">Observação</label>
                    <textarea id="m-note" name="note" maxlength="3000" placeholder="Ex.: meio usado, conteúdo da comunicação"></textarea>
                </div>
                <button type="submit" class="btn btn-ghost btn-block">Salvar comunicações</button>
            </form>
        </section>
        <?php endif; ?>

        <section class="card">
            <h2>Resumo</h2>
            <dl class="dl section-gap-sm">
                <dt>Responsável</dt><dd><?= e($i['owner_name'] ?? '—') ?></dd>
                <dt>Dados pessoais</dt><dd><?= ['sim' => 'Sim', 'nao' => 'Não', 'nao_sei' => 'Não se sabe'][$personal] ?><?= $i['sensitive_data'] ? ' · sensíveis' : '' ?></dd>
                <dt>Titulares</dt><dd><?= $i['subjects_affected'] !== null ? (int) $i['subjects_affected'] : '—' ?></dd>
                <dt>Risco</dt><dd><?= e(Labels::RISK_TO_SUBJECTS[$i['risk_to_subjects']]) ?></dd>
                <dt>Operação</dt><dd><?= $i['activity_id'] ? e(code('OP', (int) $i['activity_seq']) . ' · ' . $i['activity_name']) : '—' ?></dd>
                <?php if ($i['closed_at']): ?><dt>Encerrado em</dt><dd><?= e(fmt_datetime($i['closed_at'])) ?></dd><?php endif; ?>
            </dl>
        </section>

        <?php if ($actions): ?>
        <section class="card">
            <h2>Ações corretivas</h2>
            <ul class="bar-list section-gap-sm">
                <?php foreach ($actions as $x): ?>
                <li><a class="title-link" href="/planos/<?= (int) $x['id'] ?>"><?= e(code('ACT', (int) $x['seq']) . ' · ' . $x['title']) ?></a> <span class="badge badge-sm"><?= e(label(Labels::ACTION_STATUS, $x['status'])) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
    </div>
</div>
