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
            <p class="muted">Versão <?= e(APP_VERSION) ?> · atualizado em 29/10/2026 · módulos das fases 1 e 2</p>
            <div class="disclaimer"><?= icon('info') ?><span>O ÍSIS apoia a organização da conformidade com a LGPD e <strong>não substitui análise jurídica especializada nem garante conformidade legal</strong>. As orientações automáticas exibidas no sistema servem de apoio ao preenchimento.</span></div>
        </header>

        <section id="apresentacao">
            <h2>1. Apresentação</h2>
            <p>O ÍSIS é um sistema on-line para implantar e manter a gestão da privacidade e da proteção de dados pessoais (LGPD) em empresas de qualquer segmento: saúde, comércio, educação, tecnologia, indústria, serviços e outros.</p>
            <p>Todas as empresas acessam pelo mesmo endereço, <strong>https://isis.aicode.dev.br</strong>. Cada empresa tem o próprio ambiente, com seus usuários, configurações e dados, totalmente separados das demais: ninguém de uma empresa vê ou altera informações de outra.</p>
            <?= $fig('inicio', 'Página inicial pública do ÍSIS') ?>
            <p><strong>Módulos disponíveis nesta versão:</strong> cadastro da empresa e usuários, perfis e permissões, configuração por segmento, painel de conformidade, inventário de dados pessoais, gestão de riscos, planos de ação, evidências e trilha de auditoria.</p>
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
            <p>É a tela inicial depois de entrar. Reúne, num só lugar, o que precisa de atenção:</p>
            <?= $fig('painel', 'Painel de conformidade da administradora (dados fictícios)') ?>
            <ul>
                <li><strong>Pendências:</strong> ações vencidas, riscos altos ou críticos em aberto, operações sem hipótese legal ou com revisão vencida, riscos e ações sem responsável. Cada item leva direto à lista filtrada.</li>
                <li><strong>Indicadores:</strong> operações ativas, riscos abertos (quantos altos ou críticos), ações em aberto (quantas vencidas) e percentual de execução dos planos.</li>
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

        <section id="evidencias">
            <h2>10. Evidências</h2>
            <p>Evidências são documentos que comprovam o que foi feito, como um contrato assinado, a imagem de uma configuração ou uma lista de presença. Podem ser anexadas a ações, riscos e operações do inventário, no quadro <strong>Evidências</strong> de cada página.</p>
            <ul>
                <li><strong>Formatos aceitos:</strong> PDF, PNG, JPG, TXT, CSV, DOCX, XLSX, PPTX, ODT e ODS, até 10 MB. O ÍSIS confere o conteúdo real do arquivo, não só a extensão.</li>
                <li><strong>Integridade:</strong> cada arquivo guarda uma impressão digital (hash SHA-256), que permite comprovar que ele não foi alterado.</li>
                <li><strong>Quem pode anexar:</strong> quem pode alterar o registro e, nas ações, o próprio responsável.</li>
                <li><strong>Registro:</strong> todo envio, download e exclusão fica na trilha de auditoria.</li>
            </ul>
        </section>

        <section id="usuarios">
            <h2>11. Usuários e convites</h2>
            <h3>11.1 Convidar</h3>
            <ol>
                <li>Em <strong>Usuários e perfis</strong>, preencha <strong>Convidar colaborador</strong>: nome, e-mail, perfil e, se quiser, o setor.</li>
                <li>A pessoa recebe um link válido por 7 dias, cria a própria senha e entra direto na empresa.</li>
                <li>Convites pendentes podem ser <strong>reenviados</strong> (com novo prazo) ou <strong>cancelados</strong>.</li>
            </ol>
            <?= $fig('usuarios', 'Usuários, convites pendentes e formulário de convite') ?>
            <p>Nesta versão, cada e-mail pertence a uma única empresa. Um e-mail que já tem conta no ÍSIS não pode ser convidado.</p>
            <h3>11.2 Alterar perfil, setor ou situação</h3>
            <p>Clique em <strong>Editar</strong> ao lado do usuário. Regras:</p>
            <ul>
                <li>ninguém altera o próprio perfil nem desativa a própria conta;</li>
                <li>a empresa precisa ter sempre pelo menos um administrador ativo;</li>
                <li>mudar o perfil ou desativar a conta encerra as sessões abertas da pessoa;</li>
                <li>contas de quem saiu da empresa devem ser desativadas no mesmo dia.</li>
            </ul>
        </section>

        <section id="configuracoes">
            <h2>12. Configurações da empresa</h2>
            <p>Disponível para o administrador:</p>
            <ul>
                <li><strong>Dados da empresa:</strong> razão social, nome fantasia e segmento. Para alterar o CNPJ, é preciso falar com o suporte.</li>
                <li><strong>Encarregado e canal de privacidade:</strong> nome e e-mail do encarregado e o e-mail público para os titulares.</li>
                <li><strong>Setores:</strong> criar, renomear e excluir. Excluir um setor deixa sem setor os usuários que estavam nele.</li>
            </ul>
            <?= $fig('configuracoes', 'Configurações da empresa e setores') ?>
        </section>

        <section id="auditoria">
            <h2>13. Trilha de auditoria</h2>
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
            <h2>14. Meu perfil</h2>
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
            <h2>15. Dúvidas frequentes</h2>
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
                <dt>Posso usar o ÍSIS para várias empresas com o mesmo e-mail?</dt>
                <dd>Ainda não. Nesta versão, cada e-mail pertence a uma empresa.</dd>
                <dt>O ÍSIS garante que minha empresa está em conformidade?</dt>
                <dd>Não. O sistema organiza e documenta a gestão. As decisões jurídicas cabem à empresa e à sua assessoria.</dd>
            </dl>
        </section>

        <section id="proximos">
            <h2>16. Próximos módulos</h2>
            <p>Em desenvolvimento, nesta ordem:</p>
            <ul>
                <li><strong>Solicitações de titulares:</strong> canal público para os pedidos previstos no art. 18, com protocolo, prazos e histórico.</li>
                <li><strong>Incidentes de segurança:</strong> registro, classificação, investigação, evidências, providências e prazos de comunicação.</li>
                <li><strong>Fornecedores:</strong> cadastro, avaliação, contratos e documentos de proteção de dados.</li>
                <li><strong>Políticas e documentos:</strong> versões, aprovações, validade e ciência dos colaboradores.</li>
                <li><strong>Treinamentos:</strong> módulos, avaliações, progresso e certificados.</li>
                <li><strong>Backups e relatórios.</strong></li>
            </ul>
        </section>

        <section id="historico">
            <h2>17. Histórico de alterações</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Versão</th><th>Data</th><th>Alterações</th></tr></thead>
                    <tbody>
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
