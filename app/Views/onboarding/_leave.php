<?php if (!$completed): ?>
<form method="post" action="/sair" class="inline-form">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-link small">Sair e continuar depois</button>
</form>
<p class="hint">As etapas já respondidas ficam salvas.</p>
<?php else: ?>
<p><a class="btn btn-link small" href="/painel">Voltar ao painel sem alterar nada</a></p>
<?php endif; ?>
