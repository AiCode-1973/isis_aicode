<?php
use Isis\Core\View;
use Isis\Services\OnboardingService as Wizard;

$company = $answers['empresa'] ?? [];
$data = $answers['dados'] ?? [];
$people = $answers['responsaveis'] ?? [];
$areas = $answers['areas']['departments'] ?? [];
$invites = array_values(array_filter($people['owners'] ?? [], static fn (array $o): bool => $o['invite']));
$icons = [
    'inventario' => 'database', 'equipe' => 'users', 'riscos' => 'target', 'ripd' => 'scale', 'fornecedores' => 'truck',
    'titulares' => 'inbox', 'politicas' => 'file', 'treinamentos' => 'graduation', 'incidentes' => 'siren',
];
$groups = [
    ['Comece por aqui', array_slice($plan, 0, 3)],
    ['Em seguida', array_slice($plan, 3, 3)],
    ['Depois', array_slice($plan, 6)],
];
$n = 0;
?>
<?= View::partial('onboarding/_steps', compact('step', 'tenant', 'answers') + [
    'heading' => $completed ? 'Seu plano de implantação' : 'Seu plano sugerido',
    'lead' => 'Uma ordem de trabalho calculada a partir das suas respostas. É um ponto de partida, não uma avaliação jurídica: ajuste às prioridades da empresa.',
]) ?>

<section class="card wizard-summary" aria-labelledby="sum-title">
    <h2 id="sum-title">Resumo das respostas</h2>
    <dl class="dl">
        <dt>Empresa</dt>
        <dd><?= e($segments[$company['segment_code'] ?? ''] ?? '') ?> · <?= e(Wizard::SIZES[$company['size'] ?? ''] ?? '') ?></dd>
        <dt>Áreas</dt>
        <dd><?= e(implode(', ', $areas)) ?></dd>
        <dt>Dados</dt>
        <dd><?= e(implode(', ', $data['data_categories'] ?? [])) ?></dd>
        <dt>Titulares</dt>
        <dd><?= e(implode(', ', $data['subject_categories'] ?? [])) ?></dd>
        <dt>Encarregado</dt>
        <dd><?= e(($people['dpo_name'] ?? '') . ' · ' . ($people['dpo_email'] ?? '')) ?></dd>
        <?php if ($invites || !empty($people['invite_dpo'])): ?>
        <dt>Convites</dt>
        <dd><?= e(implode(', ', [...(!empty($people['invite_dpo']) ? [$people['dpo_name'] . ' (encarregado)'] : []), ...array_map(static fn (array $o): string => $o['name'] . ' (' . $o['department'] . ')', $invites)])) ?></dd>
        <?php endif; ?>
    </dl>
    <p class="small"><a href="/configuracao-inicial/empresa">Alterar respostas</a></p>
</section>

<?php foreach ($groups as [$label, $items]): if (!$items) { continue; } ?>
<section class="wizard-plan" aria-label="<?= e($label) ?>">
    <h2><?= e($label) ?></h2>
    <ol class="plan-list" start="<?= $n + 1 ?>">
        <?php foreach ($items as $item): $n++; ?>
        <li class="card plan-item">
            <span class="plan-num" aria-hidden="true"><?= $n ?></span>
            <div class="plan-body">
                <p class="plan-module"><?= icon($icons[$item['key']] ?? 'check') ?> <?= e($item['module']) ?></p>
                <h3><?= e($item['title']) ?></h3>
                <ul>
                    <?php foreach ($item['reasons'] as $reason): ?><li><?= e($reason) ?></li><?php endforeach; ?>
                </ul>
            </div>
            <?php if ($completed && !$pending): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e($item['link']) ?>">Abrir <?= icon('arrow-right') ?></a>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ol>
</section>
<?php endforeach; ?>

<?php if (!empty($template['guidance'])): ?>
<div class="alert alert-info section-gap">
    <?= icon('info') ?>
    <div>
        <p><strong>Pontos de atenção para <?= e($segments[$segment] ?? 'o segmento') ?></strong></p>
        <ul class="guidance">
            <?php foreach ($template['guidance'] as $tip): ?><li><?= e($tip) ?></li><?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<?php if ($completed && !$pending): ?>
<div class="wizard-actions section-gap">
    <a class="btn btn-ghost" href="/configuracao-inicial?refazer=1">Refazer o assistente</a>
    <a class="btn btn-primary" href="/painel">Ir para o painel <?= icon('arrow-right') ?></a>
</div>
<?php else: ?>
<form method="post" action="/configuracao-inicial/plano" class="card section-gap" novalidate>
    <?= csrf_field() ?>
    <p class="small muted">Ao concluir, o ÍSIS cria <?= plural(count($areas), 'área', 'áreas') ?>, <?= plural(count($data['data_categories'] ?? []) + count($data['subject_categories'] ?? []), 'categoria', 'categorias') ?> no catálogo do inventário<?= $invites || !empty($people['invite_dpo']) ? ' e envia os convites marcados' : '' ?>. Itens que já existem não são duplicados.</p>
    <div class="field">
        <label class="check">
            <input type="checkbox" name="acknowledge" value="1"<?= error_attrs('acknowledge') ?>>
            <span>Entendo que o ÍSIS apoia a organização da conformidade e que as decisões jurídicas continuam sob responsabilidade da empresa.</span>
        </label>
        <?= field_error('acknowledge') ?>
    </div>
    <?= View::partial('onboarding/_actions', ['step' => $step, 'submit' => $completed ? 'Aplicar as alterações' : 'Concluir e ir para o painel']) ?>
</form>
<?= View::partial('onboarding/_leave', ['completed' => $completed]) ?>
<?php endif; ?>
