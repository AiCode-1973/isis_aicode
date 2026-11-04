<?php

declare(strict_types=1);

namespace Isis\Support;

/**
 * Ajuda dentro de cada tela: explicação em linguagem simples, passo a passo e, nos formulários,
 * cada campo com o que é, se é obrigatório e um exemplo de preenchimento.
 *
 * "Obrigatório" segue as regras de validação dos controllers: se uma regra mudar lá, mude aqui
 * (o teste HelpTest confere os campos obrigatórios dos principais formulários).
 *
 * Formato de um campo: nome do input => [rótulo, obrigatório?, explicação, exemplo|null].
 */
final class Help
{
    /** Endereço da tela => chave da ajuda (a primeira expressão que casar vale). */
    private const array ROUTES = [
        '#^/painel$#' => 'painel',
        '#^/tarefas$#' => 'tarefas',
        '#^/inventario$#' => 'inventario',
        '#^/inventario/catalogos$#' => 'catalogos',
        '#^/inventario/(nova|\d+/editar)$#' => 'inventario.form',
        '#^/inventario/\d+$#' => 'inventario.show',
        '#^/riscos$#' => 'riscos',
        '#^/riscos/(novo|\d+/editar)$#' => 'riscos.form',
        '#^/riscos/\d+$#' => 'riscos.show',
        '#^/ripd$#' => 'ripd',
        '#^/ripd/(novo|\d+/editar)$#' => 'ripd.form',
        '#^/ripd/\d+$#' => 'ripd.show',
        '#^/planos$#' => 'planos',
        '#^/planos/(nova|\d+/editar)$#' => 'planos.form',
        '#^/planos/\d+$#' => 'planos.show',
        '#^/titulares$#' => 'titulares',
        '#^/titulares/nova$#' => 'titulares.form',
        '#^/titulares/\d+$#' => 'titulares.show',
        '#^/incidentes$#' => 'incidentes',
        '#^/incidentes/novo$#' => 'incidentes.form',
        '#^/incidentes/\d+$#' => 'incidentes.show',
        '#^/fornecedores$#' => 'fornecedores',
        '#^/fornecedores/(novo|\d+/editar)$#' => 'fornecedores.form',
        '#^/fornecedores/\d+$#' => 'fornecedores.show',
        '#^/politicas$#' => 'politicas',
        '#^/politicas/(nova|\d+/editar)$#' => 'politicas.form',
        '#^/politicas/\d+$#' => 'politicas.show',
        '#^/treinamentos$#' => 'treinamentos',
        '#^/treinamentos/(novo|\d+/editar)$#' => 'treinamentos.form',
        '#^/treinamentos/\d+$#' => 'treinamentos.show',
        '#^/relatorios$#' => 'relatorios',
        '#^/usuarios$#' => 'usuarios',
        '#^/usuarios/\d+/editar$#' => 'usuarios.form',
        '#^/configuracoes$#' => 'configuracoes',
        '#^/auditoria$#' => 'auditoria',
        '#^/plano$#' => 'plano',
        '#^/perfil$#' => 'perfil',
    ];

    /** @return array{key: string, title: string, intro: string, steps: list<string>, fields: array<string, array{0: string, 1: bool, 2: string, 3: ?string}>, manual: ?string}|null */
    public static function forPath(string $path): ?array
    {
        $path = rtrim($path, '/') ?: '/';
        foreach (self::ROUTES as $pattern => $key) {
            if (preg_match($pattern, $path)) {
                return self::get($key);
            }
        }
        return null;
    }

