<?php

use Isis\Support\Labels;

$isNew = $d['id'] === null;
$val = static fn (string $key): string => old($key, (string) ($d[$key] ?? ''));
$selActivities = old_list('activity_ids', $d['activity_ids']);
$selTriggers = old_list('triggers', $d['triggers']);
$area = static function (string $name, string $label, string $hint, string $value, int $rows = 5): string {
    return '<div class="field full"><label for="f-' . $name . '">' . e($label) . '</label>'
        . '<p class="hint">' . e($hint) . '</p>'
        . '<textarea id="f-' . $name . '" name="' . $name . '" rows="' . $rows . '" maxlength="10000"' . error_attrs($name) . '>' . e($value) . '</textarea>'
        . field_error($name) . '</div>';
};
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/ripd">Relatórios de impacto</a><?= $isNew ? '' : ' · ' . e(code('RIPD', (int) $d['seq'])) . ' · versão ' . (int) $d['version'] ?></p>
        <h1><?= $isNew ? 'Novo RIPD' : e($d['title']) ?></h1>
        <p>Descreva o tratamento, demonstre que ele é necessário e proporcional e registre a conclusão sobre os riscos. Os dados das operações e os riscos vêm do inventário.</p>
    </div>
</div>

<div class="grid grid-main">
<form method="post" action="<?= $isNew ? '/ripd' : '/ripd/' . (int) $d['id'] ?>" class="card" novalidate>
    <?= csrf_field() ?>
    <section class="form-section">
        <h2>1. Identificação e necessidade</h2>
        <div class="form-grid">
            <div class="field full">
                <label for="f-title">Título</label>
                <input type="text" id="f-title" name="title" value="<?= e($val('title')) ?>" required maxlength="190" placeholder="Ex.: RIPD · Prontuário eletrônico de pacientes"<?= error_attrs('title') ?>>
                <?= field_error('title') ?>
            </div>
            <fieldset class="field full">
                <legend class="label">Operações de tratamento avaliadas</legend>
                <?php if (!$activities): ?><p class="hint">Nenhuma operação no inventário. <a href="/inventario/nova">Cadastre a operação</a> antes de elaborar o RIPD.</p><?php else: ?>
                <div class="check-grid">
                    <?php foreach ($activities as $id => $name): ?>
                    <label class="check"><input type="checkbox" name="activity_ids[]" value="<?= (int) $id ?>"<?= in_array((string) $id, $selActivities, true) ? ' checked' : '' ?>> <span><?= e($name) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?= field_error('activity_ids') ?>
            </fieldset>
            <fieldset class="field full">
                <legend class="label">Por que este tratamento pede um RIPD</legend>
                <p class="hint">Marque as situações presentes. As que o inventário já indica vêm marcadas.</p>
                <div class="check-grid">
                    <?php foreach (Labels::DPIA_TRIGGER as $k => $v): ?>
                    <label class="check"><input type="checkbox" name="triggers[]" value="<?= e($k) ?>"<?= in_array($k, $selTriggers, true) ? ' checked' : '' ?>> <span><?= e($v) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <?= $area('need_justification', 'Justificativa', 'Outros motivos para a elaboração (ex.: novo projeto, mudança relevante no tratamento, exigência contratual).', $val('need_justification'), 3) ?>
        </div>
    </section>

    <section class="form-section">
        <h2>2. Descrição do tratamento</h2>
        <div class="form-grid">
            <?= $area('nature', 'Natureza', 'Como os dados são coletados, usados, armazenados e eliminados; quem tem acesso; tecnologias e operadores envolvidos.', $val('nature')) ?>
            <?= $area('scope', 'Escopo', 'Tipos e volume de dados, quantidade de titulares, frequência, abrangência geográfica e tempo de retenção.', $val('scope')) ?>
            <?= $area('context', 'Contexto', 'Relação com os titulares, o que eles esperam, se há vulneráveis, histórico de incidentes e preocupações conhecidas.', $val('context')) ?>
            <?= $area('purpose', 'Finalidade', 'O que a empresa pretende alcançar e os benefícios para ela, para os titulares e para a sociedade.', $val('purpose')) ?>
        </div>
    </section>

    <section class="form-section">
        <h2>3. Consulta às partes interessadas</h2>
        <div class="form-grid">
            <?= $area('stakeholders', 'Partes consultadas', 'Quem foi ouvido (encarregado, TI, segurança, jurídico, áreas de negócio, operadores, representantes dos titulares) e o que foi considerado.', $val('stakeholders'), 4) ?>
        </div>
    </section>

    <section class="form-section">
        <h2>4. Necessidade e proporcionalidade</h2>
        <div class="form-grid">
            <?= $area('necessity', 'Avaliação', 'Por que a hipótese legal se aplica; se a finalidade pode ser alcançada com menos dados; como se garante qualidade, transparência e o atendimento aos direitos dos titulares; controles sobre operadores e transferências.', $val('necessity'), 6) ?>
        </div>
    </section>

    <section class="form-section">
        <h2>5. Riscos e medidas</h2>
        <p class="hint">Os riscos das operações escolhidas entram automaticamente no relatório, com nível inerente e residual, tratamento e plano de ação. Registre novos riscos no módulo <a href="/riscos" target="_blank" rel="noopener">Riscos</a>, vinculados à operação.</p>
        <div class="form-grid">
            <?= $area('safeguards', 'Medidas e salvaguardas gerais', 'Medidas técnicas e administrativas que se aplicam ao tratamento como um todo (ex.: controle de acesso, criptografia, treinamento, contratos com operadores).', $val('safeguards')) ?>
        </div>
    </section>

    <section class="form-section">
        <h2>6. Conclusão e parecer</h2>
        <div class="form-grid">
            <div class="field full">
                <label for="f-conclusion">Conclusão sobre os riscos</label>
                <select id="f-conclusion" name="conclusion"<?= error_attrs('conclusion') ?>>
                    <option value="">— ainda não concluído —</option>
                    <?php foreach (Labels::DPIA_CONCLUSION as $k => $v): ?><option value="<?= e($k) ?>"<?= $val('conclusion') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select>
                <?= field_error('conclusion') ?>
            </div>
            <?= $area('conclusion_notes', 'Fundamentação da conclusão', 'Resumo dos riscos residuais e das condições para o tratamento seguir.', $val('conclusion_notes'), 4) ?>
            <?= $area('dpo_opinion', 'Parecer do encarregado', 'Opinião do encarregado (DPO) sobre o tratamento, os riscos e as medidas.', $val('dpo_opinion'), 4) ?>
            <div class="field">
                <label for="f-owner">Responsável pela elaboração</label>
                <select id="f-owner" name="owner_user_id"<?= error_attrs('owner_user_id') ?>>
                    <option value="">—</option>
                    <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= $val('owner_user_id') === (string) $u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                </select>
                <?= field_error('owner_user_id') ?>
            </div>
            <div class="field">
                <label for="f-review">Próxima revisão</label>
                <input type="date" id="f-review" name="review_due_date" value="<?= e($val('review_due_date')) ?>"<?= error_attrs('review_due_date') ?>>
                <?= field_error('review_due_date') ?>
            </div>
        </div>
    </section>

    <div class="actions sticky-actions">
        <button type="submit" class="btn btn-primary" data-loading="Salvando..."><?= $isNew ? 'Criar RIPD' : 'Salvar' ?></button>
        <a class="btn btn-ghost" href="<?= $isNew ? '/ripd' : '/ripd/' . (int) $d['id'] ?>">Cancelar</a>
    </div>
</form>

<aside class="card">
    <h2>Como elaborar</h2>
    <ul class="guidance small section-gap-sm">
        <li>Faça o RIPD <strong>antes</strong> de iniciar um tratamento de alto risco, ou quando ele mudar de forma relevante.</li>
        <li>Um RIPD pode cobrir várias operações parecidas (ex.: todo o atendimento de pacientes).</li>
        <li>Deixe as operações e os riscos completos no inventário: eles entram no relatório sem precisar copiar.</li>
        <li>Envolva o encarregado desde o início. O parecer dele faz parte do relatório.</li>
        <li>Salve quantas vezes quiser. Ao terminar, envie para aprovação na página do RIPD.</li>
    </ul>
    <p class="hint section-gap">Base: art. 5º, XVII, e art. 38 da LGPD e as orientações da ANPD sobre o RIPD. O ÍSIS organiza o relatório, mas não substitui a análise do encarregado e da assessoria jurídica.</p>
</aside>
</div>
