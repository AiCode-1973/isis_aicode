<?php
$selectedSegment = old('segment_code', $segmentCode);
$suggested = $template['departments'] ?? [];
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Configuração inicial</p>
        <h1>Vamos preparar o ambiente de <?= e($tenant['trade_name'] ?: $tenant['legal_name']) ?></h1>
        <p>Três passos rápidos. Tudo pode ser alterado depois em Configurações.</p>
    </div>
</div>

<form method="post" action="/configuracao-inicial" class="card" novalidate>
    <?= csrf_field() ?>

    <div class="wizard-step">
        <span class="wizard-num">1</span>
        <div class="wizard-body">
            <h2>Segmento de atuação</h2>
            <p class="muted small">As sugestões abaixo mudam conforme o segmento. Elas são só um ponto de partida.</p>
            <div class="field">
                <label for="f-segment_code">Segmento</label>
                <select id="f-segment_code" name="segment_code" data-segment-reload<?= error_attrs('segment_code') ?>>
                    <?php foreach ($segments as $code => $name): ?>
                    <option value="<?= e($code) ?>"<?= $selectedSegment === $code ? ' selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error('segment_code') ?>
            </div>
        </div>
    </div>

    <hr class="divider">

    <div class="wizard-step">
        <span class="wizard-num">2</span>
        <div class="wizard-body">
            <h2>Setores da empresa</h2>
            <p class="muted small">Os setores organizam usuários, inventário e responsabilidades. Marque os que existem e acrescente outros.</p>
            <fieldset>
                <legend class="visually-hidden">Setores sugeridos</legend>
                <div class="chips-check">
                    <?php foreach ($suggested as $dept): ?>
                    <label class="chip-check">
                        <input type="checkbox" name="departments[]" value="<?= e($dept) ?>" checked>
                        <span><?= e($dept) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <?= field_error('departments') ?>
            <?php if ($existing): ?>
            <p class="hint">Já cadastrados: <?= e(implode(', ', $existing)) ?>.</p>
            <?php endif; ?>
            <div class="field">
                <label for="f-other">Outros setores <span class="muted">(um por linha)</span></label>
                <textarea id="f-other" name="other_departments" maxlength="2000"<?= error_attrs('other_departments') ?>><?= e(old('other_departments')) ?></textarea>
                <?= field_error('other_departments') ?>
            </div>
        </div>
    </div>

    <hr class="divider">

    <div class="wizard-step">
        <span class="wizard-num">3</span>
        <div class="wizard-body">
            <h2>Encarregado e canal de privacidade</h2>
            <p class="muted small">O encarregado (DPO) é o canal de comunicação entre a empresa, os titulares e a ANPD (art. 41). Pode ser você, outra pessoa da equipe ou um serviço externo.</p>
            <div class="form-grid">
                <div class="field">
                    <label for="f-dpo_name">Nome do encarregado</label>
                    <input type="text" id="f-dpo_name" name="dpo_name" value="<?= e(old('dpo_name', $tenant['dpo_name'] ?? $user['name'])) ?>" required maxlength="150"<?= error_attrs('dpo_name') ?>>
                    <?= field_error('dpo_name') ?>
                </div>
                <div class="field">
                    <label for="f-dpo_email">E-mail do encarregado</label>
                    <input type="email" id="f-dpo_email" name="dpo_email" value="<?= e(old('dpo_email', $tenant['dpo_email'] ?? $user['email'])) ?>" required maxlength="190"<?= error_attrs('dpo_email') ?>>
                    <?= field_error('dpo_email') ?>
                </div>
                <div class="field full">
                    <label for="f-privacy_email">E-mail público do canal de privacidade <span class="muted">(opcional)</span></label>
                    <input type="email" id="f-privacy_email" name="privacy_email" value="<?= e(old('privacy_email', $tenant['privacy_email'] ?? '')) ?>" maxlength="190" placeholder="privacidade@suaempresa.com.br"<?= error_attrs('privacy_email') ?>>
                    <p class="hint">Se ficar em branco, usamos o e-mail do encarregado.</p>
                    <?= field_error('privacy_email') ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($template['guidance'])): ?>
    <hr class="divider">
    <div class="alert alert-info">
        <?= icon('info') ?>
        <div>
            <p><strong>Pontos de atenção para o segmento</strong></p>
            <ul class="guidance">
                <?php foreach ($template['guidance'] as $tip): ?><li><?= e($tip) ?></li><?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <div class="field">
        <label class="check">
            <input type="checkbox" name="acknowledge" value="1"<?= error_attrs('acknowledge') ?>>
            <span>Entendo que o ISIS apoia a organização da conformidade e que as decisões jurídicas continuam sob responsabilidade da empresa.</span>
        </label>
        <?= field_error('acknowledge') ?>
    </div>

    <div class="actions">
        <button type="submit" class="btn btn-primary" data-loading="Salvando...">Concluir configuração <?= icon('arrow-right') ?></button>
    </div>
</form>

<form method="post" action="/sair" class="inline-form">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-link small">Sair e continuar depois</button>
</form>
