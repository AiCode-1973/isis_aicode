<?php
/* Página inicial (vendas). Telas reais do sistema com dados fictícios de demonstração. */
$shot = static fn (string $name): string => e(asset('img/manual/' . $name . '.webp'));
?>
<section class="lp-hero">
    <div class="container lp-hero-inner">
        <a class="lp-badge" href="#modulos"><span>Novo</span> Relatório de impacto (RIPD) e verificação em duas etapas <?= icon('arrow-right') ?></a>
        <h1>A proteção de dados da sua empresa, <em>organizada</em>.</h1>
        <p class="lp-lead">Uma plataforma para implantar e manter a gestão da LGPD: mapeie os dados, avalie riscos, atenda titulares, trate incidentes e comprove cada passo com uma trilha de auditoria.</p>
        <div class="lp-cta">
            <a class="btn btn-teal btn-lg" href="/cadastro">Começar teste grátis <?= icon('arrow-right') ?></a>
            <a class="btn btn-glass btn-lg" href="#planos">Ver planos e preços</a>
        </div>
        <ul class="lp-trust">
            <li><?= icon('check') ?> 14 dias grátis, sem cartão</li>
            <li><?= icon('check') ?> Servidor no Brasil</li>
            <li><?= icon('check') ?> Dados isolados por empresa</li>
        </ul>
    </div>
    <div class="container">
        <figure class="lp-frame">
            <div class="lp-frame-bar" aria-hidden="true"><span></span><span></span><span></span><em>isis.aicode.dev.br/riscos</em></div>
            <img src="<?= $shot('riscos') ?>" alt="Tela de riscos do ÍSIS com a matriz de probabilidade e impacto" width="1366" height="1000" fetchpriority="high">
        </figure>
    </div>
</section>

<section class="lp-audience" aria-label="Para quem é o ÍSIS">
    <div class="container">
        <p>Feito para quem cuida de dados pessoais todos os dias</p>
        <ul>
            <li><?= icon('building') ?> Empresas</li>
            <li><?= icon('shield') ?> Hospitais</li>
            <li><?= icon('users') ?> Clínicas</li>
            <li><?= icon('search') ?> Laboratórios</li>
            <li><?= icon('graduation') ?> Escolas</li>
            <li><?= icon('layers') ?> Varejo e serviços</li>
        </ul>
    </div>
</section>

