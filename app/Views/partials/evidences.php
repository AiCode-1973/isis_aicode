<?php
/** @var string $entityType @var int $entityId @var list<array> $evidences @var bool $canUpload */
?>
<section class="card" id="evidencias" aria-labelledby="ev-title">
    <div class="card-header">
        <div>
            <h2 id="ev-title">Evidências</h2>
            <p>Documentos que comprovam o que foi feito. Cada arquivo guarda o hash SHA-256 para comprovar integridade.</p>
        </div>
    </div>
    <?php if (!$evidences): ?>
    <p class="empty">Nenhuma evidência anexada.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table-stack">
            <thead><tr><th>Arquivo</th><th>Enviado por</th><th>Data</th><th><span class="visually-hidden">Ações</span></th></tr></thead>
            <tbody>
            <?php foreach ($evidences as $ev): ?>
            <tr>
                <td data-label="Arquivo">
                    <a class="title-link" href="/evidencias/<?= (int) $ev['id'] ?>"><?= icon('file') ?> <?= e($ev['original_name']) ?></a>
                    <div class="small muted"><?= e(fmt_bytes((int) $ev['size_bytes'])) ?><?= $ev['description'] ? ' · ' . e($ev['description']) : '' ?></div>
                    <div class="small muted mono" title="SHA-256">SHA-256 <?= e(substr($ev['sha256'], 0, 16)) ?>…</div>
                </td>
                <td data-label="Enviado por"><?= e($ev['uploader_name'] ?? '—') ?></td>
                <td data-label="Data"><?= e(fmt_datetime($ev['created_at'])) ?></td>
                <td class="actions-cell">
                    <?php if ($canUpload): ?>
                    <form method="post" action="/evidencias/<?= (int) $ev['id'] ?>/excluir" class="inline-form" data-confirm="Excluir a evidência &quot;<?= e($ev['original_name']) ?>&quot;?">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger btn-sm" aria-label="Excluir evidência <?= e($ev['original_name']) ?>"><?= icon('trash') ?></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($canUpload): ?>
    <form method="post" action="/evidencias" enctype="multipart/form-data" class="form-grid section-gap" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="entity_type" value="<?= e($entityType) ?>">
        <input type="hidden" name="entity_id" value="<?= (int) $entityId ?>">
        <div class="field">
            <label for="f-file">Arquivo</label>
            <input type="file" id="f-file" name="file" required accept=".pdf,.png,.jpg,.jpeg,.txt,.csv,.docx,.xlsx,.pptx,.odt,.ods">
            <p class="hint">PDF, imagem, texto/CSV ou documento do Office/LibreOffice, até 10 MB.</p>
        </div>
        <div class="field">
            <label for="f-ev-desc">Descrição <span class="muted">(opcional)</span></label>
            <input type="text" id="f-ev-desc" name="description" maxlength="255" placeholder="Ex.: contrato assinado, print da configuração">
        </div>
        <div class="full"><button type="submit" class="btn btn-ghost" data-loading="Enviando..."><?= icon('plus') ?> Anexar evidência</button></div>
    </form>
    <?php endif; ?>
</section>
