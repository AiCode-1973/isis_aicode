<?php
$isNew = $m['id'] === null;
$val = static fn (string $key): string => old($key, (string) ($m[$key] ?? ''));
$oldTexts = old_list('q_text', array_column($questions, 'question'));
$oldCorrect = old_list('q_correct', array_map(static fn (array $q): string => (string) $q['correct_index'], $questions));
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/treinamentos">Treinamentos</a><?= $isNew ? '' : ' · ' . e(code('TRE', (int) $m['seq'])) ?></p>
        <h1><?= $isNew ? 'Novo treinamento' : e($m['title']) ?></h1>
        <p>Conteúdo em texto e avaliação de múltipla escolha. Até <?= (int) $slots ?> perguntas; deixe em branco as que não usar.</p>
    </div>
</div>

<form method="post" action="<?= $isNew ? '/treinamentos' : '/treinamentos/' . (int) $m['id'] ?>" class="card" novalidate>
    <?= csrf_field() ?>
    <section class="form-section">
        <h2>Conteúdo</h2>
        <div class="form-grid">
            <div class="field full"><label for="f-title">Título</label><input type="text" id="f-title" name="title" value="<?= e($val('title')) ?>" required maxlength="190"<?= error_attrs('title') ?>><?= field_error('title') ?></div>
            <div class="field full"><label for="f-sum">Resumo</label><input type="text" id="f-sum" name="summary" value="<?= e($val('summary')) ?>" maxlength="400"></div>
            <div class="field full">
                <label for="f-content">Conteúdo do treinamento</label>
                <textarea id="f-content" name="content" rows="16" class="policy-editor" maxlength="100000"<?= error_attrs('content') ?>><?= e($val('content')) ?></textarea>
                <p class="hint">Texto simples; as quebras de linha são mantidas. Vídeos ou apostilas podem ser indicados por link no texto.</p>
                <?= field_error('content') ?>
            </div>
        </div>
        <div class="form-grid-3 section-gap-sm">
            <div class="field"><label for="f-dur">Duração estimada (minutos)</label><input type="text" inputmode="numeric" id="f-dur" name="duration_minutes" value="<?= e($val('duration_minutes')) ?>" maxlength="4"<?= error_attrs('duration_minutes') ?>><?= field_error('duration_minutes') ?></div>
            <div class="field"><label for="f-pass">Nota mínima para aprovação (0-100)</label><input type="text" inputmode="numeric" id="f-pass" name="passing_score" value="<?= e($val('passing_score')) ?>" maxlength="3"<?= error_attrs('passing_score') ?>><?= field_error('passing_score') ?></div>
            <div class="field">
                <label for="f-cert">Validade do certificado</label>
                <select id="f-cert" name="certificate_months"><?php foreach ([6, 12, 24, 36] as $mo): ?><option value="<?= $mo ?>"<?= $val('certificate_months') === (string) $mo ? ' selected' : '' ?>><?= $mo ?> meses</option><?php endforeach; ?></select>
            </div>
        </div>
    </section>

    <section class="form-section">
        <h2>Avaliação</h2>
        <?= field_error('q_text') ?>
        <?php for ($i = 0; $i < $slots; $i++):
            $q = $questions[$i] ?? null;
            $text = $oldTexts[$i] ?? '';
            $opts = has_old() ? (array) (Isis\Core\Session::getFlash('_old')['q_option'][$i] ?? []) : ($q['options'] ?? []);
            $correct = $oldCorrect[$i] ?? '0';
        ?>
        <details class="question-block"<?= $text !== '' || $i === 0 ? ' open' : '' ?>>
            <summary>Pergunta <?= $i + 1 ?><?= $text !== '' ? ': ' . e(mb_strimwidth($text, 0, 70, '…')) : ' (vazia)' ?></summary>
            <div class="field section-gap-sm"><label for="q-<?= $i ?>">Enunciado</label><input type="text" id="q-<?= $i ?>" name="q_text[<?= $i ?>]" value="<?= e($text) ?>" maxlength="500"></div>
            <div class="options-grid">
                <?php for ($o = 0; $o < 4; $o++): ?>
                <label class="option-row">
                    <input type="radio" name="q_correct[<?= $i ?>]" value="<?= $o ?>"<?= (string) $correct === (string) $o ? ' checked' : '' ?> aria-label="Marcar alternativa <?= $o + 1 ?> como correta">
                    <input type="text" name="q_option[<?= $i ?>][<?= $o ?>]" value="<?= e((string) ($opts[$o] ?? '')) ?>" maxlength="300" placeholder="Alternativa <?= $o + 1 ?>">
                </label>
                <?php endfor; ?>
            </div>
            <p class="hint">Marque o círculo da alternativa correta.</p>
        </details>
        <?php endfor; ?>
    </section>

    <div class="actions sticky-actions">
        <button type="submit" class="btn btn-primary" data-loading="Salvando..."><?= $isNew ? 'Criar treinamento' : 'Salvar alterações' ?></button>
        <a class="btn btn-ghost" href="<?= $isNew ? '/treinamentos' : '/treinamentos/' . (int) $m['id'] ?>">Cancelar</a>
    </div>
</form>
