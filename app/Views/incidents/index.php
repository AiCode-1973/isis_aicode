<?php

use Isis\Auth\Permission as P;
use Isis\Support\Deadline;
use Isis\Support\Labels;

$severityBadge = ['critica' => 'badge-danger', 'alta' => 'badge-orange', 'media' => 'badge-warn', 'baixa' => 'badge-ok'];
$statusBadge = ['comunicado' => 'badge-warn', 'em_analise' => 'badge-brand', 'contido' => 'badge-brand', 'resolvido' => 'badge-ok', 'encerrado' => 'badge'];
$filtered = $filters['status'] || $filters['severity'] || $filters['open'];
?>
<div class="page-head">
    <div>
        <h1><?= $onlyMine ? 'Meus comunicados de incidente' : 'Incidentes de segurança' ?></h1>
        <p><?= $onlyMine ? 'Incidentes que você comunicou e a situação de cada um.' : 'Registro, classificação, investigação, providências e comunicação à ANPD e aos titulares (art. 48).' ?></p>
    </div>
    <?php if (can(P::INCIDENTS_REPORT) || can(P::INCIDENTS_MANAGE)): ?>
    <a class="btn btn-danger-solid" href="/incidentes/novo"><?= icon('siren') ?> Comunicar incidente</a>
    <?php endif; ?>
</div>

<?php if ($stats !== null): ?>
<div class="grid grid-4">
    <a class="card kpi" href="/incidentes?abertos=1"><span class="kpi-icon warn"><?= icon('siren', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['open'] ?></div><div class="kpi-label">Em aberto · <?= (int) $stats['untriaged'] ?> aguardando análise</div></div></a>
    <div class="card kpi"><span class="kpi-icon"><?= icon('alert', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['severe'] ?></div><div class="kpi-label">Abertos de gravidade alta ou crítica</div></div></div>
    <div class="card kpi"><span class="kpi-icon info"><?= icon('mail', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['anpd_pending'] ?></div><div class="kpi-label">Comunicação à ANPD pendente · <?= (int) $stats['anpd_overdue'] ?> fora do prazo</div></div></div>
    <div class="card kpi"><span class="kpi-icon teal"><?= icon('check', 'icon-lg') ?></span><div><div class="kpi-value"><?= (int) $stats['total'] ?></div><div class="kpi-label">Registrados no total</div></div></div>
</div>

<form method="get" action="/incidentes" class="card filters section-gap" aria-label="Filtros">
    <div class="field">
        <label for="f-status">Situação</label>
        <select id="f-status" name="status">
            <option value="">Todas</option>
            <?php foreach (Labels::INCIDENT_STATUS as $k => $v): ?><option value="<?= e($k) ?>"<?= $filters['status'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="f-sev">Gravidade</label>
        <select id="f-sev" name="gravidade">
            <option value="">Todas</option>
            <?php foreach (Labels::SEVERITY as $k => $v): ?><option value="<?= e($k) ?>"<?= $filters['severity'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <span class="label">Mostrar</span>
        <label class="check"><input type="checkbox" name="abertos" value="1"<?= $filters['open'] ? ' checked' : '' ?>> <span>Só em aberto</span></label>
    </div>
    <div></div>
    <div class="actions">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <?php if ($filtered): ?><a class="btn btn-ghost" href="/incidentes">Limpar</a><?php endif; ?>
    </div>
</form>
<?php endif; ?>

<section class="card section-gap" aria-label="Incidentes">
    <?php if (!$incidents): ?>
    <p class="empty"><?= $filtered ? 'Nenhum incidente com esses filtros.' : 'Nenhum incidente registrado.' ?></p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table-stack">
            <thead><tr><th>Código</th><th>Incidente</th><th>Percebido em</th><th>Gravidade</th><th>ANPD</th><th>Responsável</th><th>Situação</th></tr></thead>
            <tbody>
            <?php foreach ($incidents as $i): $left = Deadline::daysLeft($i['anpd_deadline']); ?>
            <tr class="<?= $i['anpd_overdue'] ? 'row-overdue' : '' ?>">
                <td data-label="Código"><span class="code"><?= e(code('INC', (int) $i['seq'])) ?></span></td>
                <td data-label="Incidente">
                    <a class="title-link" href="/incidentes/<?= (int) $i['id'] ?>"><?= e($i['title']) ?></a>
                    <div class="small muted"><?= e(label(Labels::INCIDENT_CATEGORY, $i['category'])) ?><?= $i['sensitive_data'] ? ' · <span class="text-danger">dados sensíveis</span>' : '' ?></div>
                </td>
                <td data-label="Percebido em"><?= e(fmt_datetime($i['detected_at'])) ?></td>
                <td data-label="Gravidade"><?= $i['severity'] ? '<span class="badge ' . $severityBadge[$i['severity']] . '">' . e(Labels::SEVERITY[$i['severity']]) . '</span>' : '<span class="badge badge-warn">a classificar</span>' ?></td>
                <td data-label="ANPD">
                    <?php if ($i['risk_to_subjects'] !== 'relevante'): ?><span class="muted">—</span>
                    <?php elseif ($i['anpd_notified_at']): ?><span class="badge badge-ok">comunicada</span>
                    <?php else: ?><span class="badge <?= $left !== null && $left < 0 ? 'badge-danger' : 'badge-warn' ?>">até <?= e(fmt_date($i['anpd_deadline'])) ?></span><?php endif; ?>
                </td>
                <td data-label="Responsável"><?= e($i['owner_name'] ?? '—') ?></td>
                <td data-label="Situação"><span class="badge <?= $statusBadge[$i['status']] ?? '' ?>"><?= e(explode(' (', Labels::INCIDENT_STATUS[$i['status']])[0]) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
