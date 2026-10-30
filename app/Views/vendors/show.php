<?php

use Isis\Services\VendorQuestionnaire as Q;
use Isis\Support\Labels;

$levelBadge = ['alto' => 'badge-danger', 'medio' => 'badge-warn', 'baixo' => 'badge-ok'];
$last = $v['assessments'][0] ?? null;
$hasDpa = (int) $v['dpa_count'] > 0;
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="/fornecedores">Fornecedores</a> · <?= e(code('FOR', (int) $v['seq'])) ?></p>
        <h1><?= e($v['name']) ?></h1>
        <p>
            <span class="badge"><?= e(label(Labels::VENDOR_STATUS, $v['status'])) ?></span>
            <?= $v['risk_level'] ? '<span class="badge ' . $levelBadge[$v['risk_level']] . '">Risco ' . e(mb_strtolower(Labels::LEVEL[$v['risk_level']])) . ' · nota ' . (int) $v['last_score'] . '</span>' : '<span class="badge badge-warn">não avaliado</span>' ?>
            · <?= e($v['service'] ?? '') ?>
        </p>
    </div>
    <?php if ($canEdit): ?><a class="btn btn-primary" href="/fornecedores/<?= (int) $v['id'] ?>/editar">Editar</a><?php endif; ?>
</div>

<?php if ($v['data_access'] && !$hasDpa): ?>
<div class="alert alert-warn"><?= icon('alert') ?><p>Este fornecedor trata dados pessoais e ainda não tem contrato registrado com cláusulas de proteção de dados.</p></div>
<?php endif; ?>

