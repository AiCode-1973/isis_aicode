<?php

use Isis\Support\Labels;

$statusBadge = ['pendente' => 'badge-warn', 'em_andamento' => 'badge-brand', 'concluido' => 'badge-ok', 'reprovado' => 'badge-danger'];
$done = count(array_filter($enrollments, static fn (array $e): bool => $e['status'] === 'concluido'));
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/treinamentos">Treinamentos</a> · <?= e(code('TRE', (int) $m['seq'])) ?></p>
        <h1><?= e($m['title']) ?></h1>
        <p><?= (int) $m['duration_minutes'] ?> min · <?= (int) $questionsCount ?> pergunta(s) · nota mínima <?= (int) $m['passing_score'] ?> · certificado válido por <?= (int) $m['certificate_months'] ?> meses
            <?php if ($manager): ?> · <span class="badge <?= $m['status'] === 'publicado' ? 'badge-ok' : '' ?>"><?= e(Labels::TRAINING_STATUS[$m['status']]) ?></span><?php endif; ?></p>
    </div>
    <?php if ($manager): ?>
    <div class="actions">
        <a class="btn btn-ghost" href="/treinamentos/<?= (int) $m['id'] ?>/editar">Editar</a>
        <form method="post" action="/treinamentos/<?= (int) $m['id'] ?>/situacao" class="inline-form">
            <?= csrf_field() ?>
            <?php if ($m['status'] === 'publicado'): ?><input type="hidden" name="status" value="arquivado"><button type="submit" class="btn btn-danger">Arquivar</button>
            <?php else: ?><input type="hidden" name="status" value="publicado"><button type="submit" class="btn btn-teal">Publicar</button><?php endif; ?>
        </form>
    </div>
    <?php endif; ?>
</div>

<div class="grid grid-main">
    <div class="stack">
        <section class="card">
            <h2>Conteúdo</h2>
            <?php if ($m['summary']): ?><p class="muted section-gap-sm"><?= e($m['summary']) ?></p><?php endif; ?>
            <div class="policy-text section-gap-sm"><?= e($m['content']) ?></div>
        </section>

        <?php if ($manager): ?>
        <section class="card" id="participantes">
            <h2>Participantes</h2>
            <p class="small muted"><?= $done ?> de <?= count($enrollments) ?> concluíram.</p>
            <?php if ($enrollments): ?>
            <div class="table-wrap section-gap-sm">
                <table class="table-stack">
                    <thead><tr><th>Pessoa</th><th>Prazo</th><th>Tentativas</th><th>Nota</th><th>Situação</th></tr></thead>
                    <tbody>
                    <?php foreach ($enrollments as $en): ?>
                    <tr class="<?= $en['overdue'] ? 'row-overdue' : '' ?>">
                        <td data-label="Pessoa"><?= e($en['user_name']) ?><div class="small muted"><?= e($en['department_name'] ?? '') ?></div></td>
                        <td data-label="Prazo" class="<?= $en['overdue'] ? 'text-danger' : '' ?>"><?= e(fmt_date($en['due_date'])) ?></td>
                        <td data-label="Tentativas"><?= (int) $en['attempts'] ?></td>
                        <td data-label="Nota"><?= $en['best_score'] !== null ? (int) $en['best_score'] : '—' ?></td>
                        <td data-label="Situação"><span class="badge <?= $statusBadge[$en['status']] ?>"><?= e(Labels::ENROLLMENT_STATUS[$en['status']]) ?></span><?= $en['certificate_code'] ? ' <a class="small" href="/treinamentos/certificado/' . e($en['certificate_code']) . '">certificado</a>' : '' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>

    <div class="stack">
        <?php if ($m['status'] === 'publicado'): ?>
        <section class="card">
            <h2>Sua participação</h2>
            <?php if ($enrollment && $enrollment['certificate_code']): ?>
            <p class="section-gap-sm"><span class="badge badge-ok">Concluído</span> com nota <?= (int) $enrollment['best_score'] ?> em <?= e(fmt_date($enrollment['completed_at'])) ?>.</p>
            <a class="btn btn-teal btn-block section-gap-sm" href="/treinamentos/certificado/<?= e($enrollment['certificate_code']) ?>"><?= icon('graduation') ?> Ver certificado</a>
            <?php else: ?>
            <p class="small muted section-gap-sm"><?= $enrollment ? e(Labels::ENROLLMENT_STATUS[$enrollment['status']]) . ($enrollment['due_date'] ? ' · prazo ' . e(fmt_date($enrollment['due_date'])) : '') : 'Você ainda não iniciou este treinamento.' ?></p>
            <p class="small section-gap-sm">Leia o conteúdo ao lado e depois faça a avaliação. É preciso acertar pelo menos <?= (int) $m['passing_score'] ?>% para receber o certificado.</p>
            <a class="btn btn-primary btn-block section-gap-sm" href="/treinamentos/<?= (int) $m['id'] ?>/avaliacao">Fazer a avaliação</a>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($manager && $m['status'] === 'publicado'): ?>
        <section class="card" id="designar">
            <h2>Designar participantes</h2>
            <form method="post" action="/treinamentos/<?= (int) $m['id'] ?>/designar" class="section-gap-sm" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label class="check"><input type="radio" name="target" value="todos" checked> <span>Todos os usuários ativos</span></label>
                    <label class="check"><input type="radio" name="target" value="setores"> <span>Setores selecionados</span></label>
                    <label class="check"><input type="radio" name="target" value="pessoas"> <span>Pessoas selecionadas</span></label>
                </div>
                <details class="changes"><summary>Setores</summary>
                    <div class="check-grid section-gap-sm"><?php foreach ($departments as $id => $name): ?><label class="check"><input type="checkbox" name="departments[]" value="<?= (int) $id ?>"> <span><?= e($name) ?></span></label><?php endforeach; ?></div>
                </details>
                <details class="changes section-gap-sm"><summary>Pessoas</summary>
                    <div class="check-grid section-gap-sm"><?php foreach ($users as $u): ?><label class="check"><input type="checkbox" name="users[]" value="<?= (int) $u['id'] ?>"> <span><?= e($u['name']) ?></span></label><?php endforeach; ?></div>
                </details>
                <div class="field section-gap-sm"><label for="d-due">Prazo para concluir</label><input type="date" id="d-due" name="due_date" value="<?= e(date('Y-m-d', strtotime('+30 days'))) ?>"></div>
                <button type="submit" class="btn btn-primary btn-block">Designar</button>
            </form>
        </section>
        <?php elseif ($manager): ?>
        <div class="alert alert-info"><?= icon('info') ?><p>Publique o treinamento para designar participantes. Antes, confira o conteúdo e as perguntas.</p></div>
        <?php endif; ?>
    </div>
</div>
