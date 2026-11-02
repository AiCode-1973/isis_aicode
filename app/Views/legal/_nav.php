<?php
use Isis\Support\LegalDocs;

$docs = [
    '/termos' => 'Termos de uso',
    '/privacidade' => 'Aviso de privacidade',
    '/acordo-de-tratamento' => 'Acordo de tratamento de dados',
];
?>
<p class="eyebrow">ÍSIS · Documentos legais</p>
<h1><?= e($docs[$current]) ?></h1>
<p class="muted">Versão de <?= e(LegalDocs::VERSION) ?>. Modelo preparado para a operação inicial, que deve ser revisado pela assessoria jurídica da AiCode antes da venda.</p>
<nav class="legal-nav" aria-label="Documentos legais">
    <?php foreach ($docs as $href => $label): ?>
    <a href="<?= e($href) ?>"<?= $href === $current ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