    public static function get(string $key): ?array
    {
        $item = self::content()[$key] ?? null;
        if ($item === null) {
            return null;
        }
        return ['key' => $key] + $item + ['steps' => [], 'fields' => [], 'manual' => null];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::content());
    }

    /** @return array<string, array<string, mixed>> */
    private static function content(): array
    {
        $owner = ['Responsável', false, 'Pessoa da equipe que cuida do item e recebe os lembretes. Só aparecem usuários ativos.', 'Beatriz Lima (gestora da recepção)'];
        $department = ['Setor', false, 'Área da empresa a que o item pertence. Ajuda a filtrar e a dividir o trabalho.', 'Recepção e Atendimento'];

        return [
            // ------------------------------------------------------------ visão geral
            'painel' => [
                'title' => 'Painel de conformidade',
                'intro' => 'É o resumo de como está a proteção de dados da empresa. Mostra o que precisa de atenção agora e como o programa está evoluindo.',
                'steps' => [
                    'Comece pelo quadro Pendências: o grupo Urgente traz prazos vencidos e riscos graves.',
                    'O índice de maturidade (0 a 100) mostra o que já está organizado em cada pilar e o principal ponto a melhorar. É um indicador interno, não uma certificação.',
                    'Clique em qualquer indicador para abrir a lista correspondente.',
                    'Para ver tudo o que está pendente com o próximo passo de cada item, use "O que preciso fazer?".',
                ],
                'manual' => 'painel',
            ],
            'tarefas' => [
                'title' => 'O que preciso fazer?',
                'intro' => 'Lista única com tudo o que está pendente nos módulos, da tarefa mais urgente para a menos urgente. Cada linha diz o prazo, quem é o responsável e qual é o próximo passo.',
                'steps' => [
                    'Use "Só as minhas" para ver apenas o que está com você.',
                    'Clique na linha para abrir o registro e resolver a pendência. Quando ela for resolvida no módulo, sai daqui sozinha.',
                    'Itens "sem responsável" precisam de um dono: abra e defina quem cuida.',
                    'A prioridade combina o prazo (vencido é urgente) com a gravidade do item. É uma sugestão: a equipe decide a ordem final.',
                ],
                'manual' => 'central',
            ],
            'relatorios' => [
                'title' => 'Relatório de conformidade',
                'intro' => 'Documento com a situação do programa de privacidade num só lugar, para apresentar à diretoria, a um cliente ou a uma auditoria.',
                'steps' => [
                    'Confira as seções na tela e use "Imprimir ou salvar em PDF" para gerar o documento.',
                    'Os botões de exportação geram planilhas (CSV) do inventário, dos riscos, das ações e dos demais módulos.',
                    'O relatório mostra o que está registrado no ÍSIS: mantenha os módulos atualizados antes de gerar.',
                ],
                'manual' => 'relatorios',
            ],

            // ------------------------------------------------------------ inventário
            'inventario' => [
                'title' => 'Inventário de dados pessoais',
                'intro' => 'É o mapa de tudo o que a empresa faz com dados pessoais. Cada linha é uma "operação de tratamento": uma atividade que usa dados de pessoas, como agendar consultas ou fazer a folha de pagamento.',
                'steps' => [
                    'Cadastre uma operação por atividade, não por sistema. Ex.: "Agendamento de consultas", e não "Sistema X".',
                    'Comece pelas áreas que mais usam dados (atendimento, RH, financeiro).',
                    'Operações sem hipótese legal aparecem em destaque: complete-as primeiro.',
                    'Use Catálogos para manter as listas de tipos de dados, de titulares e de sistemas.',
                ],
                'manual' => 'inventario',
            ],
            'catalogos' => [
                'title' => 'Catálogos do inventário',
                'intro' => 'Listas reaproveitadas no cadastro das operações: tipos de dados, tipos de titulares (de quem são os dados) e sistemas ou locais onde os dados ficam.',
                'steps' => [
                    'Marque "Sensível" nos dados de saúde, biometria, origem racial, religião, opinião política, vida sexual ou filiação sindical (art. 5º, II).',
                    'Marque "Vulnerável" nos titulares que pedem cuidado extra, como crianças, adolescentes e idosos.',
                    '"Importar sugestões do segmento" acrescenta itens comuns na sua área, sem duplicar os existentes.',
                    'Excluir um item o remove das operações em que aparece.',
                ],
                'fields' => [
                    'name' => ['Nome do item', true, 'Nome curto e fácil de reconhecer.', 'Dados de convênio'],
                    'flag' => ['Sensível / Vulnerável', false, 'Destaca o item nas operações e no relatório.', null],
                    'hosting' => ['Hospedagem', false, 'Onde o sistema fica (só no catálogo de sistemas).', 'Nuvem / SaaS'],
                ],
                'manual' => 'catalogos',
            ],
            'inventario.form' => [
                'title' => 'Cadastro de operação de tratamento',
                'intro' => 'Descreva uma atividade que usa dados pessoais: para que serve, quais dados usa, de quem são, com base em que a lei permite e por quanto tempo os dados ficam guardados.',
                'steps' => [
                    'Preencha primeiro o nome, a finalidade e o papel da empresa: só isso já salva a operação como rascunho.',
                    'Depois marque os tipos de dados, os titulares e os sistemas usados.',
                    'Escolha a hipótese legal. Se houver dado sensível, escolha também a hipótese do art. 11.',
                    'Registre compartilhamentos, prazo de guarda e medidas de segurança. Quando estiver completa, mude a situação para Ativa.',
                ],
                'fields' => [
                    'name' => ['Nome da operação', true, 'Nome curto da atividade, como as pessoas da empresa a chamam.', 'Agendamento de consultas'],
                    'purpose' => ['Finalidade', true, 'Para que os dados são usados, em uma ou duas frases. Seja específico.', 'Marcar, confirmar e lembrar consultas dos pacientes.'],
                    'description' => ['Descrição', false, 'Como a atividade funciona no dia a dia: quem faz, em que momento e com quais ferramentas.', 'A recepção registra o paciente no sistema e envia lembrete por WhatsApp na véspera.'],
                    'controller_role' => ['Papel da empresa', true, 'Controladora decide como e por que os dados são usados. Operadora trata dados em nome de outra empresa.', 'Controladora'],
                    'department_id' => $department,
                    'owner_user_id' => $owner,
                    'data_categories[]' => ['Tipos de dados', false, 'Quais dados pessoais a atividade usa. Os marcados como sensíveis exigem a hipótese do art. 11.', 'Dados de identificação, Dados de contato, Dados de convênio'],
                    'subject_categories[]' => ['Titulares', false, 'De quem são os dados.', 'Pacientes, Responsáveis legais'],
                    'systems[]' => ['Sistemas e locais', false, 'Onde os dados ficam guardados ou passam.', 'Prontuário eletrônico (PEP), WhatsApp Business'],
                    'data_source' => ['Origem dos dados', false, 'De onde os dados vêm.', 'Fornecidos pelo próprio titular'],
                    'source_details' => ['Detalhe da origem', false, 'Complemento sobre a origem, se ajudar.', 'Ficha de cadastro preenchida na recepção'],
                    'subjects_volume' => ['Quantidade de titulares', false, 'Estimativa de quantas pessoas têm dados nessa atividade.', 'Até 10.000'],
                    'legal_basis_code' => ['Hipótese legal', false, 'O motivo previsto na LGPD (art. 7º) que permite usar os dados. Sem ela, a operação fica pendente.', 'Execução de contrato (art. 7º, V)'],
                    'sensitive_legal_basis_code' => ['Hipótese para dados sensíveis', false, 'Obrigatória na prática quando há dado sensível (art. 11).', 'Tutela da saúde (art. 11, II, "f")'],
                    'legal_basis_notes' => ['Justificativa', false, 'Por que essa hipótese se aplica. Ajuda em auditorias.', 'O agendamento é parte do serviço contratado pelo paciente.'],
                    'departments[]' => ['Setores envolvidos', false, 'Outras áreas que também usam esses dados.', 'Faturamento e Convênios'],
                    'retention_period' => ['Prazo de guarda', false, 'Por quanto tempo os dados ficam guardados e por quê.', '20 anos após o último atendimento (norma do conselho profissional)'],
                    'disposal_method' => ['Forma de descarte', false, 'Como os dados são eliminados ao fim do prazo.', 'Exclusão no sistema e fragmentação do papel'],
                    'international_transfer' => ['Transferência internacional', false, 'Marque se algum dado fica em servidor fora do Brasil.', null],
                    'international_details' => ['Detalhes da transferência', false, 'Para onde vão os dados e com que garantia.', 'Servidores nos EUA do provedor de e-mail, com cláusulas contratuais'],
                    'security_measures' => ['Medidas de segurança', false, 'O que protege os dados nessa atividade.', 'Senha individual, acesso por perfil, backup diário criptografado'],
                    'status' => ['Situação', true, 'Rascunho enquanto está incompleta; Ativa quando está em uso e completa.', 'Ativa'],
                    'review_due_date' => ['Próxima revisão', false, 'Data para conferir se a operação continua igual. O ÍSIS avisa quando chegar.', '30/06/2027'],
                ],
                'manual' => 'inventario',
            ],
            'inventario.show' => [
                'title' => 'Operação de tratamento',
                'intro' => 'Ficha completa de uma operação: dados usados, titulares, hipótese legal, compartilhamentos, riscos e ações ligados a ela.',
                'steps' => [
                    'O quadro de pendências no topo diz o que falta completar.',
                    'Use "Editar" para corrigir ou completar as informações.',
                    'Registre riscos e ações a partir daqui, para que fiquem ligados à operação.',
                    'Anexe evidências (contratos, termos de consentimento, prints de configuração) no quadro Evidências.',
                ],
                'manual' => 'inventario',
            ],

            // ------------------------------------------------------------ riscos
            'riscos' => [
                'title' => 'Gestão de riscos',
                'intro' => 'Lista do que pode dar errado com os dados pessoais e de como cada risco está sendo tratado. A matriz mostra os riscos pela probabilidade e pelo impacto.',
                'steps' => [
                    'Clique numa célula da matriz para ver os riscos daquela combinação.',
                    'Comece pelos riscos críticos e altos: eles pedem ações de tratamento.',
                    'Todo risco aberto deve ter um responsável e uma data de revisão.',
                ],
                'manual' => 'riscos',
            ],
            'riscos.form' => [
                'title' => 'Cadastro de risco',
                'intro' => 'Descreva um evento indesejado que pode afetar os dados pessoais, avalie a chance de acontecer e o estrago que causaria, e defina como ele será tratado.',
                'steps' => [
                    'Escreva o risco como um acontecimento: "Acesso indevido a prontuários", e não só "Prontuário".',
                    'Avalie probabilidade e impacto de 1 a 5 como estão hoje (risco inerente).',
                    'Escolha o tratamento. Se for aceitar o risco, a justificativa é obrigatória.',
                    'Depois de salvar, crie ações para reduzir o risco e, quando elas estiverem prontas, preencha o risco residual.',
                ],
                'fields' => [
                    'title' => ['Título', true, 'O que pode acontecer, em poucas palavras.', 'Envio de dados de pacientes por WhatsApp pessoal'],
                    'description' => ['Descrição', false, 'Contexto do risco: onde e como pode acontecer.', 'Recepcionistas usam o celular próprio para confirmar consultas.'],
                    'category' => ['Categoria', true, 'Tipo de risco, para agrupar e filtrar.', 'Segurança da informação'],
                    'activity_id' => ['Operação relacionada', false, 'Operação do inventário afetada pelo risco.', 'OP-001 · Agendamento de consultas'],
                    'department_id' => $department,
                    'cause' => ['Causa', false, 'Por que o risco existe.', 'Falta de WhatsApp corporativo'],
                    'consequence' => ['Consequência', false, 'O que acontece se o risco se concretizar.', 'Exposição de dados de saúde e perda de controle sobre as conversas'],
                    'likelihood' => ['Probabilidade', true, 'Chance de acontecer, de 1 (rara) a 5 (quase certa).', '4 · Provável'],
                    'impact' => ['Impacto', true, 'Tamanho do dano, de 1 (insignificante) a 5 (gravíssimo).', '4 · Grave'],
                    'residual_likelihood' => ['Probabilidade residual', false, 'Chance depois das medidas. Preencha junto com o impacto residual.', '2 · Improvável'],
                    'residual_impact' => ['Impacto residual', false, 'Dano depois das medidas.', '3 · Moderado'],
                    'treatment' => ['Tratamento', true, 'Mitigar (reduzir), aceitar, transferir (ex.: seguro) ou evitar (parar a atividade).', 'Mitigar'],
                    'treatment_notes' => ['Justificativa do tratamento', false, 'Obrigatória quando o risco é aceito.', 'Adotar WhatsApp corporativo e proibir o uso de celular pessoal.'],
                    'status' => ['Situação', true, 'Em que ponto está o tratamento.', 'Em tratamento'],
                    'owner_user_id' => $owner,
                    'review_date' => ['Próxima revisão', false, 'Quando reavaliar o risco.', '31/03/2027'],
                ],
                'manual' => 'riscos',
            ],
            'riscos.show' => [
                'title' => 'Risco',
                'intro' => 'Detalhes do risco, com o nível antes e depois das medidas, as ações de tratamento e o histórico.',
                'steps' => [
                    'Crie ações a partir daqui para que fiquem ligadas ao risco.',
                    'Quando as ações forem concluídas, edite o risco e informe o risco residual.',
                    'Anexe evidências das medidas adotadas.',
                ],
                'manual' => 'riscos',
            ],

            // ------------------------------------------------------------ RIPD
            'ripd' => [
                'title' => 'Relatórios de impacto (RIPD)',
                'intro' => 'O relatório de impacto descreve tratamentos que podem gerar alto risco aos titulares e as medidas para reduzir esse risco. A ANPD pode pedir esse documento (art. 38).',
                'steps' => [
                    'A lista "Operações indicadas" mostra o que provavelmente precisa de relatório: dados sensíveis, crianças, grande volume e outros sinais.',
                    'Um relatório pode cobrir várias operações parecidas.',
                    'Depois de aprovado, a versão fica congelada; mudanças geram uma nova versão.',
                ],
                'manual' => 'ripd',
            ],
            'ripd.form' => [
                'title' => 'Relatório de impacto',
                'intro' => 'Escolha as operações cobertas e os motivos que levam ao relatório. O conteúdo de cada seção é preenchido na tela do relatório, depois de salvar.',
                'fields' => [
                    'title' => ['Título', true, 'Nome do relatório.', 'RIPD · Prontuário eletrônico e exames'],
                    'activity_ids[]' => ['Operações cobertas', false, 'Operações do inventário analisadas neste relatório.', 'OP-002 · Prontuário eletrônico'],
                    'triggers[]' => ['Motivos', false, 'Por que o relatório é necessário.', 'Dados sensíveis em larga escala'],
                    'owner_user_id' => $owner,
                    'conclusion' => ['Conclusão', false, 'Preencha ao final: o risco remanescente é aceitável?', 'Aceitável com as medidas previstas'],
                    'review_due_date' => ['Próxima revisão', false, 'Quando revisar o relatório.', '30/11/2027'],
                ],
                'manual' => 'ripd',
            ],
            'ripd.show' => [
                'title' => 'Relatório de impacto',
                'intro' => 'Preencha cada seção do relatório, envie para aprovação e gere o documento para impressão.',
                'steps' => [
                    'Preencha as seções na ordem: descrição, necessidade e proporcionalidade, riscos e medidas.',
                    'Envie para aprovação quando estiver completo. O encarregado aprova ou devolve.',
                    'Use "Documento" para imprimir ou salvar em PDF.',
                ],
                'manual' => 'ripd',
            ],

            // ------------------------------------------------------------ ações
            'planos' => [
                'title' => 'Planos de ação',
                'intro' => 'Tarefas para melhorar a proteção de dados, cada uma com responsável, prazo e evidências. Normalmente nascem de um risco, de uma operação ou de um incidente.',
                'steps' => [
                    'Use os filtros "Só vencidas" e "Só as minhas" para focar.',
                    'Abra a ação para registrar o andamento e anexar a evidência quando concluir.',
                ],
                'manual' => 'planos',
            ],
            'planos.form' => [
                'title' => 'Cadastro de ação',
                'intro' => 'Uma ação é uma tarefa concreta, com dono e prazo. Escreva o que deve ser feito de forma que qualquer pessoa entenda quando está pronto.',
                'fields' => [
                    'title' => ['Título', true, 'O que fazer, começando por um verbo.', 'Revisar os perfis de acesso ao prontuário eletrônico'],
                    'description' => ['Descrição', false, 'Detalhes e critério de conclusão.', 'Remover acessos de ex-colaboradores e limitar a recepção à agenda.'],
                    'risk_id' => ['Risco relacionado', false, 'Risco que a ação ajuda a reduzir.', 'RSK-001 · Acesso indevido a prontuários'],
                    'activity_id' => ['Operação relacionada', false, 'Operação do inventário ligada à ação.', null],
                    'incident_id' => ['Incidente relacionado', false, 'Incidente que originou a ação.', null],
                    'department_id' => $department,
                    'owner_user_id' => ['Responsável', false, 'Quem executa. Recebe lembretes por e-mail antes do prazo.', 'Beatriz Lima'],
                    'priority' => ['Prioridade', true, 'Alta para o que reduz riscos graves ou atende exigência legal.', 'Alta'],
                    'status' => ['Situação', true, 'Pendente ao criar; atualize conforme avança.', 'Pendente'],
                    'due_date' => ['Prazo', false, 'Data-limite para concluir. Sem prazo, a ação não entra nos alertas.', '15/12/2026'],
                ],
                'manual' => 'planos',
            ],
            'planos.show' => [
                'title' => 'Ação',
                'intro' => 'Acompanhe a ação: registre o que foi feito, mude a situação e anexe a evidência ao concluir.',
                'fields' => [
                    'status' => ['Situação', true, 'Em andamento enquanto executa; Concluída quando terminar.', 'Concluída'],
                    'note' => ['Observação', false, 'O que foi feito ou o que falta. Obrigatória se a situação não mudar.', 'Perfis revisados; 3 acessos de ex-colaboradores removidos.'],
                ],
                'manual' => 'planos',
            ],

            // ------------------------------------------------------------ titulares
            'titulares' => [
                'title' => 'Solicitações de titulares',
                'intro' => 'Pedidos de pessoas sobre os próprios dados: acesso, correção, eliminação, entre outros (art. 18). A lei pede resposta em até 15 dias.',
                'steps' => [
                    'Pedidos feitos pelo canal on-line entram aqui sozinhos; os recebidos por telefone ou e-mail são registrados em "Nova solicitação".',
                    'Confirme a identidade antes de entregar ou alterar dados.',
                    'Fique de olho na coluna Prazo: os vencidos aparecem em vermelho.',
                ],
                'manual' => 'titulares',
            ],
            'titulares.form' => [
                'title' => 'Registrar solicitação de titular',
                'intro' => 'Use para pedidos recebidos fora do canal on-line (telefone, e-mail, presencial). O prazo de 15 dias conta a partir da data de recebimento.',
                'fields' => [
                    'right_type' => ['Tipo de pedido', true, 'O que a pessoa pediu.', 'Acesso aos dados'],
                    'channel' => ['Canal', true, 'Por onde o pedido chegou.', 'Telefone'],
                    'requester_relation' => ['Quem pede', true, 'O próprio titular ou um representante (pai, mãe, procurador).', 'Titular'],
                    'requester_name' => ['Nome do titular', true, 'Nome completo da pessoa dona dos dados. Fica criptografado.', 'Ana Paula Ribeiro'],
                    'requester_email' => ['E-mail', false, 'Para responder. Informe e-mail ou telefone: ao menos um é obrigatório.', 'ana.ribeiro@email.com'],
                    'requester_phone' => ['Telefone', false, 'Outra forma de contato.', '(11) 98888-7777'],
                    'requester_document' => ['Documento', false, 'Ajuda a confirmar a identidade.', 'CPF 123.456.789-00'],
                    'description' => ['Descrição do pedido', false, 'O que a pessoa disse, com as palavras dela.', 'Quer cópia dos dados cadastrais e do histórico de consultas.'],
                    'received_at' => ['Data de recebimento', false, 'Quando o pedido chegou. Em branco, vale hoje. Não pode ser no futuro.', '03/11/2026'],
                    'activity_id' => ['Operação relacionada', false, 'Operação do inventário envolvida no pedido.', 'OP-002 · Prontuário eletrônico'],
                ],
                'manual' => 'titulares',
            ],
            'titulares.show' => [
                'title' => 'Atendimento da solicitação',
                'intro' => 'Conduza o pedido até a resposta: defina quem atende, confirme a identidade, registre o andamento e envie a resposta final.',
                'steps' => [
                    'Defina o responsável e confirme a identidade do titular.',
                    'Registre cada providência; marque "Mostrar ao titular" para que ele acompanhe pelo canal.',
                    'Responda até o prazo. A resposta pode ser enviada por e-mail ao titular.',
                ],
                'fields' => [
                    'assigned_user_id' => ['Responsável pelo atendimento', false, 'Quem conduz o pedido.', 'Rafael Nunes (encarregado)'],
                    'identity_verified' => ['Identidade confirmada', false, 'Marque depois de conferir que a pessoa é mesmo o titular.', null],
                    'identity_notes' => ['Como a identidade foi confirmada', false, 'Registro do que foi conferido, sem copiar documentos.', 'Conferido CPF e data de nascimento por telefone.'],
                    'status' => ['Situação', true, 'Etapa atual do atendimento.', 'Em atendimento'],
                    'note' => ['Observação', false, 'Providência tomada. Obrigatória se a situação não mudar.', 'Dados extraídos do sistema; aguardando revisão.'],
                    'response' => ['Resposta ao titular', true, 'Texto final, claro e completo (mínimo de 10 caracteres).', 'Segue em anexo a cópia dos seus dados cadastrais e do histórico de consultas.'],
                    'final_status' => ['Resultado', true, 'Respondida (atendida) ou indeferida (com o motivo na resposta).', 'Respondida'],
                ],
                'manual' => 'titulares',
            ],

            // ------------------------------------------------------------ incidentes
            'incidentes' => [
                'title' => 'Incidentes de segurança',
                'intro' => 'Registro de eventos que expuseram ou podem ter exposto dados pessoais: vazamentos, acessos indevidos, perdas. Incidentes com risco relevante devem ser comunicados à ANPD e aos titulares (art. 48).',
                'steps' => [
                    'Qualquer pessoa da equipe pode comunicar um incidente; o encarregado faz a triagem.',
                    'Comunique logo, mesmo sem ter todos os detalhes: o prazo da ANPD conta a partir de quando a empresa soube.',
                ],
                'manual' => 'incidentes',
            ],
            'incidentes.form' => [
                'title' => 'Comunicar incidente',
                'intro' => 'Conte o que aconteceu com as informações que você tem agora. Não precisa saber tudo: o encarregado completa a análise.',
                'fields' => [
                    'title' => ['Título', true, 'Resumo do ocorrido (mínimo de 5 caracteres).', 'Planilha de pacientes enviada a destinatário errado'],
                    'category' => ['Tipo', true, 'O que mais se aproxima do ocorrido.', 'Erro humano (envio errado, descarte inadequado)'],
                    'description' => ['O que aconteceu', true, 'Conte o que houve, quem percebeu e o que já foi feito (mínimo de 10 caracteres).', 'Às 10h a recepção enviou por e-mail a planilha do dia a um fornecedor por engano. O e-mail não foi recolhido.'],
                    'detected_at' => ['Quando foi percebido', true, 'Data e hora em que alguém notou o problema. Não pode ser no futuro.', '03/11/2026 10:30'],
                    'occurred_at' => ['Quando ocorreu', false, 'Se souber, quando o problema começou.', '03/11/2026 10:00'],
                    'personal_data' => ['Há dados pessoais envolvidos?', true, 'Se não souber, escolha "Não sei".', 'Sim'],
                    'department_id' => $department,
                ],
                'manual' => 'incidentes',
            ],
            'incidentes.show' => [
                'title' => 'Tratamento do incidente',
                'intro' => 'Classifique o incidente, registre a investigação e as providências e, se houver risco relevante aos titulares, comunique a ANPD e os titulares.',
                'steps' => [
                    'Classifique a gravidade e o risco aos titulares. Risco relevante abre o prazo de comunicação à ANPD.',
                    'Registre contenção, causa e lições aprendidas (ficam criptografadas).',
                    'Registre a data e o protocolo da comunicação à ANPD antes de encerrar.',
                ],
                'fields' => [
                    'title' => ['Título', true, 'Resumo do ocorrido.', null],
                    'category' => ['Tipo', true, 'Classificação do incidente.', 'Vazamento ou divulgação indevida'],
                    'severity' => ['Gravidade', true, 'Tamanho do problema para a empresa e os titulares.', 'Alta'],
                    'personal_data' => ['Há dados pessoais?', true, 'Confirme após a análise.', 'Sim'],
                    'subjects_affected' => ['Titulares afetados', false, 'Quantidade estimada de pessoas.', '120'],
                    'risk_to_subjects' => ['Risco aos titulares', true, '"Relevante" quando pode causar dano a eles; exige comunicação à ANPD.', 'Relevante'],
                    'owner_user_id' => $owner,
                    'containment' => ['Contenção', false, 'O que foi feito para parar o problema.', 'Pedido de exclusão ao destinatário, confirmado por escrito.'],
                    'root_cause' => ['Causa', false, 'Por que aconteceu.', 'Preenchimento automático do e-mail com o endereço errado.'],
                    'lessons' => ['Lições aprendidas', false, 'O que muda para não repetir.', 'Planilhas passam a ser enviadas só por link com senha.'],
                    'status' => ['Situação', true, 'Etapa do tratamento.', 'Contido'],
                    'note' => ['Providência', false, 'O que foi feito. Obrigatória se a situação não mudar.', null],
                    'anpd_notified_at' => ['Comunicação à ANPD', false, 'Data em que a ANPD foi comunicada.', '05/11/2026'],
                    'anpd_protocol' => ['Protocolo na ANPD', false, 'Número recebido na comunicação.', '00000.000000/2026-00'],
                    'subjects_notified_at' => ['Comunicação aos titulares', false, 'Data em que os afetados foram avisados.', '05/11/2026'],
                ],
                'manual' => 'incidentes',
            ],

            // ------------------------------------------------------------ fornecedores
            'fornecedores' => [
                'title' => 'Fornecedores',
                'intro' => 'Empresas e profissionais que recebem ou acessam dados pessoais em nome da empresa: sistemas, contabilidade, laboratórios, agências. A empresa responde junto por eles.',
                'steps' => [
                    'Cadastre primeiro quem acessa dados sensíveis ou muitos dados.',
                    'Aplique a avaliação e registre o contrato com cláusulas de proteção de dados.',
                ],
                'manual' => 'fornecedores',
            ],
            'fornecedores.form' => [
                'title' => 'Cadastro de fornecedor',
                'intro' => 'Identifique o fornecedor, o serviço prestado e quais dados ele acessa. A avaliação e os contratos são registrados na ficha, depois de salvar.',
                'fields' => [
                    'name' => ['Nome', true, 'Razão social ou nome conhecido.', 'NuvemSaúde Sistemas Ltda.'],
                    'cnpj' => ['CNPJ', false, 'Se for empresa brasileira.', '12.345.678/0001-90'],
                    'service' => ['Serviço', false, 'O que o fornecedor faz para a empresa.', 'Prontuário eletrônico em nuvem'],
                    'role' => ['Papel', true, 'Operador trata dados em nome da empresa; controlador decide sobre os dados que recebe.', 'Operador'],
                    'country' => ['País', false, 'Onde o fornecedor guarda os dados.', 'Brasil'],
                    'data_access' => ['Acessa dados pessoais', false, 'Marque se o fornecedor vê ou guarda dados pessoais.', null],
                    'sensitive_data' => ['Acessa dados sensíveis', false, 'Marque se inclui saúde, biometria e outros do art. 5º, II.', null],
                    'international' => ['Dados fora do Brasil', false, 'Marque se os dados ficam em servidor no exterior.', null],
                    'activities[]' => ['Operações atendidas', false, 'Operações do inventário em que o fornecedor participa.', 'OP-002 · Prontuário eletrônico'],
                    'contact_name' => ['Contato', false, 'Pessoa de referência no fornecedor.', 'Carla Mendes'],
                    'contact_email' => ['E-mail do contato', false, 'Para avisos e incidentes.', 'privacidade@nuvemsaude.example'],
                    'contact_phone' => ['Telefone do contato', false, null, '(11) 4000-1234'],
                    'status' => ['Situação', true, 'Em avaliação até concluir a análise; Ativo quando contratado.', 'Ativo'],
                    'owner_user_id' => $owner,
                    'department_id' => $department,
                    'next_review_date' => ['Próxima reavaliação', false, 'Quando reavaliar o fornecedor.', '31/12/2026'],
                    'notes' => ['Observações', false, null, 'Contrato renovado anualmente em janeiro.'],
                ],
                'manual' => 'fornecedores',
            ],
            'fornecedores.show' => [
                'title' => 'Ficha do fornecedor',
                'intro' => 'Avaliação, contratos e operações do fornecedor. O nível de risco vem da avaliação.',
                'steps' => [
                    'Responda a avaliação para calcular a nota e o nível de risco.',
                    'Registre os contratos e marque se têm cláusulas de proteção de dados.',
                ],
                'fields' => [
                    'title' => ['Título do contrato', true, 'Nome do contrato ou aditivo.', 'Contrato de prestação de serviços 2026'],
                    'reference' => ['Número ou referência', false, null, 'CT-2026-014'],
                    'start_date' => ['Início', false, null, '01/01/2026'],
                    'end_date' => ['Fim', false, 'Não pode ser antes do início. O ÍSIS avisa 30 dias antes.', '31/12/2026'],
                    'has_data_clauses' => ['Tem cláusulas de proteção de dados', false, 'Marque se o contrato trata de sigilo, segurança, incidentes e devolução dos dados.', null],
                    'notes' => ['Observações', false, 'Detalhes que ajudem a localizar ou entender o contrato.', 'Aditivo de proteção de dados assinado em 10/03/2026.'],
                ],
                'manual' => 'fornecedores',
            ],

            // ------------------------------------------------------------ políticas
            'politicas' => [
                'title' => 'Políticas e documentos',
                'intro' => 'Documentos internos de privacidade e segurança, com versões, aprovação, validade e ciência da equipe.',
                'steps' => [
                    'Comece pelos modelos do ÍSIS e adapte à realidade da empresa.',
                    'Uma versão nova só vale depois de aprovada; a anterior fica guardada.',
                ],
                'manual' => 'politicas',
            ],
            'politicas.form' => [
                'title' => 'Política ou documento',
                'intro' => 'Escreva o texto, defina o tipo, o prazo de revisão e se a equipe precisa dar ciência. Ao salvar, a versão vai para aprovação.',
                'fields' => [
                    'title' => ['Título', true, 'Nome do documento.', 'Política de Segurança da Informação'],
                    'category' => ['Tipo', true, null, 'Segurança da informação'],
                    'owner_user_id' => $owner,
                    'review_months' => ['Revisar a cada', true, 'De quanto em quanto tempo a política deve ser revista.', '12 meses'],
                    'requires_ack' => ['Exigir ciência da equipe', false, 'Cada pessoa precisa confirmar que leu.', null],
                    'content' => ['Texto', true, 'Conteúdo completo da política (mínimo de 20 caracteres).', null],
                    'change_summary' => ['O que mudou', false, 'Resumo da alteração nesta versão.', 'Inclusão das regras de uso do WhatsApp corporativo.'],
                ],
                'manual' => 'politicas',
            ],
            'politicas.show' => [
                'title' => 'Política',
                'intro' => 'Texto vigente, versões, aprovação e quem já deu ciência.',
                'steps' => [
                    'Se a política pedir ciência, leia e clique em "Li e estou ciente".',
                    'Quem aprova vê aqui as versões aguardando decisão.',
                ],
                'fields' => [
                    'notes' => ['Comentário da decisão', false, 'Explique o motivo ao devolver uma versão.', 'Ajustar o item 4 sobre senhas.'],
                ],
                'manual' => 'politicas',
            ],

            // ------------------------------------------------------------ treinamentos
            'treinamentos' => [
                'title' => 'Treinamentos',
                'intro' => 'Cursos curtos com avaliação e certificado. Quem gerencia cria os módulos e designa as pessoas; cada pessoa faz os seus.',
                'manual' => 'treinamentos',
            ],
            'treinamentos.form' => [
                'title' => 'Módulo de treinamento',
                'intro' => 'Monte o conteúdo, a avaliação e as regras do certificado.',
                'fields' => [
                    'title' => ['Título', true, null, 'Fundamentos da LGPD para colaboradores'],
                    'summary' => ['Resumo', false, 'Uma frase sobre o que a pessoa vai aprender.', 'O que são dados pessoais e como protegê-los no dia a dia.'],
                    'content' => ['Conteúdo', true, 'Texto do treinamento (mínimo de 50 caracteres).', null],
                    'duration_minutes' => ['Duração (minutos)', true, 'Tempo estimado para ler e fazer a avaliação.', '20'],
                    'passing_score' => ['Nota mínima', true, 'De 1 a 100. Abaixo dela, a pessoa refaz.', '70'],
                    'certificate_months' => ['Validade do certificado', true, 'Depois desse prazo, o treinamento deve ser refeito.', '12 meses'],
                ],
                'manual' => 'treinamentos',
            ],
            'treinamentos.show' => [
                'title' => 'Treinamento',
                'intro' => 'Conteúdo do módulo, sua situação e, para quem gerencia, as pessoas designadas.',
                'steps' => [
                    'Leia o conteúdo e clique em "Fazer a avaliação".',
                    'Com a nota mínima, o certificado fica disponível para baixar.',
                ],
                'manual' => 'treinamentos',
            ],

            // ------------------------------------------------------------ governança e conta
            'usuarios' => [
                'title' => 'Usuários e perfis',
                'intro' => 'Pessoas da empresa que usam o ÍSIS. O perfil define o que cada uma pode ver e fazer.',
                'steps' => [
                    'Convide pelo e-mail: a pessoa recebe um link para criar a senha.',
                    'Escolha o menor perfil que permite o trabalho dela.',
                ],
                'fields' => [
                    'name' => ['Nome', true, 'Nome completo da pessoa.', 'Diego Ramos'],
                    'email' => ['E-mail', true, 'E-mail de trabalho. Não pode ter conta no ÍSIS.', 'diego.ramos@suaempresa.com.br'],
                    'role' => ['Perfil', true, 'Administrador, encarregado, gestor, colaborador ou auditor.', 'Gestor'],
                    'department_id' => $department,
                ],
                'manual' => 'usuarios',
            ],
            'usuarios.form' => [
                'title' => 'Editar usuário',
                'fields' => [
                    'name' => ['Nome', true, null, 'Diego Ramos'],
                    'job_title' => ['Cargo', false, null, 'Coordenador de TI'],
                    'role' => ['Perfil', true, 'O que a pessoa pode fazer no sistema.', 'Gestor'],
                    'department_id' => $department,
                    'status' => ['Situação', true, 'Inativo bloqueia o acesso sem apagar o histórico.', 'Ativo'],
                ],
                'intro' => 'Ajuste o perfil, o setor e a situação da pessoa.',
                'manual' => 'usuarios',
            ],
            'configuracoes' => [
                'title' => 'Configurações da empresa',
                'intro' => 'Dados da empresa, encarregado, canal de privacidade, política de verificação em duas etapas e setores.',
                'fields' => [
                    'legal_name' => ['Razão social', true, null, 'Clínica Vida Plena Ltda.'],
                    'trade_name' => ['Nome fantasia', false, 'Nome usado nas telas e e-mails.', 'Clínica Vida Plena'],
                    'segment_code' => ['Segmento', true, 'Define as sugestões e orientações.', 'Saúde'],
                    'dpo_name' => ['Encarregado', true, 'Pessoa ou serviço que responde pela proteção de dados (art. 41).', 'Rafael Nunes'],
                    'dpo_email' => ['E-mail do encarregado', true, null, 'encarregado@vidaplena.example'],
                    'privacy_email' => ['E-mail do canal de privacidade', false, 'E-mail público para titulares. Em branco, usa o do encarregado.', 'privacidade@vidaplena.example'],
                    'mfa_policy' => ['Verificação em duas etapas', true, 'Para quem a verificação é obrigatória.', 'Obrigatória para administradores e encarregados'],
                ],
                'manual' => 'configuracoes',
            ],
            'auditoria' => [
                'title' => 'Trilha de auditoria',
                'intro' => 'Registro de quem fez o quê e quando. Não pode ser alterado; a integridade é conferida por encadeamento.',
                'steps' => ['Filtre por pessoa, tipo de ação ou período e exporte em CSV quando precisar comprovar algo.'],
                'manual' => 'auditoria',
            ],
            'plano' => [
                'title' => 'Plano e assinatura',
                'intro' => 'Plano contratado, limites de uso e faturas.',
                'manual' => 'plano',
            ],
            'perfil' => [
                'title' => 'Meu perfil',
                'intro' => 'Seus dados, sua senha e a verificação em duas etapas.',
                'fields' => [
                    'name' => ['Seu nome', true, null, 'Mariana Costa'],
                    'job_title' => ['Cargo', false, null, 'Gerente administrativa'],
                    'current_password' => ['Senha atual', true, 'Para trocar a senha.', null],
                    'password' => ['Nova senha', true, 'De 10 a 128 caracteres, com letras maiúsculas, minúsculas e números, diferente da atual.', null],
                    'password_confirmation' => ['Confirmação', true, 'Repita a nova senha.', null],
                ],
                'manual' => 'perfil',
            ],
        ];
    }
}
