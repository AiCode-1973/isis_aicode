<?php

use Isis\Services\RiskMatrix;
use Isis\Support\Labels;

$t = $r['tenant'];
$pct = static fn (int $part, int $total): string => $total > 0 ? (string) round($part / $total * 100) . '%' : '—';
$days = (int) round((strtotime($r['to']) - strtotime($r['from'])) / 86400);
$actionBase = max(0, $r['actions']['total'] - $r['actions']['cancelled']);
?>
<div class="page-head no-print">
    <div>
        <h1>Relatório de conformidade</h1>
        <p>Visão consolidada da gestão de privacidade, para a direção, auditorias e eventuais fiscalizações.</p>
    </div>
    <div class="actions">
        <form method="get" action="/relatorios" class="actions">
            <label for="f-dias" class="visually-hidden">Período</label>
            <select id="f-dias" name="dias" data-autosubmit>
                <?php foreach ([30 => 'Últimos 30 dias', 90 => 'Últimos 90 dias', 180 => 'Últimos 6 meses', 365 => 'Últimos 12 meses'] as $d => $label): ?>
                <option value="<?= $d ?>"<?= $days === $d ? ' selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn-ghost">Aplicar</button></noscript>
        </form>
        <button type="button" class="btn btn-primary" data-print><?= icon('download') ?> Imprimir ou salvar em PDF</button>
    </div>
</div>

