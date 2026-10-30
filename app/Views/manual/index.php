<?php
/*
 * Manual do sistema ÍSIS.
 * REGRA: toda alteração no sistema deve atualizar este manual (versão, seção afetada e histórico no fim).
 */

$sections = [
    'apresentacao' => 'Apresentação',
    'primeiros-passos' => 'Primeiros passos',
    'acesso' => 'Acesso e segurança da conta',
    'perfis' => 'Perfis e permissões',
    'painel' => 'Painel de conformidade',
    'inventario' => 'Inventário de dados pessoais',
    'catalogos' => 'Catálogos do inventário',
    'riscos' => 'Gestão de riscos',
    'planos' => 'Planos de ação',
    'titulares' => 'Solicitações de titulares',
    'canal' => 'Canal on-line do titular',
    'incidentes' => 'Incidentes de segurança',
    'fornecedores' => 'Fornecedores',
    'politicas' => 'Políticas e documentos',
    'treinamentos' => 'Treinamentos e certificados',
    'evidencias' => 'Evidências',
    'usuarios' => 'Usuários e convites',
    'configuracoes' => 'Configurações da empresa',
    'auditoria' => 'Trilha de auditoria',
    'perfil' => 'Meu perfil',
    'duvidas' => 'Dúvidas frequentes',
    'proximos' => 'Próximos módulos',
    'historico' => 'Histórico de alterações',
];