<section class="lp-section lp-dark" id="modulos">
    <div class="container">
        <div class="lp-head">
            <span class="lp-pill">Módulos</span>
            <h2>Tudo o que a gestão da privacidade pede, em um só lugar</h2>
            <p>Cada empresa tem um ambiente isolado, com usuários, perfis de acesso e dados próprios. Todos os módulos estão em todos os planos.</p>
        </div>

        <div class="bento">
            <article class="bento-card bento-wide">
                <div class="bento-text">
                    <h3><?= icon('database') ?> Inventário de dados</h3>
                    <p>Operações de tratamento com finalidade, hipótese legal, retenção, sistemas e compartilhamentos. Exporta o registro do art. 37.</p>
                </div>
                <div class="mini-table" aria-hidden="true">
                    <div><span class="mono">OP-002</span> Prontuário eletrônico <i class="chip chip-danger">sensível</i><i class="chip chip-ok">Ativa</i></div>
                    <div><span class="mono">OP-003</span> Faturamento de convênios <i class="chip chip-ok">Ativa</i></div>
                    <div><span class="mono">OP-005</span> Lembretes por WhatsApp <i class="chip chip-warn">sem hipótese legal</i></div>
                </div>
            </article>

            <article class="bento-card">
                <h3><?= icon('target') ?> Riscos e planos de ação</h3>
                <p>Matriz 5×5, risco inerente e residual, responsáveis e prazos.</p>
                <div class="mini-heat" aria-hidden="true">
                    <?php foreach ([[1,2,2,3,3],[1,2,3,3,4],[1,1,2,3,3],[1,1,2,2,3],[1,1,1,1,2]] as $row): foreach ($row as $lv): ?><i class="lv<?= $lv ?>"></i><?php endforeach; endforeach; ?>
                </div>
            </article>

            <article class="bento-card">
                <h3><?= icon('scale') ?> Relatório de impacto (RIPD)</h3>
                <p>O ÍSIS indica as operações de alto risco e monta o relatório com os dados do inventário. Cada versão aprovada fica guardada.</p>
                <div class="mini-doc" aria-hidden="true"><b>RIPD-001</b><span>Versão 1 · aprovada</span><i class="chip chip-ok"><?= icon('check') ?> íntegro</i></div>
            </article>

            <article class="bento-card">
                <h3><?= icon('inbox') ?> Direitos dos titulares</h3>
                <p>Canal on-line com protocolo, prazo de 15 dias e resposta registrada.</p>
                <div class="mini-timer" aria-hidden="true"><b>15</b><span>dias para responder<br><em>Protocolo JL37-6BR9</em></span></div>
            </article>

            <article class="bento-card">
                <h3><?= icon('siren') ?> Incidentes de segurança</h3>
                <p>Qualquer colaborador comunica. O encarregado classifica, investiga e controla o prazo da ANPD.</p>
                <ol class="mini-steps" aria-hidden="true"><li class="done">Comunicado</li><li class="done">Classificado</li><li>Comunicar à ANPD · 3 dias úteis</li></ol>
            </article>

            <article class="bento-card bento-stack">
                <div class="bento-text">
                    <h3><?= icon('file') ?> Fornecedores, políticas e treinamentos</h3>
                    <p>Avaliação de terceiros com nota de risco, políticas com aprovação e ciência dos colaboradores, treinamentos com certificado verificável.</p>
                </div>
                <div class="mini-cards" aria-hidden="true">
                    <div><small>Fornecedor</small><b>Risco baixo</b><i class="chip chip-ok">contrato ok</i></div>
                    <div><small>Política</small><b>v2 vigente</b><i class="chip chip-info">87% ciência</i></div>
                    <div><small>Treinamento</small><b>Certificado</b><i class="chip chip-ok">aprovado 90%</i></div>
                </div>
            </article>

            <article class="bento-card bento-wide">
                <div class="bento-text">
                    <h3><?= icon('dashboard') ?> Painel, relatório e trilha de auditoria</h3>
                    <p>Pendências e prazos à vista, relatório de conformidade para a diretoria e o registro de quem fez o quê e quando, com verificação de integridade.</p>
                </div>
                <div class="mini-audit" aria-hidden="true">
                    <div><span class="mono">09:41</span> Rafael Nunes · RIPD aprovado</div>
                    <div><span class="mono">09:38</span> Mariana Costa · Risco RSK-006 atualizado</div>
                    <div><span class="mono">09:12</span> Beatriz Lima · Solicitação respondida</div>
                    <p class="ok"><?= icon('check') ?> Cadeia de auditoria íntegra</p>
                </div>
            </article>
        </div>
    </div>
</section>

<section class="lp-section lp-security" id="seguranca">
    <div class="container lp-split">
        <div>
            <span class="lp-pill">Segurança</span>
            <h2>Quem cuida de dados dos outros precisa começar pelos próprios</h2>
            <p>O ÍSIS guarda informações sensíveis da sua empresa: pedidos de titulares, investigações de incidentes, contratos. Por isso a segurança vem desde a base, não como acessório.</p>
        </div>
        <ul class="lp-checks">
            <li><?= icon('lock') ?><div><b>Criptografia dos dados sensíveis</b><span>Dados de titulares e investigações de incidentes ficam cifrados no banco.</span></div></li>
            <li><?= icon('shield') ?><div><b>Verificação em duas etapas</b><span>Código do celular além da senha; a empresa pode torná-la obrigatória.</span></div></li>
            <li><?= icon('users') ?><div><b>Acesso pelo menor privilégio</b><span>Cinco perfis, cada um vê e faz só o necessário.</span></div></li>
            <li><?= icon('audit') ?><div><b>Trilha de auditoria à prova de alteração</b><span>Cada ação registrada em cadeia de hashes, com verificação de integridade.</span></div></li>
            <li><?= icon('building') ?><div><b>Servidor no Brasil e empresas isoladas</b><span>Os dados ficam no Brasil e cada empresa enxerga só o seu ambiente.</span></div></li>
            <li><?= icon('refresh') ?><div><b>Cópias de segurança diárias criptografadas</b><span>Banco de dados e evidências, com restauração pelo responsável técnico.</span></div></li>
        </ul>
    </div>
