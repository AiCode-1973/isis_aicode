<?php

use Isis\Repositories\LegalBasisRepository;
use Isis\Repositories\SegmentRepository;
use Isis\Services\RiskMatrix;
use Isis\Support\Labels;
use Isis\Support\ModelLibrary;

$segments = SegmentRepository::options();
$bases = [];
foreach ([...LegalBasisRepository::all(false), ...LegalBasisRepository::all(true)] as $b) {
    $bases[$b['code']] = $b['article'] . ' · ' . $b['name'];
}
$use = static function (array $m) use ($riskId): ?string {
    return match ($m['type']) {
        'inventario' => '/inventario/nova?modelo=' . $m['key'],
        'risco' => '/riscos/novo?modelo=' . $m['key'],
        'plano' => '/planos/modelo/' . $m['key'] . ($riskId ? '?risco=' . $riskId : ''),
        'incidente' => '/incidentes/novo?modelo=' . $m['key'],
        default => null,
    };
};
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Biblioteca</p>
        <h1>Modelos prontos</h1>
        <p>Exemplos editáveis para as situações mais comuns. Escolha um modelo, revise e ajuste: nada é gravado até você salvar. São pontos de partida, não uma avaliação jurídica.</p>
    </div>
</div>

<?php if (!$types): ?>
<section class="card"><p class="empty">Seu perfil não cadastra registros nos módulos com modelos.</p></section>
<?php else: ?>
<nav class="model-tabs" aria-label="Tipos de modelo">
    <?php foreach ($types as $code => [$label, , $iconName]): ?>
    <a href="/modelos?tipo=<?= e($code) ?><?= $riskId ? '&amp;risco=' . (int) $riskId : '' ?>" class="<?= $code === $type ? 'is-active' : '' ?>"<?= $code === $type ? ' aria-current="page"' : '' ?>><?= icon($iconName) ?> <?= e($label) ?> <span class="count"><?= count(ModelLibrary::ofType($code, $code === 'inventario' ? $segment : null)) ?></span></a>
    <?php endforeach; ?>
</nav>

<p class="muted section-gap-sm"><?= e($types[$type][1]) ?><?= $type === 'inventario' ? ' Aparecem os modelos do seu segmento e os que servem para qualquer empresa.' : '' ?></p>
<?php if ($type === 'resposta'): ?>
<div class="alert alert-info section-gap-sm"><?= icon('info') ?><p>Para usar um modelo de resposta, abra uma <a href="/titulares?abertas=1">solicitação em atendimento</a> e escolha o modelo no quadro <strong>Responder ao titular</strong>. O nome do titular, o protocolo, a empresa e o encarregado entram sozinhos; os trechos entre colchetes você completa.</p></div>
<?php endif; ?>

<div class="model-grid section-gap">
    <?php foreach ($models as $m): $href = $use($m); ?>
    <article class="card model-card">
        <header>
            <h2><?= e($m['title']) ?></h2>
            <?php if ($m['segments']): ?>
            <p class="model-segments"><?php foreach ($m['segments'] as $s): ?><span class="badge badge-teal"><?= e($segments[$s] ?? $s) ?></span> <?php endforeach; ?></p>
            <?php endif; ?>
        </header>
        <p class="model-situation"><strong>Quando usar:</strong> <?= e($m['situation']) ?></p>

        <details class="model-preview">
            <summary>Ver o conteúdo</summary>
            <?php if ($m['type'] === 'inventario'): $f = $m['fields']; ?>
            <dl class="dl">
                <dt>Finalidade</dt><dd><?= e($f['purpose']) ?></dd>
                <dt>Hipótese legal</dt><dd><?= e($bases[$f['legal_basis_code']] ?? '—') ?><?= !empty($f['sensitive_legal_basis_code']) ? '; dados sensíveis: ' . e($bases[$f['sensitive_legal_basis_code']] ?? '') : '' ?></dd>
                <dt>Dados</dt><dd><?= e(implode(', ', $m['data'] ?? [])) ?></dd>
                <dt>Titulares</dt><dd><?= e(implode(', ', $m['subjects'] ?? [])) ?></dd>
                <dt>Prazo de guarda</dt><dd><?= e($f['retention_period'] ?? '—') ?></dd>
            </dl>
            <?php elseif ($m['type'] === 'risco'): $f = $m['fields']; $score = (int) $f['likelihood'] * (int) $f['impact']; ?>
            <dl class="dl">
                <dt>Risco</dt><dd><?= e($f['title']) ?></dd>
                <dt>Causa</dt><dd><?= e($f['cause']) ?></dd>
                <dt>Consequência</dt><dd><?= e($f['consequence']) ?></dd>
                <dt>Nível sugerido</dt><dd><span class="badge <?= e(RiskMatrix::badge(RiskMatrix::level($score))) ?>"><?= e(RiskMatrix::label(RiskMatrix::level($score))) ?></span> probabilidade <?= (int) $f['likelihood'] ?> × impacto <?= (int) $f['impact'] ?></dd>
                <dt>Tratamento</dt><dd><?= e(label(Labels::RISK_TREATMENT, $f['treatment'])) ?>: <?= e($f['treatment_notes']) ?></dd>
                <?php if (!empty($m['plan']) && ($p = ModelLibrary::find('plano', $m['plan']))): ?><dt>Plano sugerido</dt><dd><?= e($p['title']) ?></dd><?php endif; ?>
            </dl>
            <?php elseif ($m['type'] === 'plano'): ?>
            <ol class="model-actions">
                <?php foreach ($m['actions'] as [$title, $desc, $priority, $days]): ?>
                <li><strong><?= e($title) ?></strong> <span class="small muted">· prioridade <?= e(mb_strtolower(Labels::PRIORITY[$priority])) ?> · prazo de <?= (int) $days ?> dias</span><?= $desc ? '<div class="small muted">' . e($desc) . '</div>' : '' ?></li>
                <?php endforeach; ?>
            </ol>
            <?php elseif ($m['type'] === 'resposta'): ?>
            <p class="small muted">Resultado: <?= $m['final_status'] === 'indeferida' ? 'indeferida' : 'atendida' ?><?= !empty($m['rights']) ? ' · sugerido para: ' . e(implode(', ', array_map(static fn (string $r): string => mb_strtolower((string) preg_replace('/\s*\(.*$/u', '', Labels::RIGHT_TYPE[$r] ?? $r)), $m['rights']))) : '' ?></p>
            <pre class="model-text"><?= e($m['text']) ?></pre>
            <?php else: ?>
            <dl class="dl">
                <dt>Tipo</dt><dd><?= e(label(Labels::INCIDENT_CATEGORY, $m['fields']['category'])) ?></dd>
                <dt>Título</dt><dd><?= e($m['fields']['title']) ?></dd>
            </dl>
            <pre class="model-text"><?= e($m['fields']['description']) ?></pre>
            <?php endif; ?>
        </details>

        <?php if ($href): ?>
        <p class="model-use"><a class="btn btn-primary btn-sm" href="<?= e($href) ?>">Usar este modelo <?= icon('arrow-right') ?></a></p>
        <?php endif; ?>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>
