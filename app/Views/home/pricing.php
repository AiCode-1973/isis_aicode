<?php $loggedIn = Isis\Auth\Auth::check(); ?>
<section id="planos" class="<?= $loggedIn ? '' : 'section section-alt' ?>">
    <div class="<?= $loggedIn ? '' : 'container' ?>">
        <div class="section-head">
            <p class="eyebrow">Planos e preços</p>
            <h2>Todos os módulos em todos os planos. Você escolhe pelo tamanho da equipe.</h2>
            <p class="muted">Comece com <?= (int) $trialDays ?> dias grátis, sem cartão. No plano anual você paga 10 meses e usa 12.</p>
        </div>

        <div class="pricing-grid">
            <?php foreach ($plans as $i => $p): $featured = $p['code'] === 'profissional'; ?>
            <article class="card pricing-card<?= $featured ? ' is-featured' : '' ?>">
                <?php if ($featured): ?><span class="badge badge-teal pricing-flag">Mais escolhido</span><?php endif; ?>
                <h3><?= e($p['name']) ?></h3>
                <p class="muted"><?= e((string) $p['description']) ?></p>
                <?php if ($p['contact_only'] || $p['price_monthly_cents'] === null): ?>
                <p class="pricing-price">Sob consulta</p>
                <p class="muted small">Proposta conforme o porte e as necessidades da empresa.</p>
                <?php else: ?>
                <p class="pricing-price"><?= e(money($p['price_monthly_cents'])) ?><small>/mês</small></p>
                <p class="muted small">ou <?= e(money($p['price_yearly_cents'])) ?> por ano</p>
                <?php endif; ?>
                <ul class="pricing-list">
                    <?php foreach ($p['highlights'] as $item): ?><li><?= icon('check') ?> <?= e($item) ?></li><?php endforeach; ?>
                </ul>
                <?php if ($loggedIn): ?>
                <a class="btn <?= $featured ? 'btn-primary' : 'btn-ghost' ?> btn-block" href="/plano#mudar">Escolher este plano</a>
                <?php elseif ($p['contact_only']): ?>
                <a class="btn btn-ghost btn-block" href="/cadastro">Testar e falar com a AiCode</a>
                <?php else: ?>
                <a class="btn <?= $featured ? 'btn-primary' : 'btn-ghost' ?> btn-block" href="/cadastro">Começar teste grátis</a>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-3 section-gap">
            <div class="feature"><h3>Sem bloqueio das obrigações legais</h3><p>Se o pagamento atrasar, o ÍSIS passa a somente leitura, mas o atendimento aos titulares e o tratamento de incidentes continuam funcionando.</p></div>
            <div class="feature"><h3>Seus dados continuam seus</h3><p>Em qualquer situação da assinatura você consulta e exporta inventário, riscos, planos, relatórios e a trilha de auditoria.</p></div>
            <div class="feature"><h3>Implantação e encarregado como serviço</h3><p>Precisa de ajuda para mapear os dados ou de um encarregado (DPO) externo? Fale com a AiCode: são serviços à parte.</p></div>
        </div>
        <p class="muted small section-gap">Valores em reais. O ÍSIS apoia a gestão da proteção de dados, mas não garante, por si só, a conformidade com a LGPD.</p>
    </div>
</section>