// Figura com imagem de tela (geradas por: node bin/manual-screenshots.mjs). Clique abre a imagem inteira.
$fig = static function (string $file, string $caption, string $class = ''): string {
    $src = asset('img/manual/' . $file . '.webp');
    return '<figure class="shot' . ($class !== '' ? ' ' . e($class) : '') . '"><a href="' . e($src) . '" target="_blank" rel="noopener">'
        . '<img src="' . e($src) . '" alt="' . e('Tela do ÍSIS: ' . $caption) . '" loading="lazy" decoding="async"></a>'
        . '<figcaption>' . e($caption) . '</figcaption></figure>';
};
?>
<div class="manual">
    <aside class="manual-toc" aria-label="Sumário do manual">
        <p class="eyebrow">Manual do sistema</p>
        <nav>
            <ol>
                <?php foreach ($sections as $id => $label): ?>
                <li><a href="#<?= $id ?>"><?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ol>
        </nav>
        <button type="button" class="btn btn-ghost btn-sm btn-block section-gap" data-print><?= icon('download') ?> Imprimir ou salvar em PDF</button>
    </aside>

    <article class="manual-content prose">
        <header class="manual-head">
            <p class="eyebrow">ÍSIS · Gestão da Privacidade e Proteção de Dados</p>
            <h1>Manual do sistema</h1>
            <p class="muted">Versão <?= e(APP_VERSION) ?> · atualizado em 29/10/2026 · módulos das fases 1 a 4</p>
            <div class="disclaimer"><?= icon('info') ?><span>O ÍSIS apoia a organização da conformidade com a LGPD e <strong>não substitui análise jurídica especializada nem garante conformidade legal</strong>. As orientações automáticas exibidas no sistema servem de apoio ao preenchimento.</span></div>
        </header>

        <section id="apresentacao">
            <h2>1. Apresentação</h2>
            <p>O ÍSIS é um sistema on-line para implantar e manter a gestão da privacidade e da proteção de dados pessoais (LGPD) em empresas de qualquer segmento: saúde, comércio, educação, tecnologia, indústria, serviços e outros.</p>
            <p>Todas as empresas acessam pelo mesmo endereço, <strong>https://isis.aicode.dev.br</strong>. Cada empresa tem o próprio ambiente, com seus usuários, configurações e dados, totalmente separados das demais: ninguém de uma empresa vê ou altera informações de outra.</p>
            <?= $fig('inicio', 'Página inicial pública do ÍSIS') ?>
            <p><strong>Módulos disponíveis nesta versão:</strong> cadastro da empresa e usuários, perfis e permissões, configuração por segmento, painel de conformidade, inventário de dados pessoais, gestão de riscos, planos de ação, solicitações de titulares com canal on-line, incidentes de segurança, fornecedores, políticas e documentos, treinamentos com certificado, evidências e trilha de auditoria.</p>
            <p><strong>Navegadores:</strong> funciona em computador, tablet e celular, nas versões atuais do Chrome, Edge, Firefox e Safari.</p>
            <p><strong>Menu lateral:</strong> o botão <span class="kbd">☰</span>, no canto superior esquerdo, abre e fecha o menu.</p>
            <ul>
                <li><strong>No computador:</strong> o menu recolhe para uma faixa só com ícones, e o conteúdo se expande para ocupar toda a largura da tela. Passe o mouse sobre um ícone para ver o nome. O ÍSIS lembra a sua escolha nas próximas visitas, naquele navegador.</li>
                <li><strong>No celular e em telas estreitas:</strong> o menu abre por cima do conteúdo. Para fechar, toque fora dele ou pressione <span class="kbd">Esc</span>.</li>
            </ul>
            <div class="shot-pair">
                <?= $fig('menu-recolhido', 'Computador: menu recolhido, só com ícones') ?>
                <?= $fig('celular-menu', 'Celular: menu aberto sobre o conteúdo', 'shot-phone') ?>
            </div>
        </section>

        <section id="primeiros-passos">
            <h2>2. Primeiros passos</h2>
            <h3>2.1 Criar a conta da empresa</h3>
            <ol>
                <li>Acesse o endereço do ÍSIS e clique em <strong>Criar conta</strong>.</li>
                <li>Informe razão social, nome fantasia (opcional), CNPJ e o segmento de atuação. O CNPJ pode ser no formato numérico ou no novo formato alfanumérico.</li>
                <li>Informe seu nome, e-mail corporativo e uma senha forte. Quem cria a conta passa a ser o <strong>administrador</strong> da empresa.</li>
                <li>Confirme a leitura dos termos e clique em <strong>Criar conta da empresa</strong>.</li>
            </ol>
            <?= $fig('cadastro', 'Cadastro da empresa e do administrador') ?>
            <p>Cada CNPJ só pode ser cadastrado uma vez. Se a sua empresa já usa o ÍSIS, peça um convite ao administrador em vez de criar outra conta.</p>
            <h3>2.2 Confirmar o e-mail</h3>
            <p>Você receberá um e-mail com o link de confirmação, válido por 48 horas e de uso único. Só é possível entrar depois de confirmar. Se não chegar, verifique o spam ou peça um novo link na própria tela ou tentando entrar.</p>
            <h3>2.3 Configuração inicial</h3>
            <p>No primeiro acesso, o administrador passa por um assistente de três passos:</p>
            <ol>
                <li><strong>Segmento:</strong> ao trocar o segmento, as sugestões da tela mudam.</li>
                <li><strong>Setores:</strong> marque os setores sugeridos que existem na empresa e acrescente outros, um por linha.</li>
                <li><strong>Encarregado e canal de privacidade:</strong> nome e e-mail do encarregado (DPO) e o e-mail público para os titulares. O encarregado pode ser você, outra pessoa da equipe ou um serviço externo.</li>
            </ol>
            <?= $fig('configuracao-inicial', 'Assistente de configuração inicial (exemplo de uma escola)') ?>
            <p>Ao concluir, o ÍSIS também cria as <strong>categorias de dados e de titulares sugeridas</strong> para o segmento. Tudo pode ser alterado depois em Configurações e em Catálogos. Enquanto a configuração não termina, o administrador volta para o assistente ao entrar.</p>
            <h3>2.4 Convidar a equipe</h3>
            <p>Em <strong>Usuários e perfis</strong>, convide o encarregado, os gestores, os colaboradores e, se houver, o auditor. Veja a seção <a href="#usuarios">Usuários e convites</a>.</p>
            <h3>2.5 Roteiro sugerido de implantação</h3>
            <ol>
                <li>Mapear as operações de tratamento no <a href="#inventario">Inventário</a>, começando pelas que envolvem dados sensíveis ou muitos titulares.</li>
                <li>Definir a hipótese legal de cada operação e completar os pontos de atenção.</li>
                <li>Avaliar os <a href="#riscos">riscos</a> das operações mais críticas.</li>
                <li>Criar <a href="#planos">ações</a> para tratar os riscos, com responsável e prazo.</li>
                <li>Acompanhar tudo pelo <a href="#painel">Painel de conformidade</a> e guardar evidências do que foi feito.</li>
            </ol>
        </section>

        <section id="acesso">
            <h2>3. Acesso e segurança da conta</h2>
            <h3>3.1 Entrar e sair</h3>
            <p>Entre com e-mail e senha em <strong>Entrar</strong>. Para sair, use o menu com seu nome, no canto superior direito, e clique em <strong>Sair</strong>.</p>
            <?= $fig('login', 'Tela de entrada') ?>
            <h3>3.2 Regras de segurança</h3>
            <ul>
                <li><strong>Senha:</strong> de 10 a 128 caracteres, com letras maiúsculas, minúsculas e números.</li>
                <li><strong>Bloqueio por tentativas:</strong> depois de 5 senhas erradas para a mesma conta, ou 20 a partir da mesma rede, em 15 minutos, o acesso fica bloqueado por 15 minutos. Se precisar, use "Esqueci minha senha".</li>
                <li><strong>Sessão:</strong> encerrada automaticamente após 30 minutos sem uso ou 8 horas desde a entrada.</li>
                <li>Trocar a senha, redefini-la ou ter o perfil alterado encerra as sessões abertas em outros dispositivos.</li>
                <li>Contas desativadas pelo administrador perdem o acesso imediatamente.</li>
            </ul>
            <h3>3.3 Recuperar o acesso</h3>
            <ol>
                <li>Na tela de entrada, clique em <strong>Esqueci minha senha</strong> e informe seu e-mail.</li>
                <li>Se o e-mail estiver cadastrado e ativo, você recebe um link válido por 60 minutos e de uso único. Por segurança, a mensagem na tela é a mesma em qualquer caso.</li>
                <li>Crie a nova senha. Todas as sessões abertas da conta são encerradas.</li>
            </ol>
        </section>

        <section id="perfis">
            <h2>4. Perfis e permissões</h2>
            <p>Cada usuário tem um perfil. O ÍSIS segue o princípio do <strong>menor privilégio</strong>: cada pessoa vê e faz apenas o necessário para o seu papel. Os itens de menu aparecem conforme o perfil.</p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Recurso</th><th>Administrador</th><th>Encarregado</th><th>Gestor</th><th>Colaborador</th><th>Auditor</th></tr></thead>
                    <tbody>
                        <tr><td>Painel de conformidade</td><td>Sim</td><td>Sim</td><td>Sim</td><td>Sim (as próprias tarefas)</td><td>Sim</td></tr>
                        <tr><td>Inventário de dados</td><td>Ver e alterar</td><td>Ver e alterar</td><td>Ver tudo; alterar o do seu setor</td><td>—</td><td>Ver</td></tr>
                        <tr><td>Riscos</td><td>Ver e alterar</td><td>Ver e alterar</td><td>Ver tudo; alterar o do seu setor</td><td>—</td><td>Ver</td></tr>
                        <tr><td>Planos de ação</td><td>Ver e alterar</td><td>Ver e alterar</td><td>Ver tudo; alterar o do seu setor</td><td>Só as próprias tarefas: andamento e evidências</td><td>Ver</td></tr>
                        <tr><td>Solicitações de titulares</td><td>Ver e atender</td><td>Ver e atender</td><td>Ver</td><td>—</td><td>Ver</td></tr>
                        <tr><td>Incidentes de segurança</td><td>Comunicar e conduzir</td><td>Comunicar e conduzir</td><td>Comunicar e ver todos</td><td>Comunicar e acompanhar os próprios</td><td>Ver</td></tr>
                        <tr><td>Fornecedores</td><td>Ver e alterar</td><td>Ver e alterar</td><td>Ver</td><td>—</td><td>Ver</td></tr>
                        <tr><td>Políticas e documentos</td><td>Editar, aprovar e dar ciência</td><td>Editar, aprovar e dar ciência</td><td>Ler vigentes e dar ciência</td><td>Ler vigentes e dar ciência</td><td>Ler vigentes</td></tr>
                        <tr><td>Treinamentos</td><td>Criar, designar e fazer</td><td>Criar, designar e fazer</td><td>Fazer os designados</td><td>Fazer os designados</td><td>Fazer os designados</td></tr>
                        <tr><td>Usuários</td><td>Ver e administrar</td><td>Ver</td><td>—</td><td>—</td><td>Ver</td></tr>
                        <tr><td>Configurações da empresa</td><td>Sim</td><td>—</td><td>—</td><td>—</td><td>—</td></tr>
                        <tr><td>Trilha de auditoria</td><td>Sim</td><td>Sim</td><td>—</td><td>—</td><td>Sim</td></tr>
                    </tbody>
                </table>
            </div>
            <p><strong>"Do seu setor"</strong>: o gestor altera registros cujo setor é o dele, que ele mesmo criou ou que estão sob a responsabilidade dele. O auditor nunca altera nada. Tentativas de acessar algo sem permissão são bloqueadas e ficam registradas na auditoria.</p>
        </section>

        <section id="painel">
            <h2>5. Painel de conformidade</h2>
            <p>É a tela inicial depois de entrar. Reúne, num só lugar, o que precisa de atenção. No topo, o quadro <strong>Para você</strong> lista os treinamentos designados e as políticas que ainda pedem sua ciência; ele aparece para todos os perfis.</p>
            <?= $fig('painel', 'Painel de conformidade da administradora (dados fictícios)') ?>
            <ul>
                <li><strong>Pendências:</strong> políticas vencidas ou aguardando aprovação, fornecedores de risco alto ou sem cláusulas contratuais, contratos vencendo, treinamentos atrasados, comunicação à ANPD pendente ou vencida, solicitações de titulares fora do prazo ou vencendo em 3 dias, incidentes aguardando análise, ações vencidas, riscos altos ou críticos em aberto, operações sem hipótese legal ou com revisão vencida, riscos e ações sem responsável. Cada item leva direto à lista filtrada.</li>
                <li><strong>Indicadores:</strong> operações ativas, riscos abertos (quantos altos ou críticos), ações em aberto (quantas vencidas), percentual de execução dos planos, solicitações de titulares em aberto, incidentes em aberto, fornecedores (quantos de risco alto) e conclusão dos treinamentos.</li>
                <li><strong>Prazos:</strong> ações vencidas e as que vencem nos próximos 30 dias, com o responsável.</li>
                <li><strong>Riscos:</strong> matriz de probabilidade × impacto e quantidade por nível.</li>
                <li><strong>Inventário, o que falta completar:</strong> operações com informações pendentes.</li>
                <li><strong>Jornada de implantação:</strong> sete passos, da configuração inicial à execução dos planos, com o percentual concluído. É um indicador de organização interna, não um certificado de conformidade.</li>
                <li><strong>Responsáveis:</strong> ações em aberto e vencidas por pessoa.</li>
                <li><strong>Atividade recente</strong> (perfis com acesso à auditoria), dados do encarregado e orientações do segmento.</li>
            </ul>
            <p>Para o colaborador, o painel mostra apenas as tarefas atribuídas a ele.</p>
            <?= $fig('celular-painel', 'O painel no celular', 'shot-phone') ?>
        </section>

        <section id="inventario">
            <h2>6. Inventário de dados pessoais</h2>
            <p>O inventário registra as <strong>operações de tratamento</strong>: cada atividade da empresa que usa dados pessoais, como cadastro de clientes, folha de pagamento, prontuário ou newsletter. Ele serve de base para o registro das operações exigido pelo art. 37 da LGPD.</p>
            <h3>6.1 Consultar</h3>
            <p>Em <strong>Inventário de dados</strong>, a lista mostra código (OP-001, OP-002…), nome, setor, hipótese legal, responsável, situação e quantidade de riscos. É possível buscar por nome ou finalidade e filtrar por situação, setor ou apenas operações com dados sensíveis.</p>
            <?= $fig('inventario', 'Lista de operações de tratamento') ?>
            <h3>6.2 Registrar uma operação</h3>
            <p>Clique em <strong>Nova operação</strong>. Só o nome e a finalidade são obrigatórios: salve como <em>Rascunho</em> e complete depois. O formulário tem seis partes:</p>
            <ol>
                <li><strong>Identificação:</strong>
                    <ul>
                        <li>nome e descrição;</li>
                        <li>situação: rascunho, ativa, em revisão ou descontinuada;</li>
                        <li>papel da empresa: controlador, controlador conjunto ou operador;</li>
                        <li>setor responsável, responsável e data da próxima revisão.</li>
                    </ul>
                </li>
                <li><strong>Finalidade e hipótese legal:</strong> para que os dados são usados; a hipótese legal do art. 7º; e, quando houver dados sensíveis, a do art. 11. Há também um campo para a justificativa e as salvaguardas, como a avaliação de legítimo interesse ou os cuidados com crianças.</li>
                <li><strong>Dados e titulares:</strong>
                    <ul>
                        <li>categorias de dados (as sensíveis aparecem marcadas);</li>
                        <li>categorias de titulares (as vulneráveis aparecem marcadas);</li>
                        <li>volume de titulares e origem dos dados.</li>
                    </ul>
                </li>
                <li><strong>Sistemas e setores envolvidos:</strong> onde os dados ficam e quais outros setores os acessam.</li>
                <li><strong>Compartilhamentos:</strong> destinatário, tipo (operador, outro controlador, órgão público, empresa do grupo), finalidade e se fica no Brasil ou no exterior. Deixe em branco as linhas sem uso. Marque a transferência internacional e descreva o país e o mecanismo (art. 33).</li>
                <li><strong>Retenção, descarte e segurança:</strong> por quanto tempo os dados são guardados, como são eliminados e quais medidas de segurança protegem a operação.</li>
            </ol>
            <?= $fig('inventario-form', 'Formulário de nova operação (início)') ?>
            <h3>6.3 Pontos de atenção</h3>
            <p>Na página de cada operação, o quadro <strong>Pontos de atenção</strong> lista o que falta ou merece revisão:</p>
            <ul>
                <li>finalidade ou hipótese legal ausente;</li>
                <li>dado sensível sem hipótese legal do art. 11;</li>
                <li>categorias de dados ou de titulares não informadas;</li>
                <li>prazo de retenção não definido;</li>
                <li>operação sem responsável;</li>
                <li>transferência internacional sem detalhes;</li>
                <li>revisão vencida;</li>
                <li>titulares vulneráveis sem as salvaguardas registradas.</li>
            </ul>
            <p>São orientações de preenchimento, não um parecer jurídico.</p>
            <?= $fig('inventario-detalhe', 'Detalhe de uma operação, com pontos de atenção e responsabilidade') ?>
            <h3>6.4 Riscos e ações da operação</h3>
            <p>A página da operação mostra os riscos e as ações vinculados. O botão <strong>Registrar risco</strong> já abre o formulário ligado a ela.</p>
            <h3>6.5 Exportar o registro das operações</h3>
            <p>Clique em <strong>Exportar registro (CSV)</strong>. O arquivo, que abre no Excel ou no LibreOffice, traz:</p>
            <ul>
                <li>no cabeçalho, os dados da empresa e do encarregado;</li>
                <li>uma linha por operação, com todos os campos do inventário.</li>
            </ul>
            <p>Cada exportação fica registrada na auditoria.</p>
            <h3>6.6 Excluir</h3>
            <p>Excluir uma operação mantém os riscos e as ações ligados a ela, só sem o vínculo. Para preservar o histórico, prefira mudar a situação para <em>Descontinuada</em>.</p>
        </section>

        <section id="catalogos">
            <h2>7. Catálogos do inventário</h2>
            <p>Em <strong>Inventário → Catálogos</strong> ficam as listas usadas nas operações:</p>
            <ul>
                <li><strong>Categorias de dados pessoais:</strong> marque como <em>sensível</em> as do art. 5º, II (saúde, biometria, genética, origem racial ou étnica, religião, opinião política, filiação sindical, vida sexual).</li>
                <li><strong>Categorias de titulares:</strong> marque como <em>vulnerável</em> grupos como crianças, adolescentes e idosos.</li>
                <li><strong>Sistemas e locais de armazenamento:</strong> nuvem/SaaS, servidor próprio, sistema de terceiro ou papel.</li>
            </ul>
            <?= $fig('catalogos', 'Catálogos do inventário') ?>
            <p>O botão <strong>Importar sugestões do segmento</strong> acrescenta as categorias sugeridas para o segmento da empresa, sem duplicar as que já existem. Excluir um item o remove das operações em que ele aparece. O sistema avisa quantas operações serão afetadas.</p>
        </section>

        <section id="riscos">
            <h2>8. Gestão de riscos</h2>
            <h3>8.1 Como o nível é calculado</h3>
            <p>Cada risco recebe uma nota de <strong>probabilidade</strong> e uma de <strong>impacto</strong>, de 1 a 5:</p>
            <ul>
                <li>probabilidade: rara, improvável, possível, provável ou quase certa;</li>
                <li>impacto: insignificante, menor, moderado, maior ou gravíssimo.</li>
            </ul>
            <p>O nível é a multiplicação das duas notas:</p>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Nível</th><th>Pontuação</th><th>Leitura sugerida</th></tr></thead>
                    <tbody>
                        <tr><td><span class="badge badge-ok">Baixo</span></td><td>1 a 4</td><td>Acompanhar.</td></tr>
                        <tr><td><span class="badge badge-warn">Médio</span></td><td>5 a 9</td><td>Tratar no planejamento normal.</td></tr>
                        <tr><td><span class="badge badge-orange">Alto</span></td><td>10 a 15</td><td>Tratar com prioridade, com responsável e prazo.</td></tr>
                        <tr><td><span class="badge badge-danger">Crítico</span></td><td>16 a 25</td><td>Tratar imediatamente e informar a direção.</td></tr>
                    </tbody>
                </table>
            </div>
            <h3>8.2 Registrar um risco</h3>
            <ol>
                <li>Em <strong>Riscos</strong>, clique em <strong>Novo risco</strong>, ou use <strong>Registrar risco</strong> dentro de uma operação.</li>
                <li>Informe:
                    <ul>
                        <li>o título;</li>
                        <li>a categoria: segurança, conformidade, direitos dos titulares, terceiros, retenção, transparência ou outro;</li>
                        <li>a operação ligada, se houver;</li>
                        <li>a causa e as consequências.</li>
                    </ul>
                </li>
                <li>Avalie a probabilidade e o impacto <strong>inerentes</strong>, isto é, antes das medidas. Depois de aplicar as medidas, registre também o nível <strong>residual</strong>.</li>
                <li>Escolha o tratamento: <strong>mitigar</strong>, <strong>aceitar</strong>, <strong>transferir</strong> ou <strong>evitar</strong>. Para aceitar um risco, é obrigatório escrever a justificativa.</li>
                <li>Defina o responsável, o setor, a situação e a data da próxima revisão.</li>
            </ol>
            <?= $fig('risco-form', 'Formulário de novo risco, com a escala de referência') ?>
            <h3>8.3 Matriz de riscos</h3>
            <p>A matriz mostra quantos riscos abertos estão em cada combinação de probabilidade e impacto. Clique numa célula para ver só esses riscos. Riscos abertos são os que estão como <em>Identificado</em> ou <em>Em tratamento</em>.</p>
            <?= $fig('riscos', 'Matriz de riscos e quantidade por nível') ?>
            <h3>8.4 Plano de ação do risco</h3>
            <p>Na página do risco, <strong>Nova ação</strong> cria uma medida já vinculada a ele. A prioridade vem sugerida como alta para riscos altos e críticos. O quadro mostra quantas ações do risco já foram concluídas.</p>
            <?= $fig('risco-detalhe', 'Detalhe de um risco com seu plano de ação') ?>
        </section>

        <section id="planos">
            <h2>9. Planos de ação</h2>
            <p>As ações são as medidas para tratar riscos e as demais tarefas de conformidade. Cada ação tem código (ACT-001…), responsável, setor, prazo, prioridade (alta, média ou baixa) e situação: pendente, em andamento, concluída ou cancelada.</p>
            <h3>9.1 Criar e acompanhar</h3>
            <ul>
                <li>Crie em <strong>Planos de ação → Nova ação</strong> ou a partir de um risco.</li>
                <li>Descreva o que será feito e qual critério comprova a conclusão.</li>
                <li>A lista pode ser filtrada por situação, responsável, "só vencidas" e "só as minhas". As vencidas aparecem destacadas em vermelho.</li>
            </ul>
            <?= $fig('planos', 'Lista de ações dos planos') ?>
            <h3>9.2 Registrar andamento</h3>
            <p>Na página da ação, use <strong>Registrar andamento</strong> para mudar a situação ou deixar uma observação. Tudo vai para o <strong>Histórico</strong>, com data e autor. A data de conclusão é preenchida automaticamente quando a ação passa a <em>Concluída</em>.</p>
            <?= $fig('acao-detalhe', 'Detalhe da ação: andamento, evidências e histórico') ?>
            <h3>9.3 Minhas tarefas (colaborador)</h3>
            <p>O colaborador vê, em <strong>Minhas tarefas</strong>, apenas as ações atribuídas a ele. Pode registrar o andamento e anexar evidências, mas não edita os demais campos da ação.</p>
            <?= $fig('minhas-tarefas', 'Minhas tarefas, na visão do colaborador') ?>
        </section>

        <section id="titulares">
            <h2>10. Solicitações de titulares</h2>
            <p>Reúne os pedidos das pessoas sobre os próprios dados, conforme o art. 18 da LGPD:</p>
            <ul>
                <li>confirmação de que a empresa trata dados delas;</li>
                <li>acesso, correção, anonimização, bloqueio ou eliminação;</li>
                <li>portabilidade;</li>
                <li>informação sobre compartilhamentos;</li>
                <li>revogação do consentimento e oposição;</li>
                <li>revisão de decisões automatizadas (art. 20).</li>
            </ul>
            <p>Os pedidos chegam pelo <a href="#canal">canal on-line do titular</a> ou são registrados pela equipe.</p>
            <?= $fig('titulares', 'Lista de solicitações, com o endereço do canal on-line e os indicadores de prazo') ?>
            <h3>10.1 Prazo de referência</h3>
            <p>Cada solicitação recebe um <strong>protocolo</strong>, no formato XXXX-XXXX, e um prazo de referência de <strong>15 dias</strong> a partir do recebimento (art. 19, II).</p>
            <ul>
                <li>A lista mostra quantos dias faltam.</li>
                <li>Pedidos fora do prazo aparecem em vermelho.</li>
                <li>Os que vencem em até 3 dias aparecem como alerta no painel.</li>
            </ul>
            <h3>10.2 Registrar um pedido recebido por outro canal</h3>
            <p>Para pedidos que chegaram por e-mail, telefone ou presencialmente, use <strong>Registrar solicitação</strong>:</p>
            <ul>
                <li>informe o tipo de pedido, o canal, a data de recebimento (o prazo conta a partir dela) e os dados do titular;</li>
                <li>é preciso ao menos um contato: e-mail ou telefone;</li>
                <li>se o titular tiver e-mail, ele recebe o protocolo automaticamente.</li>
            </ul>
            <h3>10.3 Atender</h3>
            <p>Na página da solicitação:</p>
            <ul>
                <li><strong>Atendimento:</strong> defina o responsável, marque se a identidade do titular foi confirmada (e como) e ligue o pedido à operação de tratamento relacionada.</li>
                <li><strong>Registrar andamento:</strong> mude a situação (recebida, verificando identidade, em atendimento ou cancelada) e deixe observações. Marque <em>Mostrar ao titular</em> para que a observação apareça no acompanhamento on-line.</li>
                <li><strong>Responder ao titular:</strong> escreva a resposta e escolha o resultado, <em>atendida</em> ou <em>indeferida</em>. Na recusa, informe os motivos (art. 18, § 4º). A resposta pode ser enviada por e-mail e sempre fica visível no acompanhamento on-line.</li>
                <li><strong>Evidências:</strong> anexe o que foi enviado ao titular ou o que comprova o atendimento.</li>
            </ul>
            <?= $fig('titular-detalhe', 'Página da solicitação: pedido, resposta, atendimento e histórico') ?>
            <h3>10.4 Proteção dos dados do titular</h3>
            <ul>
                <li>Nome, e-mail, telefone, documento, descrição e resposta ficam <strong>criptografados</strong> no banco de dados.</li>
                <li>Cada vez que alguém abre uma solicitação, o acesso fica registrado na trilha de auditoria.</li>
                <li>O aviso enviado ao encarregado sobre um novo pedido traz só o protocolo e o tipo, nunca os dados pessoais.</li>
                <li>Antes de enviar dados pessoais na resposta, confirme a identidade de quem pediu.</li>
            </ul>
        </section>

        <section id="canal">
            <h2>11. Canal on-line do titular</h2>
            <p>Cada empresa tem um endereço público, no mesmo domínio do ÍSIS, para os titulares fazerem pedidos sem precisar de conta: <span class="mono">https://isis.aicode.dev.br/titular/<em>nome-da-empresa</em></span>.</p>
            <ul>
                <li>O endereço exato aparece em <strong>Solicitações de titulares</strong> e em <strong>Configurações</strong>.</li>
                <li>Divulgue-o no aviso de privacidade, no site e nos contratos.</li>
                <li>O canal só funciona depois que a configuração inicial da empresa é concluída.</li>
            </ul>
            <div class="shot-pair">
                <?= $fig('canal-titular', 'Formulário público para o titular fazer o pedido') ?>
                <?= $fig('canal-acompanhar', 'Acompanhamento pelo protocolo e e-mail', 'shot-phone') ?>
            </div>
            <h3>11.1 Como funciona para o titular</h3>
            <ol>
                <li>Escolhe o tipo de pedido, descreve o que precisa e informa nome e e-mail.</li>
                <li>Recebe na tela e por e-mail o número do protocolo e o prazo.</li>
                <li>Em <strong>Acompanhar solicitação</strong>, com o protocolo e o e-mail, vê:
                    <ul>
                        <li>a situação do pedido;</li>
                        <li>as observações marcadas como visíveis;</li>
                        <li>a resposta, quando houver.</li>
                    </ul>
                </li>
            </ol>
            <p>O encarregado recebe um e-mail a cada novo pedido. Para evitar abusos, o canal limita a quantidade de envios e de consultas por rede.</p>
        </section>

        <section id="incidentes">
            <h2>12. Incidentes de segurança</h2>
            <p>Registra e acompanha eventos que podem comprometer dados pessoais ou sistemas: acesso não autorizado, vazamento, perda, ransomware, phishing, erro humano, furto de equipamento e outros.</p>
            <?= $fig('incidentes', 'Lista de incidentes com gravidade e prazo de comunicação à ANPD') ?>
            <h3>12.1 Comunicar um incidente</h3>
            <p>Qualquer colaborador pode comunicar, pelo botão <strong>Comunicar incidente</strong>, mesmo sem ter certeza de que é um incidente. Basta informar:</p>
            <ul>
                <li>um resumo e o tipo;</li>
                <li>o que aconteceu;</li>
                <li>quando foi percebido;</li>
                <li>se há dados pessoais envolvidos.</li>
            </ul>
            <p>O encarregado recebe um e-mail na hora. Quem comunicou acompanha a situação em <strong>Meus comunicados</strong>, mas não vê os detalhes da investigação. Não apague e-mails, arquivos ou registros relacionados: eles são evidências.</p>
            <?= $fig('incidente-comunicar', 'Comunicação de incidente por um colaborador') ?>
            <h3>12.2 Classificar e investigar</h3>
            <p>O encarregado e o administrador conduzem o incidente:</p>
            <ul>
                <li><strong>Classificação:</strong>
                    <ul>
                        <li>gravidade (baixa, média, alta ou crítica);</li>
                        <li>se há dados pessoais e se há dados sensíveis;</li>
                        <li>estimativa de titulares afetados;</li>
                        <li>operação de tratamento afetada e responsável;</li>
                        <li>a <strong>avaliação de risco aos titulares</strong>.</li>
                    </ul>
                </li>
                <li><strong>Investigação e providências:</strong> medidas de contenção, causa identificada e lições aprendidas. Esses textos ficam criptografados.</li>
                <li><strong>Situação e providências:</strong> comunicado, em análise, contido, resolvido ou encerrado, com registro de cada providência no histórico.</li>
                <li><strong>Ação corretiva:</strong> cria no plano de ação uma tarefa ligada ao incidente.</li>
            </ul>
            <h3>12.3 Comunicação à ANPD e aos titulares</h3>
            <p>Quando o incidente <strong>pode acarretar risco ou dano relevante aos titulares</strong> (art. 48):</p>
            <ul>
                <li>o ÍSIS calcula o prazo de referência de <strong>3 dias úteis</strong> a partir do conhecimento do incidente (Resolução CD/ANPD nº 15/2024);</li>
                <li>o prazo aparece em destaque e entra como alerta no painel. Feriados não são considerados no cálculo; confira o calendário.</li>
            </ul>
            <p>Em <strong>Comunicações externas</strong>, registre a data e o protocolo da comunicação à ANPD e a data da comunicação aos titulares. Um incidente com risco relevante só pode ser encerrado depois de registrada a comunicação à ANPD.</p>
            <?= $fig('incidente-detalhe', 'Incidente com risco relevante: prazo da ANPD, classificação e providências') ?>
        </section>

        <section id="fornecedores">
            <h2>13. Fornecedores</h2>
            <p>Cadastre os terceiros que tratam ou recebem dados pessoais da empresa, como sistemas em nuvem, contabilidade, laboratórios, transportadoras e agências de marketing. Para cada um, registre:</p>
            <ul>
                <li>o papel em relação aos dados: <em>operador</em>, que trata em nome da empresa, ou outro controlador;</li>
                <li>se acessa dados sensíveis e se os dados ficam no exterior;</li>
                <li>as operações do inventário em que participa;</li>
                <li>o contato no fornecedor e o responsável interno.</li>
            </ul>
            <?= $fig('fornecedores', 'Lista de fornecedores com risco, contratos e revisão') ?>
            <h3>13.1 Avaliação de proteção de dados</h3>
            <p>Na página do fornecedor, responda às 12 perguntas da <strong>avaliação</strong> com <em>sim</em>, <em>parcialmente</em>, <em>não</em> ou <em>não se aplica</em>. Elas tratam de:</p>
            <ul>
                <li>contrato, instruções e suboperadores;</li>
                <li>controles de segurança: acesso, criptografia, cópias de segurança e certificações;</li>
                <li>comunicação de incidentes;</li>
                <li>transferência internacional;</li>
                <li>treinamento da equipe e devolução dos dados ao fim do contrato.</li>
            </ul>
            <p>O ÍSIS calcula uma nota de 0 a 100, em que as perguntas mais importantes pesam mais, e classifica o risco:</p>
            <ul>
                <li><strong>Baixo:</strong> nota de 80 a 100.</li>
                <li><strong>Médio:</strong> nota de 50 a 79.</li>
                <li><strong>Alto:</strong> nota abaixo de 50.</li>
            </ul>
            <p>Duas regras sobem o nível:</p>
            <ul>
                <li>quem trata dados sensíveis com nota abaixo de 80 é classificado como risco alto;</li>
                <li>quem não tem cláusulas contratuais de proteção de dados nunca fica com risco baixo.</li>
            </ul>
            <p>A próxima revisão é marcada automaticamente: em 6 meses para risco alto e em 12 meses nos demais casos. O histórico das avaliações fica guardado.</p>
            <?= $fig('fornecedor-detalhe', 'Página do fornecedor: dados, contratos, avaliação e resultado') ?>
            <h3>13.2 Contratos e documentos</h3>
            <ul>
                <li>Registre cada contrato com vigência e marque se tem cláusulas de proteção de dados (ou um acordo de tratamento de dados).</li>
                <li>Anexe o contrato assinado, questionários e certificados em <strong>Evidências</strong>.</li>
                <li>O painel avisa sobre fornecedores que tratam dados sem contrato com cláusulas e sobre contratos que vencem em 30 dias.</li>
            </ul>
        </section>

        <section id="politicas">
            <h2>14. Políticas e documentos</h2>
            <p>Reúne as políticas internas, os avisos de privacidade e os procedimentos da empresa. Cada documento passa pelas mesmas etapas:</p>
            <ol>
                <li><strong>Rascunho:</strong> o encarregado ou o administrador escreve ou ajusta o texto.</li>
                <li><strong>Envio para aprovação.</strong></li>
                <li><strong>Aprovação</strong> pelo encarregado ou pelo administrador. A versão passa a ser a <em>vigente</em>, com validade (3 a 36 meses), e a anterior fica como <em>substituída</em>.</li>
                <li>Se a versão for <strong>devolvida</strong>, o motivo fica registrado e ela volta para ajustes.</li>
            </ol>
            <p>Para começar rápido, use <strong>Usar modelos do ÍSIS</strong>. Ele cria rascunhos de três documentos, já com o nome da empresa:</p>
            <ul>
                <li>política de privacidade e proteção de dados;</li>
                <li>política de segurança da informação;</li>
                <li>procedimento de resposta a incidentes.</li>
            </ul>
            <p>Os modelos são pontos de partida: adapte-os à realidade da empresa e revise com a assessoria jurídica antes de aprovar.</p>
            <?= $fig('politicas', 'Políticas com versão vigente, validade, aprovação pendente e ciência') ?>
            <h3>14.1 Ciência dos colaboradores</h3>
            <ul>
                <li>Quando o documento exige ciência, cada usuário vê o aviso <strong>Leitura obrigatória</strong> e confirma com <strong>Li e estou ciente</strong>. A confirmação fica registrada com data e hora.</li>
                <li>O responsável acompanha quem já deu ciência e quem está pendente.</li>
                <li>Cada nova versão aprovada pede uma nova ciência.</li>
                <li>Colaboradores só veem as versões vigentes; rascunhos e versões em aprovação ficam com quem edita.</li>
            </ul>
            <?= $fig('politica-detalhe', 'Documento vigente com o registro de ciência e o acompanhamento por usuário') ?>
        </section>

        <section id="treinamentos">
            <h2>15. Treinamentos e certificados</h2>
            <p>O encarregado e o administrador criam treinamentos com <strong>conteúdo em texto</strong> e <strong>avaliação de múltipla escolha</strong>. Em cada um, definem:</p>
            <ul>
                <li>até 10 perguntas, com até 4 alternativas cada;</li>
                <li>a nota mínima para aprovação;</li>
                <li>a validade do certificado.</li>
            </ul>
            <p>O botão <strong>Usar treinamento modelo de LGPD</strong> cria um treinamento pronto, que você pode revisar e publicar: "Fundamentos da LGPD para colaboradores", com 5 perguntas.</p>
            <h3>15.1 Publicar e designar</h3>
            <ul>
                <li>O treinamento nasce como rascunho. Depois de <strong>publicado</strong>, pode ser designado a todos os usuários ativos, a setores ou a pessoas específicas, com prazo para concluir.</li>
                <li>A página do treinamento mostra, por participante, o prazo, as tentativas, a nota e a situação.</li>
                <li>O painel avisa sobre prazos vencidos.</li>
            </ul>
            <?= $fig('treinamentos', 'Treinamentos: indicadores, módulos e conclusão') ?>
            <h3>15.2 Fazer o treinamento</h3>
            <ol>
                <li>Cada usuário vê em <strong>Meus treinamentos</strong>, e no quadro <strong>Para você</strong> do painel, os treinamentos designados a ele.</li>
                <li>Leia o conteúdo e clique em <strong>Fazer a avaliação</strong>.</li>
                <li>Se não atingir a nota mínima, revise o conteúdo e tente de novo; não há limite de tentativas.</li>
                <li>Com a aprovação, o certificado é emitido na hora.</li>
            </ol>
            <?= $fig('treinamento-avaliacao', 'Avaliação de múltipla escolha') ?>
            <h3>15.3 Certificado</h3>
            <ul>
                <li>O certificado traz o nome, o treinamento, a empresa, a carga horária, o aproveitamento, as datas de conclusão e de validade e um <strong>código</strong> (formato ISIS-XXXX-XXXX).</li>
                <li>Pode ser impresso ou salvo em PDF.</li>
                <li>Qualquer pessoa pode conferir a autenticidade no endereço <span class="mono">/certificado/código</span>, que mostra só o necessário para a verificação.</li>
            </ul>
            <?= $fig('certificado', 'Certificado de conclusão com código de verificação') ?>
        </section>

        <section id="evidencias">
            <h2>16. Evidências</h2>
            <p>Evidências são documentos que comprovam o que foi feito, como um contrato assinado, a imagem de uma configuração ou uma lista de presença. Podem ser anexadas a ações, riscos, operações do inventário, solicitações de titulares, incidentes, fornecedores e políticas, no quadro <strong>Evidências</strong> de cada página.</p>
            <ul>
                <li><strong>Formatos aceitos:</strong> PDF, PNG, JPG, TXT, CSV, DOCX, XLSX, PPTX, ODT e ODS, até 10 MB. O ÍSIS confere o conteúdo real do arquivo, não só a extensão.</li>
                <li><strong>Integridade:</strong> cada arquivo guarda uma impressão digital (hash SHA-256), que permite comprovar que ele não foi alterado.</li>
                <li><strong>Quem pode anexar:</strong> quem pode alterar o registro. Nas ações, também o responsável; nos incidentes, também quem comunicou.</li>
                <li><strong>Registro:</strong> todo envio, download e exclusão fica na trilha de auditoria.</li>
            </ul>
        </section>

        <section id="usuarios">
            <h2>17. Usuários e convites</h2>
            <h3>17.1 Convidar</h3>
            <ol>
                <li>Em <strong>Usuários e perfis</strong>, preencha <strong>Convidar colaborador</strong>: nome, e-mail, perfil e, se quiser, o setor.</li>
                <li>A pessoa recebe um link válido por 7 dias, cria a própria senha e entra direto na empresa.</li>
                <li>Convites pendentes podem ser <strong>reenviados</strong> (com novo prazo) ou <strong>cancelados</strong>.</li>
            </ol>
            <?= $fig('usuarios', 'Usuários, convites pendentes e formulário de convite') ?>
            <p>Nesta versão, cada e-mail pertence a uma única empresa. Um e-mail que já tem conta no ÍSIS não pode ser convidado.</p>
            <h3>17.2 Alterar perfil, setor ou situação</h3>
            <p>Clique em <strong>Editar</strong> ao lado do usuário. Regras:</p>
            <ul>
                <li>ninguém altera o próprio perfil nem desativa a própria conta;</li>
                <li>a empresa precisa ter sempre pelo menos um administrador ativo;</li>
                <li>mudar o perfil ou desativar a conta encerra as sessões abertas da pessoa;</li>
                <li>contas de quem saiu da empresa devem ser desativadas no mesmo dia.</li>
            </ul>
        </section>

        <section id="configuracoes">
            <h2>18. Configurações da empresa</h2>
            <p>Disponível para o administrador:</p>
            <ul>
                <li><strong>Dados da empresa:</strong> razão social, nome fantasia e segmento. Para alterar o CNPJ, é preciso falar com o suporte.</li>
                <li><strong>Encarregado e canal de privacidade:</strong> nome e e-mail do encarregado e o e-mail público para os titulares.</li>
                <li><strong>Setores:</strong> criar, renomear e excluir. Excluir um setor deixa sem setor os usuários que estavam nele.</li>
            </ul>
            <?= $fig('configuracoes', 'Configurações da empresa e setores') ?>
        </section>

        <section id="auditoria">
            <h2>19. Trilha de auditoria</h2>
            <p>Registra automaticamente os acessos e as ações feitas no ÍSIS. Cada registro guarda:</p>
            <ul>
                <li>usuário, data e hora;</li>
                <li>IP;</li>
                <li>ação realizada;</li>
                <li>o que mudou, com o valor antes e depois. Senhas e códigos de acesso nunca aparecem.</li>
            </ul>
            <p>Os registros <strong>não podem ser editados nem excluídos</strong> pelo sistema.</p>
            <ul>
                <li><strong>Filtros:</strong> por ação, usuário e período. Eventos de segurança, como login recusado ou acesso negado, aparecem com o selo <span class="badge badge-danger">segurança</span>.</li>
                <li><strong>Exportar CSV:</strong> gera o arquivo com os filtros aplicados.</li>
                <li><strong>Verificar integridade:</strong> cada registro carrega o código (hash) do anterior, formando uma cadeia. A verificação confirma que nenhum registro foi alterado ou apagado diretamente no banco de dados. Se houver violação, o sistema indica a partir de qual registro.</li>
            </ul>
            <?= $fig('auditoria', 'Trilha de auditoria com filtros') ?>
        </section>

        <section id="perfil">
            <h2>20. Meu perfil</h2>
            <p>Em <strong>Meu perfil</strong>, cada usuário:</p>
            <ul>
                <li>atualiza o nome e o cargo;</li>
                <li>troca a senha, informando a atual;</li>
                <li>pode clicar em <strong>Sair de todos os outros dispositivos</strong>, o que é útil se esqueceu a sessão aberta em outro computador.</li>
            </ul>
            <p>Para trocar o e-mail, peça ao administrador.</p>
            <?= $fig('perfil', 'Meu perfil') ?>
        </section>

        <section id="duvidas">
            <h2>21. Dúvidas frequentes</h2>
            <dl class="faq">
                <dt>Não recebi o e-mail de confirmação, convite ou recuperação.</dt>
                <dd>Verifique o spam. Os links têm validade: confirmação em 48 horas, recuperação em 60 minutos e convite em 7 dias. Peça um novo link quando necessário.</dd>
                <dt>Apareceu "Acesso negado".</dt>
                <dd>Seu perfil não permite essa ação, ou o registro pertence a outro setor (no caso do gestor). Fale com o administrador ou com o encarregado.</dd>
                <dt>Apareceu "Sessão expirada" ao salvar.</dt>
                <dd>A sessão termina após 30 minutos sem uso. Entre novamente e refaça o envio.</dd>
                <dt>Fui bloqueado ao entrar.</dt>
                <dd>Aguarde 15 minutos ou use "Esqueci minha senha".</dd>
                <dt>O arquivo de evidência foi recusado.</dt>
                <dd>Confira o formato e o tamanho: até 10 MB, nos tipos listados na seção <a href="#evidencias">Evidências</a>.</dd>
                <dt>O titular diz que não encontra a solicitação no acompanhamento.</dt>
                <dd>O protocolo e o e-mail precisam ser exatamente os usados no pedido; maiúsculas não importam. Confirme também se ele está usando o endereço da empresa certa.</dd>
                <dt>O canal do titular mostra "não encontrado".</dt>
                <dd>O canal só funciona para empresas ativas que concluíram a configuração inicial. Confira o endereço em Configurações.</dd>
                <dt>Comuniquei um incidente e não vejo os detalhes da investigação.</dt>
                <dd>É esperado: quem comunica acompanha a situação; os detalhes ficam com o encarregado e o administrador.</dd>
                <dt>Posso usar o ÍSIS para várias empresas com o mesmo e-mail?</dt>
                <dd>Ainda não. Nesta versão, cada e-mail pertence a uma empresa.</dd>
                <dt>O ÍSIS garante que minha empresa está em conformidade?</dt>
                <dd>Não. O sistema organiza e documenta a gestão. As decisões jurídicas cabem à empresa e à sua assessoria.</dd>
            </dl>
        </section>

        <section id="proximos">
            <h2>22. Próximos módulos</h2>
            <p>Em desenvolvimento, nesta ordem:</p>
            <ul>
                <li><strong>Backups e relatórios.</strong></li>
            </ul>
        </section>

        <section id="historico">
            <h2>23. Histórico de alterações</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Versão</th><th>Data</th><th>Alterações</th></tr></thead>
                    <tbody>
                        <tr><td>0.4.0</td><td>29/10/2026</td><td>Fase 4, com três módulos novos:<br>• <strong>Fornecedores:</strong> avaliação de proteção de dados com nota e nível de risco, contratos com cláusulas de dados, vínculo com o inventário e revisão periódica.<br>• <strong>Políticas e documentos:</strong> rascunho, aprovação, versões, validade, ciência dos colaboradores e modelos iniciais.<br>• <strong>Treinamentos:</strong> conteúdo, avaliação, designação com prazo, certificado com código e verificação pública, além de um treinamento modelo de LGPD.<br>O painel ganhou o quadro "Para você" e novos alertas e indicadores.</td></tr>
                        <tr><td>0.3.0</td><td>29/10/2026</td><td>Fase 3, com dois módulos novos:<br>• <strong>Solicitações de titulares:</strong> protocolo, prazo de 15 dias, verificação de identidade, resposta por e-mail e dados criptografados.<br>• <strong>Incidentes de segurança:</strong> comunicação por qualquer colaborador, classificação, investigação criptografada, prazo de 3 dias úteis para a ANPD e registro das comunicações.<br>Também entraram o canal on-line do titular com acompanhamento por protocolo, ações corretivas ligadas a incidentes e novos alertas e indicadores no painel. Corrigida a exclusão de setores e de operações que tinham registros vinculados.</td></tr>
                        <tr><td>0.2.4</td><td>29/10/2026</td><td>Barras de rolagem finas e discretas nas telas, tabelas e no menu lateral; no menu, a barra só aparece ao passar o mouse.</td></tr>
                        <tr><td>0.2.3</td><td>29/10/2026</td><td>As telas passam a ocupar toda a largura disponível, inclusive com o menu lateral recolhido. Os indicadores se distribuem para preencher a linha.</td></tr>
                        <tr><td>0.2.2</td><td>29/10/2026</td><td>Nome do sistema grafado com acento, ÍSIS, em todas as telas, e-mails e documentos. Imagens do manual atualizadas.</td></tr>
                        <tr><td>0.2.1</td><td>29/10/2026</td><td>Menu lateral recolhível no computador, com lembrança da escolha. Atalho para o manual no menu do usuário. Manual ilustrado com 22 imagens das telas, feitas com dados fictícios.</td></tr>
                        <tr><td>0.2.0</td><td>29/10/2026</td><td>Inventário de dados pessoais, catálogos, exportação do registro das operações, gestão de riscos com matriz 5×5, planos de ação com histórico, evidências com hash e painel de conformidade completo. Escopo por setor para gestores e "Minhas tarefas" para colaboradores. Primeira edição deste manual.</td></tr>
                        <tr><td>0.1.0</td><td>29/10/2026</td><td>Cadastro da empresa, confirmação de e-mail, login com bloqueio de tentativas, recuperação de acesso, convites, perfis e permissões, configuração inicial por segmento, usuários, setores, perfil e trilha de auditoria com verificação de integridade.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </article>
</div>