<div class="grid grid-main">
    <div class="stack">
        <section class="card">
            <h2>Dados e papel</h2>
            <div class="detail-grid section-gap-sm">
                <div class="detail"><span class="label">Papel</span><div class="value"><?= e(label(Labels::VENDOR_ROLE, $v['role'])) ?></div></div>
                <div class="detail"><span class="label">CNPJ</span><div class="value"><?= $v['cnpj'] ? e(fmt_cnpj($v['cnpj'])) : '—' ?></div></div>
                <div class="detail"><span class="label">Dados pessoais</span><div class="value"><?= $v['data_access'] ? 'Trata ou acessa' : 'Não trata' ?><?= $v['sensitive_data'] ? ' · <span class="text-danger">inclui sensíveis</span>' : '' ?></div></div>
                <div class="detail"><span class="label">Local</span><div class="value"><?= $v['international'] ? 'Exterior' . ($v['country'] ? ' · ' . e($v['country']) : '') : 'Brasil' ?></div></div>
                <div class="detail full">
                    <span class="label">Operações de tratamento</span>
                    <div class="chips"><?php foreach ($v['activities'] as $a): ?><a class="chip" href="/inventario/<?= (int) $a['id'] ?>"><?= e(code('OP', (int) $a['seq']) . ' · ' . $a['name']) ?></a><?php endforeach; ?><?= $v['activities'] ? '' : '<span class="muted">—</span>' ?></div>
                </div>
                <?php if ($v['notes']): ?><div class="detail full"><span class="label">Observações</span><div class="value"><?= e($v['notes']) ?></div></div><?php endif; ?>
            </div>
        </section>

        <section class="card" id="contratos">
            <h2>Contratos</h2>
            <?php if (!$v['contracts']): ?><p class="empty">Nenhum contrato registrado.</p><?php else: ?>
            <div class="table-wrap section-gap-sm">
                <table class="table-stack">
                    <thead><tr><th>Contrato</th><th>Vigência</th><th>Cláusulas de dados</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($v['contracts'] as $c): $expired = $c['end_date'] && $c['end_date'] < date('Y-m-d'); $soon = $c['end_date'] && !$expired && $c['end_date'] <= date('Y-m-d', strtotime('+30 days')); ?>
                    <tr>
                        <td data-label="Contrato"><strong><?= e($c['title']) ?></strong><?= $c['reference'] ? '<div class="small muted">' . e($c['reference']) . '</div>' : '' ?><?= $c['notes'] ? '<div class="small muted">' . e($c['notes']) . '</div>' : '' ?></td>
                        <td data-label="Vigência" class="<?= $expired ? 'text-danger' : ($soon ? 'text-warn' : '') ?>"><?= e(fmt_date($c['start_date'])) ?> a <?= e(fmt_date($c['end_date'])) ?><?= $expired ? ' · vencido' : ($soon ? ' · vence em breve' : '') ?></td>
                        <td data-label="Cláusulas de dados"><?= $c['has_data_clauses'] ? '<span class="badge badge-ok">sim</span>' : '<span class="badge badge-danger">não</span>' ?></td>
                        <?php if ($canEdit): ?>
                        <td class="actions-cell">
                            <form method="post" action="/fornecedores/<?= (int) $v['id'] ?>/contratos/<?= (int) $c['id'] ?>/excluir" class="inline-form" data-confirm="Remover o registro deste contrato?">
                                <?= csrf_field() ?><button type="submit" class="btn btn-danger btn-sm" aria-label="Remover contrato"><?= icon('trash') ?></button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <?php if ($canEdit): ?>
            <form method="post" action="/fornecedores/<?= (int) $v['id'] ?>/contratos" class="form-grid section-gap" novalidate>
                <?= csrf_field() ?>
                <div class="field"><label for="c-title">Título</label><input type="text" id="c-title" name="title" maxlength="190" required placeholder="Ex.: Contrato de prestação de serviços + aditivo LGPD"<?= error_attrs('title') ?>><?= field_error('title') ?></div>
                <div class="field"><label for="c-ref">Número ou referência</label><input type="text" id="c-ref" name="reference" maxlength="80"></div>
                <div class="field"><label for="c-start">Início</label><input type="date" id="c-start" name="start_date"></div>
                <div class="field"><label for="c-end">Fim</label><input type="date" id="c-end" name="end_date"<?= error_attrs('end_date') ?>><?= field_error('end_date') ?></div>
                <div class="field full"><label class="check"><input type="checkbox" name="has_data_clauses" value="1"> <span>Tem cláusulas de proteção de dados (ou acordo de tratamento de dados)</span></label></div>
                <div class="full"><button type="submit" class="btn btn-ghost"><?= icon('plus') ?> Registrar contrato</button></div>
            </form>
            <?php endif; ?>
        </section>

        <?php if ($canEdit): ?>
        <section class="card" id="avaliacao">
            <h2>Nova avaliação de proteção de dados</h2>
            <p class="small muted">Responda com base em contrato, questionário enviado ao fornecedor, certificações ou documentos recebidos. Anexe as comprovações em Evidências.</p>
            <form method="post" action="/fornecedores/<?= (int) $v['id'] ?>/avaliacao" class="section-gap-sm" novalidate>
                <?= csrf_field() ?>
                <div class="table-wrap">
                    <table class="questionnaire">
                        <thead><tr><th>Pergunta</th><?php foreach (Q::ANSWERS as $label): ?><th class="text-center"><?= e($label) ?></th><?php endforeach; ?></tr></thead>
                        <tbody>
                        <?php foreach (Q::QUESTIONS as $key => [$question, $weight]): $current = old('q_' . $key, $last['answers'][$key] ?? ''); ?>
                        <tr>
                            <td><?= e($question) ?><?= $weight >= 3 ? ' <span class="badge badge-sm">peso alto</span>' : '' ?></td>
                            <?php foreach (Q::ANSWERS as $answer => $label): ?>
                            <td class="text-center"><input type="radio" name="q_<?= e($key) ?>" value="<?= e($answer) ?>" aria-label="<?= e($label) ?>"<?= $current === $answer ? ' checked' : '' ?>></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="field section-gap-sm"><label for="a-notes">Observações da avaliação</label><textarea id="a-notes" name="notes" maxlength="3000"></textarea></div>
                <button type="submit" class="btn btn-primary" data-loading="Calculando...">Registrar avaliação</button>
            </form>
        </section>
        <?php endif; ?>

        <?= Isis\Core\View::partial('partials/evidences', ['entityType' => 'vendor', 'entityId' => (int) $v['id'], 'evidences' => $evidences, 'canUpload' => $canEdit]) ?>
    </div>

    <div class="stack">
        <section class="card">
            <h2>Avaliação</h2>
            <?php if (!$last): ?>
            <p class="muted section-gap-sm">Ainda não avaliado.</p>
            <?php else: ?>
            <p class="score-big section-gap-sm"><span class="badge <?= $levelBadge[$last['risk_level']] ?>">Risco <?= e(mb_strtolower(Labels::LEVEL[$last['risk_level']])) ?></span> <strong><?= (int) $last['score'] ?></strong><span class="muted">/100</span></p>
            <div class="bar"><span class="<?= width_class((int) $last['score']) ?>"></span></div>
            <dl class="dl section-gap">
                <dt>Avaliado em</dt><dd><?= e(fmt_datetime($last['assessed_at'])) ?></dd>
                <dt>Por</dt><dd><?= e($last['assessed_by_name'] ?? '—') ?></dd>
                <dt>Próxima revisão</dt><dd class="<?= $v['next_review_date'] && $v['next_review_date'] < date('Y-m-d') ? 'text-danger' : '' ?>"><?= e(fmt_date($v['next_review_date'])) ?></dd>
            </dl>
            <?php if (count($v['assessments']) > 1): ?>
            <p class="small muted section-gap-sm">Histórico: <?php foreach (array_slice($v['assessments'], 1, 5) as $a): ?><?= e(fmt_date($a['assessed_at'])) ?> (<?= (int) $a['score'] ?>) <?php endforeach; ?></p>
            <?php endif; ?>
            <?php endif; ?>
            <p class="hint section-gap-sm">Nota de 0 a 100: baixo risco a partir de 80; médio de 50 a 79. Quem trata dados sensíveis com nota abaixo de 80, ou não tem cláusulas contratuais, sobe de nível.</p>
        </section>

        <section class="card">
            <h2>Contato e responsabilidade</h2>
            <dl class="dl section-gap-sm">
                <dt>Contato</dt><dd><?= e($v['contact_name'] ?? '—') ?></dd>
                <dt>E-mail</dt><dd><?= e($v['contact_email'] ?? '—') ?></dd>
                <dt>Telefone</dt><dd><?= e($v['contact_phone'] ?? '—') ?></dd>
                <dt>Responsável</dt><dd><?= e($v['owner_name'] ?? '—') ?></dd>
                <dt>Setor</dt><dd><?= e($v['department_name'] ?? '—') ?></dd>
            </dl>
        </section>
    </div>
</div>
