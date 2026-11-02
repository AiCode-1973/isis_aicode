<?php
/*
 * Gráfico de barras de uma série (SVG gerado no servidor, sem JavaScript).
 * $series: list<array{label: string, value: int, title: string}>; $caption: nome da série (para a tabela).
 * Barras finas com topo arredondado presas à linha de base; rótulo de valor só no mês atual e no maior;
 * dica ao passar o mouse (<title>) e tabela equivalente para leitores de tela.
 */

$w = 560;
$h = 140;
$padL = 32;
$padB = 26;
$padT = 20;
$plotW = $w - $padL - 4;
$plotH = $h - $padB - $padT;
$n = max(1, count($series));
$values = array_column($series, 'value');
$max = max(1, ...$values);
$step = $max <= 4 ? 1 : (int) ceil($max / 4);
$top = $step * (int) ceil($max / $step);
$slot = $plotW / $n;
$barW = min(28, $slot * .55);
$maxIndex = array_search(max($values), $values, true);
$y = static fn (float $v): float => $padT + $plotH - $v / $top * $plotH;
?>
<figure class="chart">
    <svg viewBox="0 0 <?= $w ?> <?= $h ?>" role="img" aria-label="<?= e($caption) ?>">
        <?php for ($t = 0; $t <= $top; $t += $step): $ty = round($y($t), 1); ?>
        <line x1="<?= $padL ?>" x2="<?= $w - 4 ?>" y1="<?= $ty ?>" y2="<?= $ty ?>" class="chart-grid<?= $t === 0 ? ' chart-base' : '' ?>"/>
        <text x="<?= $padL - 6 ?>" y="<?= $ty + 3.5 ?>" class="chart-axis" text-anchor="end"><?= $t ?></text>
        <?php endfor; ?>
        <?php foreach ($series as $k => $point):
            $cx = $padL + $slot * $k + $slot / 2;
            $x = round($cx - $barW / 2, 1);
            $by = round($y($point['value']), 1);
            $bh = round($padT + $plotH - $by, 1);
            $r = min(4, $bh, $barW / 2);
            $base = $padT + $plotH;
        ?>
        <g class="chart-bar">
            <title><?= e($point['title']) ?></title>
            <rect x="<?= round($cx - $slot / 2, 1) ?>" y="<?= $padT ?>" width="<?= round($slot, 1) ?>" height="<?= $plotH ?>" class="chart-hit"/>
            <?php if ($point['value'] > 0): ?>
            <path d="M<?= $x ?> <?= $base ?>V<?= $by + $r ?>Q<?= $x ?> <?= $by ?> <?= $x + $r ?> <?= $by ?>H<?= round($x + $barW - $r, 1) ?>Q<?= round($x + $barW, 1) ?> <?= $by ?> <?= round($x + $barW, 1) ?> <?= $by + $r ?>V<?= $base ?>Z" class="chart-mark"/>
            <?php endif; ?>
            <?php if ($k === $n - 1 || $k === $maxIndex): ?>
            <text x="<?= round($cx, 1) ?>" y="<?= $by - 5 ?>" class="chart-value" text-anchor="middle"><?= (int) $point['value'] ?></text>
            <?php endif; ?>
            <text x="<?= round($cx, 1) ?>" y="<?= $h - 6 ?>" class="chart-axis" text-anchor="middle"><?= e($point['label']) ?></text>
        </g>
        <?php endforeach; ?>
    </svg>
    <table class="visually-hidden">
        <caption><?= e($caption) ?></caption>
        <tbody><?php foreach ($series as $point): ?><tr><th scope="row"><?= e($point['label']) ?></th><td><?= (int) $point['value'] ?></td></tr><?php endforeach; ?></tbody>
    </table>
</figure>