<article class="report">
    <header class="report-head">
        <div class="report-brand"><img src="<?= e(asset('img/logo.svg')) ?>" alt="" width="44" height="44"><div><strong>ÍSIS</strong><span>Gestão da Privacidade e Proteção de Dados</span></div></div>
        <h1>Relatório de conformidade com a LGPD</h1>
        <dl class="report-id">
            <dt>Empresa</dt><dd><?= e($t['legal_name']) ?> · CNPJ <?= e(fmt_cnpj($t['cnpj'])) ?></dd>
            <dt>Segmento</dt><dd><?= e($t['segment_name']) ?></dd>
            <dt>Encarregado</dt><dd><?= e($t['dpo_name'] ?? '—') ?><?= $t['dpo_email'] ? ' · ' . e($t['dpo_email']) : '' ?></dd>
            <dt>Período</dt><dd><?= e(fmt_date($r['from'])) ?> a <?= e(fmt_date($r['to'])) ?> (movimentos) · situação atual em <?= e(date('d/m/Y H:i')) ?></dd>
            <dt>Gerado por</dt><dd><?= e($generatedBy) ?></dd>
        </dl>
    </header>

    <section class="report-section">
        <h2>1. Resumo</h2>
        <div class="report-kpis">
            <div><strong><?= (int) $r['inventory']['active'] ?></strong><span>operações de tratamento ativas</span></div>
            <div><strong><?= (int) $r['risks']['open'] ?></strong><span>riscos abertos (<?= (int) ($r['risks']['critical'] + $r['risks']['high']) ?> altos/críticos)</span></div>
            <div><strong><?= $pct((int) $r['actions']['done'], $actionBase) ?></strong><span>execução do plano de ação</span></div>
            <div><strong><?= (int) $r['requestsPeriod']['received'] ?></strong><span>solicitações de titulares no período</span></div>
            <div><strong><?= (int) $r['incidentsPeriod']['total'] ?></strong><span>incidentes no período</span></div>
            <div><strong><?= $pct((int) $r['trainings']['completed'], (int) $r['trainings']['enrolled']) ?></strong><span>conclusão dos treinamentos</span></div>
        </div>
    </section>

    <section class="report-section">
        <h2>2. Inventário de dados pessoais</h2>
        <p><?= (int) $r['inventory']['total'] ?> operação(ões) registrada(s), <?= (int) $r['inventory']['active'] ?> ativa(s); <?= (int) $r['inventory']['sensitive'] ?> com dados sensíveis; <?= (int) $r['inventory']['international'] ?> com transferência internacional; <?= (int) $r['inventory']['no_basis'] ?> sem hipótese legal definida; <?= (int) $r['inventory']['review_overdue'] ?> com revisão vencida.</p>
        <?php if ($r['activities']): ?>
        <table class="report-table">
            <thead><tr><th>Código</th><th>Operação</th><th>Hipótese legal</th><th>Sensíveis</th><th>Setor</th><th>Situação</th></tr></thead>
            <tbody>
            <?php foreach ($r['activities'] as $a): ?>
            <tr><td><?= e(code('OP', (int) $a['seq'])) ?></td><td><?= e($a['name']) ?></td><td><?= e($a['legal_basis_article'] ?? 'não definida') ?><?= $a['sensitive_basis_article'] ? ' / ' . e($a['sensitive_basis_article']) : '' ?></td><td><?= $a['has_sensitive'] ? 'Sim' : 'Não' ?></td><td><?= e($a['department_name'] ?? '—') ?></td><td><?= e(label(Labels::ACTIVITY_STATUS, $a['status'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <section class="report-section">
        <h2>3. Riscos</h2>
        <div class="report-split">
            <div><?= Isis\Core\View::partial('partials/heatmap', ['heatmap' => $r['heatmap'], 'selected' => null]) ?></div>
            <div>
                <p>Riscos abertos por nível: crítico <?= (int) $r['risks']['critical'] ?>, alto <?= (int) $r['risks']['high'] ?>, médio <?= (int) $r['risks']['medium'] ?>, baixo <?= (int) $r['risks']['low'] ?>. Sem responsável: <?= (int) $r['risks']['unowned'] ?>. Revisão vencida: <?= (int) $r['risks']['review_overdue'] ?>.</p>
                <?php if ($r['topRisks']): ?>
                <table class="report-table">
                    <thead><tr><th>Risco</th><th>Nível</th><th>Tratamento</th></tr></thead>
                    <tbody><?php foreach ($r['topRisks'] as $k): ?><tr><td><?= e(code('RSK', (int) $k['seq']) . ' · ' . $k['title']) ?></td><td><?= e(RiskMatrix::label(RiskMatrix::levelOf((int) $k['likelihood'], (int) $k['impact']))) ?> (<?= (int) $k['score'] ?>)</td><td><?= e(label(Labels::RISK_TREATMENT, $k['treatment'])) ?></td></tr><?php endforeach; ?></tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="report-section">
        <h2>4. Plano de ação</h2>
        <p><?= (int) $r['actions']['total'] ?> ação(ões): <?= (int) $r['actions']['done'] ?> concluída(s) (<?= (int) $r['actions']['done_on_time'] ?> dentro do prazo), <?= (int) ($r['actions']['pending'] + $r['actions']['in_progress']) ?> em aberto, <?= (int) $r['actions']['overdue'] ?> vencida(s).</p>
        <?php if ($r['overdueActions']): ?>
        <table class="report-table">
            <thead><tr><th>Ação vencida</th><th>Responsável</th><th>Prazo</th></tr></thead>
            <tbody><?php foreach ($r['overdueActions'] as $x): ?><tr><td><?= e(code('ACT', (int) $x['seq']) . ' · ' . $x['title']) ?></td><td><?= e($x['owner_name'] ?? '—') ?></td><td><?= e(fmt_date($x['due_date'])) ?></td></tr><?php endforeach; ?></tbody>
        </table>
        <?php endif; ?>
    </section>

    <section class="report-section">
        <h2>5. Direitos dos titulares</h2>
        <p>No período: <?= (int) $r['requestsPeriod']['received'] ?> solicitação(ões) recebida(s) e <?= (int) $r['requestsPeriod']['answered'] ?> respondida(s), <?= $pct((int) $r['requestsPeriod']['on_time'], (int) $r['requestsPeriod']['answered']) ?> dentro do prazo de referência. Situação atual: <?= (int) $r['requests']['open'] ?> em aberto, <?= (int) $r['requests']['overdue'] ?> fora do prazo. Tempo médio de resposta: <?= $r['requests']['avg_days'] === null ? '—' : e(number_format($r['requests']['avg_days'], 1, ',', '')) . ' dia(s)' ?>.</p>
        <?php if ($r['requestsPeriod']['by_type']): ?>
        <table class="report-table"><thead><tr><th>Tipo de pedido</th><th>Quantidade</th></tr></thead>
            <tbody><?php foreach ($r['requestsPeriod']['by_type'] as $row): ?><tr><td><?= e(label(Labels::RIGHT_TYPE, $row['right_type'])) ?></td><td><?= (int) $row['n'] ?></td></tr><?php endforeach; ?></tbody></table>
        <?php endif; ?>
    </section>

    <section class="report-section">
        <h2>6. Incidentes de segurança</h2>
        <p>No período: <?= (int) $r['incidentsPeriod']['total'] ?> incidente(s), <?= (int) $r['incidentsPeriod']['relevant'] ?> com risco relevante aos titulares, dos quais <?= (int) $r['incidentsPeriod']['notified_on_time'] ?> comunicado(s) à ANPD dentro do prazo de referência. Situação atual: <?= (int) $r['incidents']['open'] ?> em aberto; <?= (int) $r['incidents']['anpd_pending'] ?> com comunicação à ANPD pendente.</p>
        <?php if ($r['incidentsPeriod']['by_severity']): ?>
        <p class="small">Por gravidade: <?php foreach ($r['incidentsPeriod']['by_severity'] as $row): ?><?= e($row['severity'] === 'a_classificar' ? 'a classificar' : Labels::SEVERITY[$row['severity']]) ?>: <?= (int) $row['n'] ?> · <?php endforeach; ?></p>
        <?php endif; ?>
    </section>

    <section class="report-section">
        <h2>7. Fornecedores</h2>
        <p><?= (int) $r['vendors']['active'] ?> fornecedor(es) ativo(s); <?= (int) $r['vendors']['unassessed'] ?> sem avaliação; <?= (int) $r['vendors']['no_dpa'] ?> trata(m) dados sem contrato com cláusulas de proteção; <?= (int) $r['vendors']['contracts_expiring'] ?> contrato(s) vencendo em 30 dias.
            Por risco: <?php foreach ($r['vendorsByRisk'] as $row): ?><?= e($row['level'] === 'nao_avaliado' ? 'não avaliado' : Labels::LEVEL[$row['level']]) ?>: <?= (int) $row['n'] ?> · <?php endforeach; ?></p>
    </section>

    <section class="report-section">
        <h2>8. Políticas e treinamentos</h2>
        <p>Políticas: <?= (int) $r['policies']['published'] ?> vigente(s) de <?= (int) $r['policies']['total'] ?>; <?= (int) $r['policies']['expired'] ?> vencida(s); <?= (int) $r['policies']['awaiting'] ?> aguardando aprovação; ciência dos colaboradores em <?= $pct((int) $r['policies']['ack_done'], (int) $r['policies']['ack_expected']) ?>.</p>
        <p>Treinamentos: <?= (int) $r['trainings']['modules'] ?> publicado(s); <?= (int) $r['trainings']['completed'] ?> de <?= (int) $r['trainings']['enrolled'] ?> participações concluídas (<?= $pct((int) $r['trainings']['completed'], (int) $r['trainings']['enrolled']) ?>); <?= (int) $r['trainings']['overdue'] ?> com prazo vencido; <?= (int) $r['trainingsPeriod'] ?> concluída(s) no período.</p>
    </section>

    <section class="report-section">
        <h2>9. Rastreabilidade</h2>
        <p><?= (int) $r['audit']['events'] ?> ação(ões) registrada(s) na trilha de auditoria no período, <?= (int) $r['audit']['security'] ?> de segurança (acessos negados e tentativas de login recusadas). Verificação da cadeia de integridade: <strong><?= $r['audit']['chain']['ok'] ? 'íntegra' : 'VIOLADA' ?></strong> (<?= (int) $r['audit']['chain']['checked'] ?> registros conferidos).</p>
    </section>

    <footer class="report-foot">
        <p>Relatório gerado pelo ÍSIS a partir das informações registradas pela própria empresa. Indicadores de organização interna: não substituem análise jurídica especializada nem representam certificação ou garantia de conformidade legal. Prazos de referência: 15 dias para titulares (art. 19, II) e 3 dias úteis para a comunicação de incidentes à ANPD (Res. CD/ANPD nº 15/2024).</p>
    </footer>
</article>

<section class="card section-gap no-print" aria-labelledby="exp-title">
    <h2 id="exp-title">Exportar dados em planilha (CSV)</h2>
    <p class="small muted">Arquivos para Excel ou LibreOffice. O registro das operações de tratamento fica em Inventário; a trilha de auditoria, em Auditoria. Cada exportação fica registrada.</p>
    <div class="grid grid-cards section-gap-sm">
        <?php foreach ($exports as $type => [$label, $desc]): ?>
        <a class="training-card export-card" href="/relatorios/exportar/<?= e($type) ?>"><h3><?= icon('download') ?> <?= e($label) ?></h3><p class="small muted"><?= e($desc) ?></p></a>
        <?php endforeach; ?>
    </div>
</section>
