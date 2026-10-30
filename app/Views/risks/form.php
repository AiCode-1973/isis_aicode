<?php

use Isis\Support\Labels;

$isNew = $r['id'] === null;
$val = static fn (string $key): string => old($key, (string) ($r[$key] ?? ''));
$scale = static function (string $name, array $labels, string $current, bool $optional = false): string {
    $html = '<select id="f-' . $name . '" name="' . $name . '"' . error_attrs($name) . '>';
    if ($optional) {
        $html .= '<option value="">—</option>';
    }
    foreach ($labels as $k => $v) {
        $html .= '<option value="' . $k . '"' . ($current === (string) $k ? ' selected' : '') . '>' . $k . ' · ' . e($v) . '</option>';
    }
    return $html . '</select>';
};
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/riscos">Riscos</a><?= $isNew ? '' : ' · ' . e(code('RSK', (int) $r['seq'])) ?></p>
        <h1><?= $isNew ? 'Novo risco' : e($r['title']) ?></h1>
        <p>Descreva o evento indesejado, avalie probabilidade e impacto e defina como será tratado.</p>
    </div>
</div>

<div class="grid grid-main">
<form method="post" action="<?= $isNew ? '/riscos' : '/riscos/' . (int) $r['id'] ?>" class="card" novalidate>
    <?= csrf_field() ?>
    <section class="form-section">
        <h2>Identificação</h2>
        <div class="form-grid">
            <div class="field full">
                <label for="f-title">Título do risco</label>
                <input type="text" id="f-title" name="title" value="<?= e($val('title')) ?>" required maxlength="190" placeholder="Ex.: Acesso indevido a prontuários por usuários sem necessidade"<?= error_attrs('title') ?>>
                <?= field_error('title') ?>
            </div>
            <div class="field">
                <label for="f-category">Categoria</label>
                <select id="f-category" name="category">
                    <?php foreach (Labels::RISK_CATEGORY as $k => $v): ?><option value="<?= e($k) ?>"<?= $val('category') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-activity">Operação de tratamento</label>
                <select id="f-activity" name="activity_id"<?= error_attrs('activity_id') ?>>
                    <option value="">— risco geral —</option>
                    <?php foreach ($activities as $id => $name): ?><option value="<?= (int) $id ?>"<?= $val('activity_id') === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
                </select>
                <?= field_error('activity_id') ?>
            </div>
            <div class="field full">
                <label for="f-desc">Descrição</label>
                <textarea id="f-desc" name="description" maxlength="5000"><?= e($val('description')) ?></textarea>
            </div>
            <div class="field">
                <label for="f-cause">Causa / vulnerabilidade</label>
                <textarea id="f-cause" name="cause" maxlength="5000" placeholder="O que pode levar ao evento?"><?= e($val('cause')) ?></textarea>
            </div>
            <div class="field">
                <label for="f-cons">Consequência para os titulares e a empresa</label>
                <textarea id="f-cons" name="consequence" maxlength="5000"><?= e($val('consequence')) ?></textarea>
            </div>
        </div>
    </section>

    <section class="form-section">
        <h2>Avaliação</h2>
        <p class="hint">Nível = probabilidade × impacto. Baixo até 4, médio até 9, alto até 15, crítico de 16 a 25.</p>
        <div class="form-grid">
            <div class="field"><label for="f-likelihood">Probabilidade (inerente)</label><?= $scale('likelihood', Labels::LIKELIHOOD, $val('likelihood')) ?><?= field_error('likelihood') ?></div>
            <div class="field"><label for="f-impact">Impacto (inerente)</label><?= $scale('impact', Labels::IMPACT, $val('impact')) ?><?= field_error('impact') ?></div>
            <div class="field"><label for="f-residual_likelihood">Probabilidade residual <span class="muted">(após as medidas)</span></label><?= $scale('residual_likelihood', Labels::LIKELIHOOD, $val('residual_likelihood'), true) ?></div>
            <div class="field"><label for="f-residual_impact">Impacto residual</label><?= $scale('residual_impact', Labels::IMPACT, $val('residual_impact'), true) ?><?= field_error('residual_impact') ?></div>
        </div>
    </section>

    <section class="form-section">
        <h2>Tratamento e responsabilidade</h2>
        <div class="form-grid">
            <div class="field">
                <label for="f-treatment">Tratamento</label>
                <select id="f-treatment" name="treatment">
                    <?php foreach (Labels::RISK_TREATMENT as $k => $v): ?><option value="<?= e($k) ?>"<?= $val('treatment') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-status">Situação</label>
                <select id="f-status" name="status">
                    <?php foreach (Labels::RISK_STATUS as $k => $v): ?><option value="<?= e($k) ?>"<?= $val('status') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field full">
                <label for="f-tnotes">Medidas e justificativa do tratamento</label>
                <textarea id="f-tnotes" name="treatment_notes" maxlength="5000" placeholder="Medidas mitigadoras previstas ou motivo para aceitar o risco."<?= error_attrs('treatment_notes') ?>><?= e($val('treatment_notes')) ?></textarea>
                <?= field_error('treatment_notes') ?>
            </div>
            <div class="field">
                <label for="f-owner">Responsável</label>
                <select id="f-owner" name="owner_user_id"<?= error_attrs('owner_user_id') ?>>
                    <option value="">—</option>
                    <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= $val('owner_user_id') === (string) $u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-dept">Setor</label>
                <select id="f-dept" name="department_id">
                    <option value="">—</option>
                    <?php foreach ($departments as $id => $name): ?><option value="<?= (int) $id ?>"<?= $val('department_id') === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="f-review">Próxima revisão</label>
                <input type="date" id="f-review" name="review_date" value="<?= e($val('review_date')) ?>"<?= error_attrs('review_date') ?>>
                <?= field_error('review_date') ?>
            </div>
        </div>
    </section>

    <div class="actions sticky-actions">
        <button type="submit" class="btn btn-primary" data-loading="Salvando..."><?= $isNew ? 'Registrar risco' : 'Salvar alterações' ?></button>
        <a class="btn btn-ghost" href="<?= $isNew ? '/riscos' : '/riscos/' . (int) $r['id'] ?>">Cancelar</a>
    </div>
</form>

<aside class="card">
    <h2>Escala de referência</h2>
    <p class="small section-gap-sm"><strong>Probabilidade</strong></p>
    <ul class="guidance small">
        <li>1 Rara: não há histórico, só em circunstâncias excepcionais.</li>
        <li>3 Possível: já ocorreu no setor ou há vulnerabilidade conhecida.</li>
        <li>5 Quase certa: ocorre com frequência ou já está acontecendo.</li>
    </ul>
    <p class="small"><strong>Impacto</strong></p>
    <ul class="guidance small">
        <li>1 Insignificante: sem efeito perceptível para os titulares.</li>
        <li>3 Moderado: transtornos reversíveis, poucos titulares.</li>
        <li>5 Gravíssimo: dano relevante, dados sensíveis ou muitos titulares; pode exigir comunicação à ANPD.</li>
    </ul>
    <?= Isis\Core\View::partial('partials/heatmap', ['heatmap' => [], 'selected' => null]) ?>
</aside>
</div>
