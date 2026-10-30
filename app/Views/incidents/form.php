<?php

use Isis\Support\Labels;

?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/incidentes">Incidentes</a></p>
        <h1>Comunicar incidente de segurança</h1>
        <p>Viu algo errado com dados ou sistemas? Comunique já, mesmo sem ter certeza. O encarregado será avisado e fará a análise.</p>
    </div>
</div>

<div class="grid grid-main">
<form method="post" action="/incidentes" class="card" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="field full">
            <label for="f-title">Resumo em uma frase</label>
            <input type="text" id="f-title" name="title" value="<?= e(old('title')) ?>" required maxlength="190" placeholder="Ex.: Planilha de clientes enviada para o e-mail errado"<?= error_attrs('title') ?>>
            <?= field_error('title') ?>
        </div>
        <div class="field">
            <label for="f-cat">Tipo</label>
            <select id="f-cat" name="category"<?= error_attrs('category') ?>>
                <?php foreach (Labels::INCIDENT_CATEGORY as $k => $v): ?><option value="<?= e($k) ?>"<?= old('category', 'outro') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="f-dept">Setor envolvido</label>
            <select id="f-dept" name="department_id">
                <option value="">Não sei / vários</option>
                <?php foreach ($departments as $id => $name): ?><option value="<?= (int) $id ?>"<?= old('department_id', (string) (auth_user()['department_id'] ?? '')) === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="field full">
            <label for="f-desc">O que aconteceu</label>
            <textarea id="f-desc" name="description" rows="6" maxlength="10000" placeholder="Descreva o que viu, quais dados ou sistemas podem ter sido afetados e o que já foi feito."<?= error_attrs('description') ?>><?= e(old('description')) ?></textarea>
            <p class="hint">Não cole aqui senhas nem cópias dos dados expostos. Esta descrição é guardada criptografada.</p>
            <?= field_error('description') ?>
        </div>
        <div class="field">
            <label for="f-detected">Quando foi percebido</label>
            <input type="date" id="f-detected" name="detected_at" value="<?= e(old('detected_at', date('Y-m-d'))) ?>" max="<?= date('Y-m-d') ?>"<?= error_attrs('detected_at') ?>>
            <?= field_error('detected_at') ?>
        </div>
        <div class="field">
            <label for="f-occurred">Quando ocorreu <span class="muted">(se souber)</span></label>
            <input type="date" id="f-occurred" name="occurred_at" value="<?= e(old('occurred_at')) ?>" max="<?= date('Y-m-d') ?>">
        </div>
        <div class="field full">
            <span class="label">Há dados pessoais envolvidos?</span>
            <div class="actions">
                <?php foreach (['sim' => 'Sim', 'nao' => 'Não', 'nao_sei' => 'Não sei'] as $k => $v): ?>
                <label class="chip-check"><input type="radio" name="personal_data" value="<?= $k ?>"<?= old('personal_data', 'nao_sei') === $k ? ' checked' : '' ?>> <span><?= $v ?></span></label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="actions sticky-actions">
        <button type="submit" class="btn btn-danger-solid" data-loading="Enviando...">Comunicar incidente</button>
        <a class="btn btn-ghost" href="/incidentes">Cancelar</a>
    </div>
</form>

<aside class="card">
    <h2>O que fazer agora</h2>
    <ul class="guidance section-gap-sm">
        <li>Não apague registros, e-mails ou arquivos relacionados: eles são evidências.</li>
        <li>Se for possível conter com segurança (ex.: pedir ao destinatário que apague um e-mail enviado por engano), faça e conte o que fez na descrição.</li>
        <li>Não divulgue o ocorrido fora dos canais da empresa.</li>
        <li>Se houver risco ou dano relevante aos titulares, a empresa tem prazo curto para comunicar a ANPD: por isso, comunique já.</li>
    </ul>
</aside>
</div>
