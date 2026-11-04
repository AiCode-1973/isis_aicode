<?php
/*
 * Ajuda da tela atual (botão no topo + painel lateral). O conteúdo vem de Isis\Support\Help.
 * Os itens [data-help-field] também servem ao app.js, que marca "obrigatório"/"opcional" e põe o "?" em cada campo do formulário.
 */
$help = Isis\Support\Help::forPath((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if ($help === null) {
    return;
}
$required = array_filter($help['fields'], static fn (array $f): bool => $f[1]);
?>
<details class="help" data-help>
    <summary class="btn btn-ghost btn-sm help-btn" title="Ajuda desta tela"><?= icon('help') ?><span class="help-btn-label">Ajuda</span></summary>
    <div class="help-drawer" role="region" aria-labelledby="help-title">
        <div class="help-head">
            <div>
                <p class="eyebrow">Ajuda desta tela</p>
                <h2 id="help-title"><?= e($help['title']) ?></h2>
            </div>
            <button type="button" class="icon-btn" data-help-close aria-label="Fechar a ajuda"><?= icon('close') ?></button>
        </div>
        <p><?= e($help['intro']) ?></p>

        <?php if ($help['steps']): ?>
        <h3>Como usar</h3>
        <ol class="help-steps">
            <?php foreach ($help['steps'] as $step): ?><li><?= e($step) ?></li><?php endforeach; ?>
        </ol>
        <?php endif; ?>

        <?php if ($help['fields']): ?>
        <h3>Campos</h3>
        <p class="small muted"><?= $required
            ? 'Os campos <span class="field-tag req">obrigatório</span> precisam ser preenchidos para salvar. Os demais podem ficar em branco e ser completados depois.'
            : 'Nenhum campo é obrigatório: preencha o que souber.' ?></p>
        <dl class="help-fields">
            <?php foreach ($help['fields'] as $name => [$label, $isRequired, $what, $example]): ?>
            <div data-help-field="<?= e($name) ?>" data-help-required="<?= $isRequired ? '1' : '0' ?>">
                <dt><span class="help-label"><?= e($label) ?></span> <span class="field-tag<?= $isRequired ? ' req' : '' ?>"><?= $isRequired ? 'obrigatório' : 'opcional' ?></span></dt>
                <dd>
                    <?php if ($what): ?><span class="help-what"><?= e($what) ?></span><?php endif; ?>
                    <?php if ($example): ?><span class="help-example"><strong>Exemplo:</strong> <?= e($example) ?></span><?php endif; ?>
                </dd>
            </div>
            <?php endforeach; ?>
        </dl>
        <?php endif; ?>

        <?php if ($help['manual']): ?>
        <p class="help-more"><a href="/manual#<?= e($help['manual']) ?>" target="_blank" rel="noopener"><?= icon('info') ?> Ver mais no manual</a></p>
        <?php endif; ?>
    </div>
</details>
