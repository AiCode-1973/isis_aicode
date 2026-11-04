<?php
use Isis\Core\View;

$saved = $saved ?? [];
$areas = $answers['areas']['departments'] ?? [];
$byArea = [];
foreach ($saved['owners'] ?? [] as $owner) {
    $byArea[$owner['department']] = $owner;
}
$savedNames = $savedEmails = $savedInvites = [];
foreach ($areas as $i => $area) {
    $savedNames[$i] = $byArea[$area]['name'] ?? '';
    $savedEmails[$i] = $byArea[$area]['email'] ?? '';
    $savedInvites[$i] = !empty($byArea[$area]['invite']) ? '1' : '';
}
$names = old_list('owner_name', $savedNames);
$emails = old_list('owner_email', $savedEmails);
$invites = old_list('owner_invite', has_old() ? [] : $savedInvites);
$inviteDpo = has_old() ? old('invite_dpo') === '1' : !empty($saved['invite_dpo']);
?>
<?= View::partial('onboarding/_steps', compact('step', 'tenant', 'answers') + [
    'heading' => 'Quem cuida da proteção de dados?',
    'lead' => 'Defina o encarregado e, se quiser, quem responde por cada área. Os convites só são enviados ao concluir, e só para quem você marcar.',
]) ?>

<form method="post" action="/configuracao-inicial/responsaveis" class="card" novalidate>
    <?= csrf_field() ?>
    <h2>Encarregado e canal de privacidade</h2>
    <p class="muted small">O encarregado (DPO) é o canal de comunicação entre a empresa, os titulares e a ANPD (art. 41). Pode ser você, outra pessoa da equipe ou um serviço externo.</p>
    <div class="form-grid">
        <div class="field">
            <label for="f-dpo_name">Nome do encarregado</label>
            <input type="text" id="f-dpo_name" name="dpo_name" value="<?= e(old('dpo_name', $saved['dpo_name'] ?? $tenant['dpo_name'] ?? $user['name'])) ?>" required maxlength="150"<?= error_attrs('dpo_name') ?>>
            <?= field_error('dpo_name') ?>
        </div>
        <div class="field">
            <label for="f-dpo_email">E-mail do encarregado</label>
            <input type="email" id="f-dpo_email" name="dpo_email" value="<?= e(old('dpo_email', $saved['dpo_email'] ?? $tenant['dpo_email'] ?? $user['email'])) ?>" required maxlength="190"<?= error_attrs('dpo_email') ?>>
            <?= field_error('dpo_email') ?>
        </div>
        <div class="field">
            <label for="f-privacy_email">E-mail público do canal de privacidade <span class="muted">(opcional)</span></label>
            <input type="email" id="f-privacy_email" name="privacy_email" value="<?= e(old('privacy_email', $saved['privacy_email'] ?? $tenant['privacy_email'] ?? '')) ?>" maxlength="190" placeholder="privacidade@suaempresa.com.br"<?= error_attrs('privacy_email') ?>>
            <p class="hint">Se ficar em branco, usamos o e-mail do encarregado.</p>
            <?= field_error('privacy_email') ?>
        </div>
        <div class="field field-end">
            <label class="check"><input type="checkbox" name="invite_dpo" value="1"<?= $inviteDpo ? ' checked' : '' ?>> <span>Convidar o encarregado para o ÍSIS com o perfil Encarregado (se não for você)</span></label>
        </div>
    </div>

    <?php if ($areas): ?>
    <hr class="divider">
    <h2>Responsável por área <span class="muted small">(opcional)</span></h2>
    <p class="muted small">Quem conhece o dia a dia de cada área ajuda a mapear as operações. Convidados entram com o perfil Gestor, já ligados à área.</p>
    <div class="owners">
        <div class="owners-head" aria-hidden="true"><span>Área</span><span>Nome</span><span>E-mail</span><span>Convidar</span></div>
        <?php foreach ($areas as $i => $area): ?>
        <div class="owners-row">
            <span class="owners-area"><?= e($area) ?></span>
            <div>
                <label class="visually-hidden" for="o-name-<?= $i ?>">Nome do responsável por <?= e($area) ?></label>
                <input type="text" id="o-name-<?= $i ?>" name="owner_name[<?= $i ?>]" value="<?= e($names[$i] ?? '') ?>" maxlength="150" placeholder="Nome"<?= error_attrs('owner_name_' . $i) ?>>
                <?= field_error('owner_name_' . $i) ?>
            </div>
            <div>
                <label class="visually-hidden" for="o-email-<?= $i ?>">E-mail do responsável por <?= e($area) ?></label>
                <input type="email" id="o-email-<?= $i ?>" name="owner_email[<?= $i ?>]" value="<?= e($emails[$i] ?? '') ?>" maxlength="190" placeholder="email@empresa.com.br"<?= error_attrs('owner_email_' . $i) ?>>
                <?= field_error('owner_email_' . $i) ?>
            </div>
            <label class="check owners-invite"><input type="checkbox" name="owner_invite[<?= $i ?>]" value="1"<?= ($invites[$i] ?? '') === '1' ? ' checked' : '' ?>> <span>Convidar</span></label>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?= View::partial('onboarding/_actions', ['step' => $step, 'submit' => 'Ver o plano sugerido']) ?>
</form>

<?= View::partial('onboarding/_leave', ['completed' => $completed]) ?>