</section>

<section class="lp-section" id="como-funciona">
    <div class="container">
        <div class="lp-head lp-head-light">
            <span class="lp-pill lp-pill-light">Como funciona</span>
            <h2>Comece hoje, sem instalar nada</h2>
        </div>
        <ol class="lp-steps">
            <li><b>Cadastre a empresa</b><span>Crie a conta e escolha o segmento. O ÍSIS sugere setores, exemplos e orientações para o seu ramo.</span></li>
            <li><b>Convide a equipe</b><span>Encarregado, gestores, colaboradores e auditor, cada um com o perfil certo.</span></li>
            <li><b>Gerencie e comprove</b><span>Acompanhe pendências e prazos no painel e guarde evidências de cada ação.</span></li>
        </ol>
    </div>
</section>

<?= Isis\Core\View::partial('home/pricing', ['plans' => $plans, 'trialDays' => $trialDays]) ?>

<section class="lp-section" id="duvidas">
    <div class="container lp-faq">
        <div class="lp-head lp-head-light">
            <span class="lp-pill lp-pill-light">Dúvidas</span>
            <h2>Perguntas frequentes</h2>
        </div>
        <div>
            <details>
                <summary>O ÍSIS garante que minha empresa está em conformidade com a LGPD?</summary>
                <p>Não. Nenhum sistema garante conformidade sozinho. O ÍSIS organiza, orienta e comprova a gestão da privacidade, mas não substitui o encarregado nem a análise jurídica especializada.</p>
            </details>
            <details>
                <summary>Preciso instalar alguma coisa?</summary>
                <p>Não. O ÍSIS funciona no navegador, no computador e no celular. Basta criar a conta da empresa.</p>
            </details>
            <details>
                <summary>Onde ficam os dados da minha empresa?</summary>
                <p>Em servidor no Brasil, com os dados sensíveis criptografados, cópias de segurança diárias e cada empresa isolada das demais.</p>
            </details>
            <details>
                <summary>Como funciona o teste grátis?</summary>
                <p>São <?= (int) $trialDays ?> dias com todos os recursos, sem cartão. Ao final, você escolhe um plano. Se não contratar, consegue consultar e exportar os seus dados.</p>
            </details>
            <details>
                <summary>Serve para hospitais, clínicas e laboratórios?</summary>
                <p>Sim. Há sugestões próprias para a área da saúde, controle de dados sensíveis e do relatório de impacto, e o sistema funciona para qualquer segmento.</p>
            </details>
            <details>
                <summary>E se o pagamento atrasar?</summary>
                <p>O sistema passa a somente leitura, mas o atendimento aos titulares e o tratamento de incidentes continuam funcionando, porque são obrigações legais da empresa.</p>
            </details>
        </div>
    </div>
</section>

<section class="lp-final">
    <div class="container">
        <img src="<?= e(asset('img/logo.svg')) ?>" alt="" width="64" height="64">
        <h2>Porque cuidar das informações é também cuidar das pessoas.</h2>
        <p>Comece agora a organizar a proteção de dados da sua empresa.</p>
        <div class="lp-cta">
            <a class="btn btn-teal btn-lg" href="/cadastro">Começar teste grátis <?= icon('arrow-right') ?></a>
            <a class="btn btn-glass btn-lg" href="/entrar">Já tenho conta</a>
        </div>
        <p class="lp-disclaimer"><?= Isis\Core\View::partial('partials/disclaimer') ?></p>
    </div>
</section>
