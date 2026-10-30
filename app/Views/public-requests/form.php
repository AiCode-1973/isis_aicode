<?php

use Isis\Support\Labels;

$company = $tenant['trade_name'] ?: $tenant['legal_name'];
$base = '/titular/' . $tenant['slug'];
?>
<section class="section">
    <div class="container public-form">
        <div class="public-form-head">
            <p class="eyebrow">Canal de privacidade</p>
            <h1><?= e($company) ?></h1>
            <p class="muted">Use este canal para exercer seus direitos sobre os dados pessoais tratados pela empresa, como previsto no art. 18 da Lei Geral de Proteção de Dados (LGPD).</p>
            <p class="small"><a href="<?= e($base . '/acompanhar') ?>"><?= icon('search') ?> Já fez uma solicitação? Acompanhe pelo protocolo</a></p>
        </div>

        <?= Isis\Core\View::partial('partials/flash') ?>

        <form method="post" action="<?= e($base) ?>" class="card" novalidate>
            <?= csrf_field() ?>
            <div class="honeypot" aria-hidden="true">
                <label for="f-website">Não preencha</label>
                <input type="text" id="f-website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="field">
                <label for="f-right">O que você deseja?</label>
                <select id="f-right" name="right_type"<?= error_attrs('right_type') ?>>
                    <?php foreach (Labels::RIGHT_TYPE as $k => $v): ?><option value="<?= e($k) ?>"<?= old('right_type', 'acesso') === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select>
                <?= field_error('right_type') ?>
            </div>
            <div class="field">
                <label for="f-desc">Descreva seu pedido</label>
                <textarea id="f-desc" name="description" maxlength="3000" rows="5" placeholder="Explique o que precisa. Se possível, diga como você se relaciona com a empresa (cliente, paciente, ex-funcionário...)."<?= error_attrs('description') ?>><?= e(old('description')) ?></textarea>
                <?= field_error('description') ?>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label for="f-name">Nome completo</label>
                    <input type="text" id="f-name" name="requester_name" value="<?= e(old('requester_name')) ?>" required maxlength="150" autocomplete="name"<?= error_attrs('requester_name') ?>>
                    <?= field_error('requester_name') ?>
                </div>
                <div class="field">
                    <label for="f-relation">Você é</label>
                    <select id="f-relation" name="requester_relation">
                        <option value="titular">O próprio titular dos dados</option>
                        <option value="representante"<?= old('requester_relation') === 'representante' ? ' selected' : '' ?>>Representante legal ou procurador</option>
                    </select>
                </div>
                <div class="field">
                    <label for="f-email">E-mail para resposta</label>
                    <input type="email" id="f-email" name="requester_email" value="<?= e(old('requester_email')) ?>" required maxlength="190" autocomplete="email"<?= error_attrs('requester_email') ?>>
                    <?= field_error('requester_email') ?>
                </div>
                <div class="field">
                    <label for="f-phone">Telefone <span class="muted">(opcional)</span></label>
                    <input type="text" id="f-phone" name="requester_phone" value="<?= e(old('requester_phone')) ?>" maxlength="40" autocomplete="tel">
                </div>
            </div>
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="accept" value="1"<?= error_attrs('accept') ?>>
                    <span>Entendo que os dados informados serão usados apenas para atender e responder a este pedido, e que a empresa poderá solicitar a confirmação da minha identidade antes de fornecer dados pessoais.</span>
                </label>
                <?= field_error('accept') ?>
            </div>
            <button type="submit" class="btn btn-primary" data-loading="Enviando...">Enviar solicitação</button>
            <p class="hint section-gap-sm">Você receberá um número de protocolo por e-mail. Prazo de referência para a resposta completa: até 15 dias.</p>
        </form>

        <p class="small muted section-gap">
            Encarregado de dados: <?= e($tenant['dpo_name'] ?? '—') ?><?= ($tenant['privacy_email'] ?: $tenant['dpo_email']) ? ' · ' . e($tenant['privacy_email'] ?: $tenant['dpo_email']) : '' ?>
        </p>
    </div>
</section>
