<?php
/* Anel do índice de maturidade (SVG). $score: 0 a 100. */
$radius = 52;
$circ = 2 * M_PI * $radius;
$filled = round($circ * $score / 100, 2);
?>
<svg class="ring" viewBox="0 0 128 128" role="img" aria-label="Índice de maturidade: <?= (int) $score ?> de 100">
    <circle cx="64" cy="64" r="<?= $radius ?>" class="ring-track"/>
    <circle cx="64" cy="64" r="<?= $radius ?>" class="ring-value" stroke-dasharray="<?= $filled ?> <?= round($circ, 2) ?>" transform="rotate(-90 64 64)"/>
    <text x="64" y="66" text-anchor="middle" class="ring-number"><?= (int) $score ?></text>
    <text x="64" y="86" text-anchor="middle" class="ring-unit">de 100</text>
</svg>
