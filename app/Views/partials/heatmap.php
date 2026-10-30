<?php

use Isis\Services\RiskMatrix;
use Isis\Support\Labels;

/** @var array<int, array<int, int>> $heatmap @var array{0:int,1:int}|null $selected */
$selected = $selected ?? null;
?>
<div class="heatmap" role="table" aria-label="Matriz de riscos abertos: probabilidade por impacto">
    <?php foreach ([5, 4, 3, 2, 1] as $l): ?>
    <div class="axis axis-y" role="rowheader"><?= e(Labels::LIKELIHOOD[$l]) ?></div>
    <?php foreach ([1, 2, 3, 4, 5] as $i):
        $n = $heatmap[$l][$i] ?? 0;
        $level = RiskMatrix::levelOf($l, $i);
        $isSel = $selected === [$l, $i];
    ?>
    <a role="cell" class="heat heat-<?= $level ?><?= $n === 0 ? ' is-empty' : '' ?><?= $isSel ? ' is-selected' : '' ?>"
       href="/riscos?celula=<?= $l ?>-<?= $i ?>&amp;abertos=1"
       aria-label="Probabilidade <?= e(Labels::LIKELIHOOD[$l]) ?>, impacto <?= e(Labels::IMPACT[$i]) ?>: <?= $n ?> risco(s) aberto(s), nível <?= e(RiskMatrix::label($level)) ?>"><?= $n ?></a>
    <?php endforeach; ?>
    <?php endforeach; ?>
    <div></div>
    <?php foreach ([1, 2, 3, 4, 5] as $i): ?><div class="axis" title="<?= e(Labels::IMPACT[$i]) ?>"><?= $i ?></div><?php endforeach; ?>
</div>
<p class="heat-caption">Linhas: probabilidade · Colunas: impacto (1 insignificante a 5 gravíssimo) · Números: riscos abertos</p>
<div class="heat-legend">
    <span><i class="heat-baixo"></i> Baixo (1–4)</span>
    <span><i class="heat-medio"></i> Médio (5–9)</span>
    <span><i class="heat-alto"></i> Alto (10–15)</span>
    <span><i class="heat-critico"></i> Crítico (16–25)</span>
</div>
