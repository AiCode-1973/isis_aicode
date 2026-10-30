<?php
/*
 * RIPD em formato de documento (imprimir ou salvar em PDF). $doc vem de DpiaService::document(),
 * ao vivo ou da cópia guardada na aprovação ($approval preenchido).
 */

use Isis\Support\Labels;

$d = $doc['dpia'];
$t = $doc['tenant'];
$text = static fn (?string $v): string => $v !== null && trim($v) !== '' ? '<p class="pre-line">' . e($v) . '</p>' : '<p class="muted">Não informado.</p>';
$list = static fn (array $items): string => $items ? e(implode('; ', $items)) : '—';
$n = 0;
?>
<div class="page-head no-print">
    <div>
        <p class="eyebrow"><a href="/ripd">Relatórios de impacto</a> · <a href="/ripd/<?= (int) $id ?>"><?= e($d['code']) ?></a></p>
        <h1><?= $approval ? 'Versão ' . (int) $approval['version'] . ' aprovada' : 'Versão ' . (int) $d['version'] . ' em elaboração' ?></h1>
    </div>
    <button type="button" class="btn btn-primary" data-print><?= icon('download') ?> Imprimir ou salvar em PDF</button>
</div>

<?php if ($approval && !$approval['intact']): ?>
<div class="alert alert-danger no-print"><?= icon('alert') ?><p>O conteúdo guardado desta versão não confere com o código de integridade registrado na aprovação.</p></div>
<?php endif; ?>

