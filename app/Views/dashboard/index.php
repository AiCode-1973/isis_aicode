<?php

use Isis\Auth\Permission as P;

$width = 'w-' . (int) (round($progress / 10) * 10);
$company = $tenant['trade_name'] ?: $tenant['legal_name'];
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><?= e($tenant['segment_name']) ?></p>
        <h1>Olá, <?= e(explode(' ', (string) auth_user()['name'])[0]) ?></h1>
        <p>Acompanhe a implantação da gestão de privacidade de <?= e($company) ?>.</p>
    </div>
    <?php if (can(P::USERS_MANAGE)): ?>
    <a class="btn btn-primary" href="/usuarios#convidar"><?= icon('plus') ?> Convidar colaborador</a>
    <?php endif; ?>
</div>

<div class="grid grid-4">
    <div class="card kpi">
        <span class="kpi-icon"><?= icon('users', 'icon-lg') ?></span>
        <div><div class="kpi-value"><?= (int) $stats['users'] ?></div><div class="kpi-label">Usuários ativos</div></div>
    </div>
    <div class="card kpi">
        <span class="kpi-icon warn"><?= icon('mail', 'icon-lg') ?></span>
        <div><div class="kpi-value"><?= (int) $stats['invites'] ?></div><div class="kpi-label">Convites pendentes</div></div>
    </div>
    <div class="card kpi">
        <span class="kpi-icon teal"><?= icon('building', 'icon-lg') ?></span>
        <div><div class="kpi-value"><?= (int) $stats['departments'] ?></div><div class="kpi-label">Setores</div></div>
    </div>
    <div class="card kpi">
        <span class="kpi-icon info"><?= icon('audit', 'icon-lg') ?></span>
        <div><div class="kpi-value"><?= (int) $stats['events'] ?></div><div class="kpi-label">Ações registradas (7 dias)</div></div>
    </div>
</div>

<div class="grid grid-main section-gap">
    <div class="stack">
        <section class="card" aria-labelledby="journey-title">
            <div class="card-header">
                <div>
                    <h2 id="journey-title">Jornada de implantação</h2>
                    <p>Primeiros passos da governança de dados.</p>
                </div>
                <span class="badge badge-teal"><?= (int) $progress ?>% concluído</span>
            </div>
            <div class="progress" role="progressbar" aria-valuenow="<?= (int) $progress ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progresso da implantação">
                <span class="<?= e($width) ?>"></span>
            </div>
            <ul class="steps section-gap-sm">
                <?php foreach ($journey as $i => $step): ?>
                <li class="<?= $step['done'] ? 'done' : '' ?>">
                    <span class="step-dot" aria-hidden="true"><?= $step['done'] ? icon('check', 'icon') : $i + 1 ?></span>
                    <div>
                        <div class="step-title"><?= e($step['label']) ?> <?php if (!$step['done']): ?><span class="badge badge-warn">Pendente</span><?php endif; ?></div>
                        <div class="step-hint"><?= e($step['hint']) ?></div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <?php if (can(P::AUDIT_VIEW)): ?>
        <section class="card" aria-labelledby="recent-title">
            <div class="card-header">
                <div>
                    <h2 id="recent-title">Atividade recente</h2>
                    <p>Últimas ações registradas na trilha de auditoria.</p>
                </div>
                <a class="btn btn-ghost btn-sm" href="/auditoria">Ver trilha completa</a>
            </div>
            <?php if (!$recent): ?>
            <p class="empty">Nenhuma ação registrada ainda.</p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table-stack">
                    <thead><tr><th>Quando</th><th>Quem</th><th>Ação</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $row): ?>
                    <tr>
                        <td data-label="Quando"><?= e(fmt_datetime($row['created_at'])) ?></td>
                        <td data-label="Quem"><?= e($row['user_name'] ?? 'Sistema') ?></td>
                        <td data-label="Ação"><?= e(audit_label($row['action'])) ?></td>
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
        <section class="card" aria-labelledby="dpo-title">
            <h2 id="dpo-title">Encarregado de dados</h2>
            <?php if ($tenant['dpo_name']): ?>
            <dl class="dl">
                <dt>Nome</dt><dd><?= e($tenant['dpo_name']) ?></dd>
                <dt>E-mail</dt><dd><?= e($tenant['dpo_email']) ?></dd>
                <dt>Canal</dt><dd><?= e($tenant['privacy_email'] ?: $tenant['dpo_email']) ?></dd>
            </dl>
            <?php else: ?>
            <p class="muted">Ainda não definido.</p>
            <?php endif; ?>
        </section>

        <?php if ($guidance): ?>
        <section class="card" aria-labelledby="guide-title">
            <h2 id="guide-title">Orientações para <?= e($tenant['segment_name']) ?></h2>
            <ul class="guidance">
                <?php foreach ($guidance as $tip): ?><li><?= e($tip) ?></li><?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <section class="card" aria-labelledby="role-title">
            <h2 id="role-title">Seu perfil</h2>
            <p><span class="badge badge-brand"><?= e($role?->label()) ?></span></p>
            <p class="small muted"><?= e($role?->description()) ?></p>
        </section>
    </div>
</div>
