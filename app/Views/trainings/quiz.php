<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/treinamentos/<?= (int) $m['id'] ?>"><?= e($m['title']) ?></a></p>
        <h1>Avaliação</h1>
        <p><?= count($questions) ?> pergunta(s). Nota mínima: <?= (int) $m['passing_score'] ?>. Você pode refazer se não atingir a nota.<?= (int) $enrollment['attempts'] > 0 ? ' Tentativas anteriores: ' . (int) $enrollment['attempts'] . '.' : '' ?></p>
    </div>
</div>

<form method="post" action="/treinamentos/<?= (int) $m['id'] ?>/avaliacao" class="card quiz" novalidate>
    <?= csrf_field() ?>
    <?php foreach ($questions as $n => $q): ?>
    <fieldset class="quiz-question">
        <legend><?= $n + 1 ?>. <?= e($q['question']) ?></legend>
        <?php foreach ($q['options'] as $i => $option): ?>
        <label class="chip-check quiz-option"><input type="radio" name="answer[<?= (int) $q['id'] ?>]" value="<?= (int) $i ?>" required> <span><?= e($option) ?></span></label>
        <?php endforeach; ?>
    </fieldset>
    <?php endforeach; ?>
    <div class="actions sticky-actions">
        <button type="submit" class="btn btn-primary" data-loading="Corrigindo...">Enviar respostas</button>
        <a class="btn btn-ghost" href="/treinamentos/<?= (int) $m['id'] ?>">Voltar ao conteúdo</a>
    </div>
</form>