<article class="report">
    <header class="report-head">
        <div class="report-brand"><img src="<?= e(asset('img/logo.svg')) ?>" alt="" width="44" height="44"><div><strong>ÍSIS</strong><span>Gestão da Privacidade e Proteção de Dados</span></div></div>
        <h1>Relatório de Impacto à Proteção de Dados Pessoais</h1>
        <?php if (!$approval): ?>
        <p class="report-draft"><?= icon('alert') ?> Documento em elaboração: ainda não aprovado.</p>
        <?php endif; ?>
        <dl class="report-id">
            <dt>Relatório</dt><dd><?= e($d['code']) ?> · <?= e($d['title']) ?> · versão <?= (int) $d['version'] ?></dd>
            <dt>Controlador</dt><dd><?= e($t['legal_name']) ?> · CNPJ <?= e(fmt_cnpj($t['cnpj'])) ?></dd>
            <dt>Encarregado</dt><dd><?= e($t['dpo_name'] ?? '—') ?><?= !empty($t['dpo_email']) ? ' · ' . e($t['dpo_email']) : '' ?></dd>
            <dt>Elaboração</dt><dd><?= e($d['owner_name'] ?? '—') ?></dd>
            <?php if ($approval): ?>
            <dt>Aprovação</dt><dd><?= e($approval['approved_by_name']) ?> em <?= e(fmt_datetime($approval['approved_at'])) ?></dd>
            <?php endif; ?>
            <dt>Próxima revisão</dt><dd><?= e(fmt_date($d['review_due_date'] ?? null)) ?></dd>
        </dl>
    </header>

    <section class="report-section">
        <h2><?= ++$n ?>. Necessidade do relatório</h2>
        <p><strong>Situações de alto risco identificadas:</strong> <?= $list($d['triggers']) ?>.</p>
        <?= trim((string) $d['need_justification']) !== '' ? $text($d['need_justification']) : '' ?>
    </section>

    <section class="report-section">
        <h2><?= ++$n ?>. Operações de tratamento avaliadas</h2>
        <?php if (!$doc['activities']): ?><p class="muted">Nenhuma operação vinculada.</p><?php endif; ?>
        <?php foreach ($doc['activities'] as $a): ?>
        <h3 class="report-sub"><?= e($a['code'] . ' · ' . $a['name']) ?></h3>
        <table class="report-table report-kv">
            <tbody>
                <tr><th>Finalidade</th><td><?= e($a['purpose'] ?: '—') ?></td></tr>
                <tr><th>Papel da empresa</th><td><?= e($a['controller_role']) ?></td></tr>
                <tr><th>Hipótese legal</th><td><?= e($a['legal_basis'] ?? '—') ?><?= $a['sensitive_basis'] ? '<br>Dados sensíveis: ' . e($a['sensitive_basis']) : '' ?></td></tr>
                <tr><th>Dados pessoais</th><td><?= $list($a['data']) ?></td></tr>
                <tr><th>Titulares</th><td><?= $list($a['subjects']) ?> · <?= e($a['volume']) ?></td></tr>
                <tr><th>Origem dos dados</th><td><?= e($a['source']) ?></td></tr>
                <tr><th>Sistemas</th><td><?= $list($a['systems']) ?></td></tr>
                <tr><th>Compartilhamentos</th><td><?= $list($a['sharings']) ?></td></tr>
                <tr><th>Transferência internacional</th><td><?= e($a['international'] ?? 'Não') ?></td></tr>
                <tr><th>Retenção e descarte</th><td><?= e($a['retention'] ?: '—') ?><?= $a['disposal'] ? ' · ' . e($a['disposal']) : '' ?></td></tr>
                <tr><th>Medidas de segurança</th><td><?= e($a['security'] ?: '—') ?></td></tr>
                <tr><th>Setor e responsável</th><td><?= e($a['department'] ?? '—') ?> · <?= e($a['owner'] ?? '—') ?></td></tr>
            </tbody>
        </table>
        <?php endforeach; ?>
    </section>

    <section class="report-section">
        <h2><?= ++$n ?>. Descrição do tratamento</h2>
        <h3 class="report-sub">Natureza</h3><?= $text($d['nature']) ?>
        <h3 class="report-sub">Escopo</h3><?= $text($d['scope']) ?>
        <h3 class="report-sub">Contexto</h3><?= $text($d['context']) ?>
        <h3 class="report-sub">Finalidade</h3><?= $text($d['purpose']) ?>
    </section>

    <section class="report-section">
        <h2><?= ++$n ?>. Partes interessadas consultadas</h2>
        <?= $text($d['stakeholders']) ?>
    </section>

    <section class="report-section">
        <h2><?= ++$n ?>. Necessidade e proporcionalidade</h2>
        <?= $text($d['necessity']) ?>
    </section>

    <section class="report-section">
        <h2><?= ++$n ?>. Identificação e avaliação dos riscos</h2>
        <p class="small">Nível = probabilidade × impacto (escala de 1 a 5): baixo até 4, médio até 9, alto até 15, crítico de 16 a 25.</p>
        <?php if (!$doc['risks']): ?>
        <p class="muted">Nenhum risco registrado para as operações avaliadas.</p>
        <?php else: ?>
        <table class="report-table">
            <thead><tr><th>Risco</th><th>Operação</th><th>Inerente</th><th>Residual</th><th>Tratamento</th><th>Plano de ação</th></tr></thead>
            <tbody>
            <?php foreach ($doc['risks'] as $r): ?>
            <tr>
                <td><strong><?= e($r['code']) ?></strong> · <?= e($r['title']) ?><div class="small muted"><?= e($r['category']) ?><?= $r['consequence'] ? ' · ' . e($r['consequence']) : '' ?></div></td>
                <td><?= e($r['activity']) ?></td>
                <td><?= risk_badge($r['likelihood'], $r['impact']) ?></td>
                <td><?= $r['residual_likelihood'] !== null ? risk_badge($r['residual_likelihood'], $r['residual_impact']) : '<span class="muted">não avaliado</span>' ?></td>
                <td><?= e($r['treatment']) ?> · <?= e($r['status']) ?></td>
                <td><?= $r['actions_count'] > 0 ? $r['actions_done'] . ' de ' . $r['actions_count'] . ' concluída(s)' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <section class="report-section">
        <h2><?= ++$n ?>. Medidas para tratar os riscos</h2>
        <?php foreach ($doc['risks'] as $r): if (trim((string) $r['treatment_notes']) === '') { continue; } ?>
        <p><strong><?= e($r['code']) ?>:</strong> <?= e($r['treatment_notes']) ?></p>
        <?php endforeach; ?>
        <h3 class="report-sub">Medidas e salvaguardas gerais</h3>
        <?= $text($d['safeguards']) ?>
    </section>

    <section class="report-section">
        <h2><?= ++$n ?>. Conclusão</h2>
        <p><strong><?= e(Labels::of(Labels::DPIA_CONCLUSION, $d['conclusion'])) ?></strong></p>
        <?= trim((string) $d['conclusion_notes']) !== '' ? $text($d['conclusion_notes']) : '' ?>
        <h3 class="report-sub">Parecer do encarregado</h3>
        <?= $text($d['dpo_opinion']) ?>
    </section>

    <section class="report-section report-signatures">
        <div><span></span><p>Encarregado de dados<br><?= e($t['dpo_name'] ?? '') ?></p></div>
        <div><span></span><p>Representante do controlador<?= $approval ? '<br>' . e($approval['approved_by_name']) : '' ?></p></div>
    </section>

    <p class="report-foot">
        <?php if ($approval): ?>
        Conteúdo congelado na aprovação · código de integridade (SHA-256) <span class="mono"><?= e($approval['sha256']) ?></span>.
        <?php else: ?>
        Gerado em <?= e(date('d/m/Y H:i')) ?> com os dados atuais do ÍSIS.
        <?php endif; ?>
        O ÍSIS apoia a elaboração do relatório, mas não garante, por si só, a conformidade com a LGPD.
    </p>
</article>
