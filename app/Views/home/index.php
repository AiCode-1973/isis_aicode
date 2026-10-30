<section class="hero">
    <div class="container">
        <p class="eyebrow">ÍSIS · Gestão da Privacidade e Proteção de Dados</p>
        <h1>A proteção de dados da sua empresa, organizada.</h1>
        <p class="lead">Uma plataforma para implantar e manter a gestão da LGPD: mapeie operações de tratamento, avalie riscos, atenda titulares, registre incidentes e comprove cada passo com uma trilha de auditoria.</p>
        <div class="actions">
            <a class="btn btn-teal" href="/cadastro">Criar conta da empresa <?= icon('arrow-right') ?></a>
            <a class="btn btn-light" href="/entrar">Já tenho conta</a>
        </div>
        <div class="pillars"><span>LGPD</span><span>Governança</span><span>Segurança da Informação</span><span>Conformidade</span></div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <p class="eyebrow">Módulos</p>
            <h2>Tudo o que a gestão da privacidade pede, em um só lugar</h2>
            <p class="muted">Cada empresa tem seu ambiente isolado, com usuários, configurações e dados próprios.</p>
        </div>
        <div class="grid grid-3">
            <?php
            $features = [
                ['dashboard', 'Painel de conformidade', 'Pendências, riscos, indicadores, responsáveis e prazos à vista.'],
                ['database', 'Inventário de dados', 'Operações de tratamento com finalidades, bases legais, retenção e compartilhamentos.'],
                ['target', 'Riscos e planos de ação', 'Avaliação, classificação, medidas mitigadoras, prazos e evidências.'],
                ['inbox', 'Direitos dos titulares', 'Registro e acompanhamento dos pedidos previstos na LGPD, com prazos.'],
                ['siren', 'Incidentes de segurança', 'Classificação, investigação, providências e histórico completo.'],
                ['truck', 'Fornecedores', 'Avaliação, contratos e documentos de proteção de dados de terceiros.'],
                ['file', 'Políticas e documentos', 'Versões, aprovações, validade e ciência dos colaboradores.'],
                ['graduation', 'Treinamentos', 'Módulos, avaliações, progresso e certificados.'],
                ['audit', 'Trilha de auditoria', 'Quem fez o quê e quando, com verificação de integridade.'],
            ];
            foreach ($features as [$ic, $name, $text]): ?>
            <div class="feature">
                <div class="kpi-icon teal"><?= icon($ic, 'icon-lg') ?></div>
                <h3><?= e($name) ?></h3>
                <p><?= e($text) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <p class="eyebrow">Para qualquer segmento</p>
            <h2>Sugestões adaptadas ao seu setor, sem limitar a sua realidade</h2>
            <p class="muted">Ao escolher o segmento, o ISIS sugere setores, exemplos e orientações. Tudo pode ser ajustado.</p>
        </div>
        <div class="segments">
            <span>Saúde</span><span>Comércio e Varejo</span><span>Educação</span><span>Tecnologia</span><span>Indústria</span><span>Serviços</span><span>Outros segmentos</span>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <p class="eyebrow">Como funciona</p>
            <h2>Comece em minutos</h2>
        </div>
        <div class="grid grid-3 how">
            <div class="feature"><h3>Cadastre a empresa</h3><p>Crie a conta institucional no mesmo endereço, sem instalar nada.</p></div>
            <div class="feature"><h3>Configure e convide</h3><p>Escolha o segmento, organize os setores, nomeie o encarregado e convide a equipe com o perfil certo.</p></div>
            <div class="feature"><h3>Gerencie e comprove</h3><p>Acompanhe pendências e prazos no painel e mantenha evidências de cada ação.</p></div>
        </div>

        <div class="section">
            <div class="grid grid-2">
                <div class="feature">
                    <div class="kpi-icon"><?= icon('lock', 'icon-lg') ?></div>
                    <h3>Segurança desde a base</h3>
                    <p>Senhas com Argon2id, sessões protegidas, bloqueio de tentativas, criptografia de dados sensíveis, isolamento entre empresas e registro de acessos.</p>
                </div>
                <div class="feature">
                    <div class="kpi-icon"><?= icon('users', 'icon-lg') ?></div>
                    <h3>Acesso pelo menor privilégio</h3>
                    <p>Perfis de administrador, encarregado, gestor, colaborador e auditor: cada um vê e faz só o necessário.</p>
                </div>
            </div>
        </div>

        <div class="disclaimer"><?= icon('info') ?><span><?= Isis\Core\View::partial('partials/disclaimer') ?></span></div>
    </div>
</section>
