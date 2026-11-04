<?php

declare(strict_types=1);

namespace Isis\Support;

use Isis\Auth\Auth;
use Isis\Auth\Permission as P;

/**
 * Modelos prontos por situação: exemplos editáveis que preenchem o formulário do módulo.
 * Nada é gravado ao escolher um modelo; a pessoa revisa, ajusta e salva. São pontos de partida,
 * não uma avaliação jurídica: hipóteses legais, prazos e textos devem ser conferidos pela empresa.
 *
 * Tipos: inventario (operação), risco, plano (pacote de ações), resposta (a titulares) e incidente.
 * "segments" vazio = serve para qualquer segmento.
 */
final class ModelLibrary
{
    public const array TYPES = [
        'inventario' => ['Inventário', 'Operações de tratamento comuns, com finalidade, hipótese legal e prazo de guarda sugeridos.', 'database', P::INVENTORY_EDIT],
        'risco' => ['Avaliação de risco', 'Riscos frequentes, com causa, consequência, nível sugerido e tratamento.', 'target', P::RISKS_EDIT],
        'plano' => ['Plano de ação', 'Pacotes de ações para resolver um problema, com prioridade e prazo sugeridos.', 'check', P::ACTIONS_EDIT],
        'resposta' => ['Resposta a titulares', 'Textos de resposta para cada tipo de pedido, com o nome, o protocolo e a empresa já preenchidos.', 'inbox', P::REQUESTS_MANAGE],
        'incidente' => ['Registro de incidente', 'Roteiros para comunicar os incidentes mais comuns sem esquecer nada.', 'siren', P::INCIDENTS_REPORT],
    ];

    /** Aviso que acompanha os textos de resposta (sai do texto final enviado só se a pessoa apagar). */
    public const string RESPONSE_NOTICE = 'Revise o texto antes de enviar: complete os trechos entre colchetes e confira se a resposta corresponde ao que foi feito.';

    /** @return list<array<string, mixed>> */
    public static function ofType(string $type, ?string $segment = null): array
    {
        $models = array_values(array_filter(self::all(), static fn (array $m): bool => $m['type'] === $type));
        if ($segment === null) {
            return $models;
        }
        // Do segmento primeiro, depois os gerais; os de outros segmentos ficam de fora.
        $mine = array_filter($models, static fn (array $m): bool => in_array($segment, $m['segments'], true));
        $general = array_filter($models, static fn (array $m): bool => $m['segments'] === []);
        return array_values([...$mine, ...$general]);
    }

    public static function find(string $type, ?string $key): ?array
    {
        foreach (self::all() as $m) {
            if ($m['type'] === $type && $m['key'] === $key) {
                return $m;
            }
        }
        return null;
    }

    /** Tipos que o perfil atual pode usar. @return array<string, array{0: string, 1: string, 2: string, 3: string}> */
    public static function allowedTypes(): array
    {
        return array_filter(self::TYPES, static fn (array $t): bool => Auth::can($t[3]));
    }

    /**
     * Texto de resposta com os dados do pedido no lugar das marcas {nome}, {protocolo}...
     *
     * @param array<string, string> $values
     */
    public static function fill(string $text, array $values): string
    {
        return strtr($text, array_combine(array_map(static fn (string $k): string => '{' . $k . '}', array_keys($values)), array_values($values)));
    }

    /**
     * Liga os nomes do modelo aos itens do catálogo da empresa (sem diferenciar maiúsculas e ignorando o detalhe entre parênteses).
     *
     * @param array<int, array{name: string}> $catalog id => item
     * @param list<string> $names
     * @return array{0: list<array{id: int}>, 1: list<string>} [itens encontrados, nomes que faltam]
     */
    public static function matchCatalog(array $catalog, array $names): array
    {
        $base = static fn (string $n): string => mb_strtolower(trim((string) preg_replace('/\s*\(.*$/u', '', $n)));
        $index = [];
        foreach ($catalog as $id => $item) {
            $index[mb_strtolower($item['name'])] ??= $id;
            $index[$base($item['name'])] ??= $id;
        }
        $found = [];
        $missing = [];
        foreach ($names as $name) {
            $id = $index[mb_strtolower($name)] ?? $index[$base($name)] ?? null;
            if ($id === null) {
                $missing[] = $name;
            } elseif (!in_array($id, array_column($found, 'id'), true)) {
                $found[] = ['id' => $id];
            }
        }
        return [$found, $missing];
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        $models = [];
        foreach (self::definitions() as $type => $items) {
            foreach ($items as $key => $m) {
                $models[] = ['key' => $key, 'type' => $type] + $m + ['segments' => []];
            }
        }
        return $models;
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    private static function definitions(): array
    {
        return [
            // ================================================================ inventário
            'inventario' => [
                'cadastro-clientes' => [
                    'title' => 'Cadastro de clientes',
                    'situation' => 'Você guarda nome, documento e contato de clientes para vender, entregar ou atender.',
                    'segments' => ['comercio', 'servicos', 'tecnologia', 'industria'],
                    'fields' => [
                        'name' => 'Cadastro de clientes',
                        'purpose' => 'Identificar o cliente, emitir notas fiscais, entregar pedidos e prestar atendimento.',
                        'description' => 'O cadastro é feito no balcão ou no site e fica no sistema de vendas. O atendimento consulta o cadastro para trocas e dúvidas.',
                        'controller_role' => 'controlador', 'data_source' => 'titular',
                        'legal_basis_code' => 'art7_v',
                        'legal_basis_notes' => 'O cadastro é necessário para a venda e a entrega pedidas pelo cliente. A nota fiscal é obrigação legal (art. 7º, II).',
                        'retention_period' => '5 anos após a última compra (prazo fiscal e do Código de Defesa do Consumidor)',
                        'disposal_method' => 'Exclusão no sistema de vendas ou anonimização para relatórios',
                        'security_measures' => 'Acesso por usuário e senha individuais; perfis por função; backup diário.',
                    ],
                    'data' => ['Dados de identificação', 'Dados de contato', 'Histórico de compras'],
                    'subjects' => ['Clientes'],
                ],
                'folha-pagamento' => [
                    'title' => 'Folha de pagamento e dados de colaboradores',
                    'situation' => 'Toda empresa com empregados: admissão, folha, benefícios e obrigações trabalhistas.',
                    'fields' => [
                        'name' => 'Gestão de colaboradores e folha de pagamento',
                        'purpose' => 'Admitir, pagar salários e benefícios e cumprir as obrigações trabalhistas, previdenciárias e fiscais.',
                        'description' => 'O RH recebe os documentos na admissão e envia os dados à contabilidade, que processa a folha e o eSocial.',
                        'controller_role' => 'controlador', 'data_source' => 'titular',
                        'legal_basis_code' => 'art7_ii', 'sensitive_legal_basis_code' => 'art11_ii_a',
                        'legal_basis_notes' => 'Obrigações da CLT, do eSocial e da Receita. Atestados e dados de saúde ocupacional têm base no art. 11, II, "a".',
                        'retention_period' => 'Conforme a legislação trabalhista e previdenciária (alguns registros por até 20 anos ou mais)',
                        'disposal_method' => 'Fragmentação dos documentos em papel e exclusão no sistema ao fim do prazo',
                        'security_measures' => 'Pasta física trancada; acesso ao sistema de folha só pelo RH; contrato com a contabilidade com cláusulas de proteção de dados.',
                    ],
                    'data' => ['Dados de identificação', 'Dados de contato', 'Dados de colaboradores (RH e folha)', 'Dados financeiros e de pagamento', 'Saúde ocupacional (atestados e exames admissionais)'],
                    'subjects' => ['Colaboradores'],
                ],
                'recrutamento' => [
                    'title' => 'Recrutamento e seleção',
                    'situation' => 'Você recebe currículos por e-mail, site ou plataformas de vagas.',
                    'fields' => [
                        'name' => 'Recrutamento e seleção',
                        'purpose' => 'Avaliar candidatos para vagas abertas e entrar em contato com os selecionados.',
                        'description' => 'Os currículos chegam por e-mail e pela plataforma de vagas. O gestor da área participa das entrevistas.',
                        'controller_role' => 'controlador', 'data_source' => 'titular',
                        'legal_basis_code' => 'art7_v',
                        'legal_basis_notes' => 'Procedimentos preliminares a um contrato de trabalho, a pedido do candidato. Para manter o currículo em banco de talentos, pedir consentimento.',
                        'retention_period' => '6 meses após o fim do processo seletivo (ou enquanto houver consentimento para banco de talentos)',
                        'disposal_method' => 'Exclusão dos e-mails e arquivos; fragmentação de currículos impressos',
                        'security_measures' => 'Currículos em pasta com acesso restrito ao RH; e-mail exclusivo para vagas.',
                    ],
                    'data' => ['Dados de identificação', 'Dados de contato'],
                    'subjects' => ['Candidatos a vagas'],
                ],
                'email-marketing' => [
                    'title' => 'E-mail marketing e newsletter',
                    'situation' => 'Você envia promoções, novidades ou conteúdo por e-mail ou WhatsApp.',
                    'fields' => [
                        'name' => 'Comunicações de marketing',
                        'purpose' => 'Enviar ofertas, novidades e conteúdo a quem pediu para receber.',
                        'description' => 'A pessoa se cadastra no site ou na loja. Os envios são feitos por uma ferramenta de e-mail marketing.',
                        'controller_role' => 'controlador', 'data_source' => 'titular',
                        'legal_basis_code' => 'art7_i',
                        'legal_basis_notes' => 'Consentimento registrado no cadastro, com descadastro fácil em todo envio. Para clientes atuais, avaliar legítimo interesse com opção de saída.',
                        'retention_period' => 'Enquanto houver consentimento; exclusão em até 30 dias após o descadastro',
                        'disposal_method' => 'Exclusão da lista na ferramenta de envio',
                        'security_measures' => 'Acesso à ferramenta com verificação em duas etapas; listas sem dados além de nome e e-mail.',
                        'international_transfer' => '1',
                        'international_details' => '[Confira onde a ferramenta de envio guarda os dados e as garantias contratuais.]',
                    ],
                    'data' => ['Dados de contato', 'Dados de navegação e cookies'],
                    'subjects' => ['Clientes', 'Leads e prospects'],
                ],
                'cftv' => [
                    'title' => 'Câmeras de segurança (CFTV)',
                    'situation' => 'Há câmeras gravando a entrada, o salão ou o estoque.',
                    'fields' => [
                        'name' => 'Monitoramento por câmeras de segurança',
                        'purpose' => 'Proteger pessoas e o patrimônio e apurar ocorrências.',
                        'description' => 'Câmeras na entrada e nas áreas comuns, com gravação em equipamento local. Placas avisam sobre o monitoramento.',
                        'controller_role' => 'controlador', 'data_source' => 'gerado',
                        'legal_basis_code' => 'art7_ix',
                        'legal_basis_notes' => 'Legítimo interesse na segurança, sem câmeras em banheiros, vestiários ou áreas de descanso. Registrar a avaliação do legítimo interesse.',
                        'retention_period' => '30 dias, salvo imagens de ocorrências guardadas para apuração',
                        'disposal_method' => 'Sobrescrita automática do gravador',
                        'security_measures' => 'Gravador em sala trancada; acesso às imagens só pelo responsável; registro de quem consultou.',
                    ],
                    'data' => ['Imagem e voz (fotos, vídeos, câmeras)'],
                    'subjects' => ['Clientes', 'Visitantes', 'Colaboradores'],
                ],
                'agendamento-consultas' => [
                    'title' => 'Agendamento de consultas',
                    'situation' => 'Clínicas e consultórios que marcam e confirmam consultas.',
                    'segments' => ['saude'],
                    'fields' => [
                        'name' => 'Agendamento de consultas',
                        'purpose' => 'Marcar, confirmar e lembrar consultas dos pacientes.',
                        'description' => 'A recepção registra o paciente na agenda e envia lembrete na véspera pelo WhatsApp da clínica.',
                        'controller_role' => 'controlador', 'data_source' => 'titular',
                        'legal_basis_code' => 'art7_v', 'sensitive_legal_basis_code' => 'art11_ii_f',
                        'legal_basis_notes' => 'O agendamento é parte do serviço pedido pelo paciente. A especialidade pode revelar dado de saúde: tutela da saúde (art. 11, II, "f").',
                        'retention_period' => 'Junto com o prontuário, conforme as normas do conselho profissional',
                        'disposal_method' => 'Exclusão no sistema de agenda ao fim do prazo',
                        'security_measures' => 'WhatsApp corporativo; agenda sem detalhes clínicos; acesso por perfil.',
                    ],
                    'data' => ['Dados de identificação', 'Dados de contato', 'Dados de convênio'],
                    'subjects' => ['Pacientes', 'Responsáveis legais'],
                ],
                'prontuario' => [
                    'title' => 'Prontuário do paciente',
                    'situation' => 'Registro clínico de atendimentos, exames e diagnósticos.',
                    'segments' => ['saude'],
                    'fields' => [
                        'name' => 'Prontuário eletrônico',
                        'purpose' => 'Registrar o atendimento e o histórico clínico para a continuidade do cuidado.',
                        'description' => 'Médicos e enfermagem registram cada atendimento no prontuário eletrônico. A recepção não acessa os dados clínicos.',
                        'controller_role' => 'controlador', 'data_source' => 'titular',
                        'legal_basis_code' => 'art7_viii', 'sensitive_legal_basis_code' => 'art11_ii_f',
                        'legal_basis_notes' => 'Tutela da saúde por profissionais de saúde; guarda obrigatória por norma do conselho profissional.',
                        'retention_period' => 'Mínimo de 20 anos a partir do último registro (norma do conselho profissional)',
                        'disposal_method' => 'Eliminação segura ao fim do prazo, com registro',
                        'security_measures' => 'Acesso por perfil; registro de acessos; senha individual; backup criptografado.',
                    ],
                    'data' => ['Dados de identificação', 'Dados de saúde (prontuário, exames, diagnósticos)'],
                    'subjects' => ['Pacientes'],
                ],
                'matricula-alunos' => [
                    'title' => 'Matrícula de alunos',
                    'situation' => 'Escolas e cursos que matriculam alunos, inclusive menores de idade.',
                    'segments' => ['educacao'],
                    'fields' => [
                        'name' => 'Matrícula e vida escolar',
                        'purpose' => 'Matricular o aluno, controlar frequência e notas e emitir documentos escolares.',
                        'description' => 'A secretaria recebe os documentos do responsável e cadastra o aluno no sistema acadêmico.',
                        'controller_role' => 'controlador', 'data_source' => 'titular',
                        'legal_basis_code' => 'art7_v',
                        'legal_basis_notes' => 'Contrato de prestação de serviços educacionais com o responsável; guarda de documentos por obrigação legal. Dados de menores no melhor interesse da criança (art. 14).',
                        'retention_period' => 'Histórico escolar de forma permanente; demais documentos conforme a regulação educacional',
                        'disposal_method' => 'Fragmentação de cópias em papel e exclusão no sistema',
                        'security_measures' => 'Acesso por perfil; arquivo físico trancado; responsáveis acessam só os dados do próprio filho.',
                    ],
                    'data' => ['Dados de identificação', 'Histórico escolar e notas', 'Dados de crianças e adolescentes'],
                    'subjects' => ['Alunos', 'Alunos menores de idade', 'Pais e responsáveis'],
                ],
            ],

            // ================================================================ riscos
            'risco' => [
                'senha-compartilhada' => [
                    'title' => 'Senhas compartilhadas ou acessos de ex-colaboradores',
                    'situation' => 'Mais de uma pessoa usa o mesmo login, ou quem saiu da empresa ainda tem acesso.',
                    'plan' => 'controle-acessos',
                    'fields' => [
                        'title' => 'Acesso indevido por senha compartilhada ou conta não desativada',
                        'category' => 'seguranca',
                        'description' => 'Logins de uso comum e contas de ex-colaboradores continuam ativos nos sistemas.',
                        'cause' => 'Falta de rotina para criar e desativar acessos; senhas anotadas ou repassadas.',
                        'consequence' => 'Acesso a dados pessoais sem rastreio de quem foi; vazamento por ex-colaborador.',
                        'likelihood' => '4', 'impact' => '4', 'treatment' => 'mitigar',
                        'treatment_notes' => 'Contas individuais, desativação no desligamento e revisão periódica dos acessos.',
                    ],
                ],
                'whatsapp-pessoal' => [
                    'title' => 'Dados enviados por WhatsApp ou e-mail pessoal',
                    'situation' => 'A equipe usa o celular ou o e-mail próprio para falar com clientes ou pacientes.',
                    'plan' => 'canais-oficiais',
                    'fields' => [
                        'title' => 'Envio de dados pessoais por canais pessoais',
                        'category' => 'seguranca',
                        'description' => 'Conversas e arquivos com dados de clientes ficam no celular ou no e-mail particular de colaboradores.',
                        'cause' => 'Ausência de canal corporativo e de regra sobre o uso de aparelhos pessoais.',
                        'consequence' => 'Perda de controle sobre os dados, inclusive após o desligamento; exposição em caso de perda do aparelho.',
                        'likelihood' => '4', 'impact' => '3', 'treatment' => 'mitigar',
                        'treatment_notes' => 'Adotar canal corporativo e proibir o uso de contas pessoais para dados de clientes.',
                    ],
                ],
                'ransomware' => [
                    'title' => 'Sequestro de dados (ransomware) sem backup testado',
                    'situation' => 'Os dados ficam em computadores ou servidor local, e o backup não é testado.',
                    'plan' => 'backup',
                    'fields' => [
                        'title' => 'Perda ou sequestro de dados por ransomware',
                        'category' => 'seguranca',
                        'description' => 'Um vírus criptografa os arquivos e o sistema; a empresa não consegue recuperar a tempo.',
                        'cause' => 'Backup ausente, sem cópia fora do local ou nunca testado; sistemas desatualizados.',
                        'consequence' => 'Parada do atendimento, perda de dados e possível vazamento com comunicação à ANPD.',
                        'likelihood' => '3', 'impact' => '5', 'treatment' => 'mitigar',
                        'treatment_notes' => 'Backup diário com cópia separada, teste de restauração e atualização dos sistemas.',
                    ],
                ],
                'fornecedor-sem-contrato' => [
                    'title' => 'Fornecedor com acesso a dados sem contrato de proteção',
                    'situation' => 'Contabilidade, sistema em nuvem ou agência acessam dados sem cláusulas de proteção de dados.',
                    'plan' => 'fornecedores',
                    'fields' => [
                        'title' => 'Fornecedor trata dados pessoais sem obrigações contratuais',
                        'category' => 'terceiros',
                        'description' => 'Fornecedores acessam dados de clientes ou colaboradores sem contrato que trate de sigilo, segurança e incidentes.',
                        'cause' => 'Contratos antigos ou contratação informal.',
                        'consequence' => 'Uso indevido ou vazamento pelo fornecedor, pelo qual a empresa também responde.',
                        'likelihood' => '3', 'impact' => '4', 'treatment' => 'mitigar',
                        'treatment_notes' => 'Avaliar os fornecedores e incluir cláusulas de proteção de dados nos contratos.',
                    ],
                ],
                'guarda-excessiva' => [
                    'title' => 'Dados guardados além do necessário',
                    'situation' => 'Nada é apagado: cadastros, currículos e documentos antigos se acumulam.',
                    'plan' => 'retencao',
                    'fields' => [
                        'title' => 'Retenção de dados pessoais por tempo indeterminado',
                        'category' => 'retencao',
                        'description' => 'Dados de ex-clientes, candidatos e ex-colaboradores continuam guardados sem motivo.',
                        'cause' => 'Ausência de tabela de prazos de guarda e de rotina de descarte.',
                        'consequence' => 'Mais dados expostos em um incidente; descumprimento do princípio da necessidade.',
                        'likelihood' => '4', 'impact' => '2', 'treatment' => 'mitigar',
                        'treatment_notes' => 'Definir prazos de guarda por tipo de dado e criar rotina de descarte.',
                    ],
                ],
                'papel-sem-controle' => [
                    'title' => 'Documentos em papel sem controle',
                    'situation' => 'Fichas, contratos e cópias de documentos ficam em mesas ou armários abertos.',
                    'fields' => [
                        'title' => 'Acesso ou perda de documentos físicos com dados pessoais',
                        'category' => 'seguranca',
                        'description' => 'Documentos com dados pessoais ficam expostos e são descartados no lixo comum.',
                        'cause' => 'Falta de armários trancados, de mesa limpa e de fragmentadora.',
                        'consequence' => 'Acesso por pessoas não autorizadas e vazamento pelo lixo.',
                        'likelihood' => '3', 'impact' => '3', 'treatment' => 'mitigar',
                        'treatment_notes' => 'Armários com chave, regra de mesa limpa e fragmentação no descarte.',
                    ],
                ],
                'phishing' => [
                    'title' => 'Golpe por e-mail ou mensagem (phishing)',
                    'situation' => 'A equipe recebe mensagens falsas pedindo senha, pagamento ou clique em link.',
                    'plan' => 'conscientizacao',
                    'fields' => [
                        'title' => 'Captura de senhas e dados por phishing',
                        'category' => 'seguranca',
                        'description' => 'Um colaborador clica em link falso e entrega a senha de e-mail ou de sistema.',
                        'cause' => 'Falta de treinamento e de verificação em duas etapas.',
                        'consequence' => 'Acesso de terceiros às caixas de e-mail e aos sistemas, com vazamento de dados.',
                        'likelihood' => '4', 'impact' => '4', 'treatment' => 'mitigar',
                        'treatment_notes' => 'Treinar a equipe, ativar verificação em duas etapas e criar canal para avisar sobre mensagens suspeitas.',
                    ],
                ],
                'prazo-titulares' => [
                    'title' => 'Pedidos de titulares sem resposta no prazo',
                    'situation' => 'Pedidos de acesso ou exclusão chegam por vários canais e se perdem.',
                    'fields' => [
                        'title' => 'Descumprimento do prazo de resposta aos titulares',
                        'category' => 'direitos_titulares',
                        'description' => 'Pedidos chegam por telefone, e-mail ou redes sociais e não são registrados nem acompanhados.',
                        'cause' => 'Ausência de canal único e de responsável pelo atendimento.',
                        'consequence' => 'Reclamação à ANPD ou ao Procon e desgaste com o cliente.',
                        'likelihood' => '3', 'impact' => '3', 'treatment' => 'mitigar',
                        'treatment_notes' => 'Divulgar o canal do titular do ÍSIS e registrar todo pedido recebido por outros meios.',
                    ],
                ],
            ],

            // ================================================================ planos de ação
            'plano' => [
                'controle-acessos' => [
                    'title' => 'Controle de acessos',
                    'situation' => 'Para arrumar quem acessa o quê nos sistemas e pastas.',
                    'actions' => [
                        ['Levantar todos os usuários dos sistemas e pastas compartilhadas', 'Listar contas por sistema, com o dono de cada uma.', 'alta', 15],
                        ['Desativar contas de ex-colaboradores e contas genéricas', 'Substituir logins compartilhados por contas individuais.', 'alta', 20],
                        ['Definir perfis de acesso por função', 'Cada função acessa só o necessário (ex.: recepção sem dados clínicos).', 'media', 30],
                        ['Incluir a desativação de acessos no roteiro de desligamento', 'O RH avisa a TI no último dia de trabalho.', 'media', 30],
                        ['Revisar os acessos a cada 6 meses', 'Registrar a revisão como evidência nesta ação.', 'baixa', 180],
                    ],
                ],
                'canais-oficiais' => [
                    'title' => 'Canais oficiais de atendimento',
                    'situation' => 'Para tirar dados de clientes de celulares e e-mails pessoais.',
                    'actions' => [
                        ['Contratar WhatsApp Business ou canal corporativo de atendimento', null, 'alta', 15],
                        ['Publicar regra proibindo contas pessoais para dados de clientes', 'Incluir na política de segurança e colher a ciência da equipe.', 'alta', 20],
                        ['Apagar conversas e arquivos de clientes dos aparelhos pessoais', 'Pedir confirmação de cada colaborador.', 'media', 30],
                    ],
                ],
                'backup' => [
                    'title' => 'Backup e recuperação',
                    'situation' => 'Para não perder dados em caso de ransomware, falha ou erro.',
                    'actions' => [
                        ['Configurar backup diário automático dos sistemas e arquivos', null, 'alta', 10],
                        ['Manter uma cópia do backup fora do local ou em nuvem separada', 'A cópia não pode ficar acessível pela mesma rede.', 'alta', 20],
                        ['Testar a restauração do backup', 'Restaurar um arquivo e registrar o teste como evidência.', 'media', 30],
                        ['Atualizar sistemas operacionais e antivírus', null, 'media', 15],
                    ],
                ],
                'fornecedores' => [
                    'title' => 'Adequação de fornecedores',
                    'situation' => 'Para garantir que terceiros que tratam dados cumpram regras de proteção.',
                    'actions' => [
                        ['Listar os fornecedores que acessam dados pessoais', 'Cadastrar cada um em Fornecedores.', 'alta', 15],
                        ['Aplicar a avaliação do ÍSIS a cada fornecedor', null, 'media', 30],
                        ['Incluir cláusulas de proteção de dados nos contratos', 'Sigilo, segurança, aviso de incidentes e devolução ou eliminação ao fim do contrato.', 'alta', 45],
                        ['Definir a reavaliação anual dos fornecedores de risco alto', null, 'baixa', 90],
                    ],
                ],
                'retencao' => [
                    'title' => 'Prazos de guarda e descarte',
                    'situation' => 'Para guardar só o necessário e eliminar o resto com segurança.',
                    'actions' => [
                        ['Definir o prazo de guarda de cada operação do inventário', 'Preencher o campo "Prazo de guarda" com a justificativa.', 'media', 30],
                        ['Eliminar os dados que já passaram do prazo', 'Registrar o que foi eliminado e como.', 'media', 60],
                        ['Comprar fragmentadora e definir o descarte de papéis', null, 'baixa', 30],
                        ['Agendar a rotina de descarte semestral', null, 'baixa', 180],
                    ],
                ],
                'conscientizacao' => [
                    'title' => 'Conscientização da equipe',
                    'situation' => 'Para reduzir erros do dia a dia e golpes.',
                    'actions' => [
                        ['Designar o treinamento de LGPD a toda a equipe', 'Usar o módulo de Treinamentos do ÍSIS.', 'alta', 15],
                        ['Ativar verificação em duas etapas no e-mail e nos sistemas', null, 'alta', 15],
                        ['Criar canal para avisar sobre mensagens suspeitas', 'Ex.: e-mail ou grupo para o encarregado.', 'media', 20],
                        ['Repetir o treinamento a cada ano', null, 'baixa', 365],
                    ],
                ],
                'site-privacidade' => [
                    'title' => 'Site com aviso de privacidade e cookies',
                    'situation' => 'Para ajustar o site ou a loja virtual que coleta dados.',
                    'actions' => [
                        ['Publicar o aviso de privacidade no site', 'Explicar quais dados são coletados, para quê e como exercer direitos.', 'alta', 20],
                        ['Configurar o aviso de cookies com opção de recusar os não essenciais', null, 'media', 30],
                        ['Incluir no site o link do canal do titular do ÍSIS', null, 'media', 10],
                        ['Revisar os formulários para pedir só os dados necessários', null, 'baixa', 45],
                    ],
                ],
            ],

            // ================================================================ respostas
            'resposta' => [
                'confirmacao' => [
                    'title' => 'Confirmação de que tratamos seus dados',
                    'situation' => 'O titular quer saber se a empresa tem dados dele.',
                    'rights' => ['confirmacao'],
                    'final_status' => 'respondida',
                    'text' => "Olá, {nome}.\n\nEm resposta à sua solicitação {protocolo}, recebida em {data_pedido}, confirmamos que {empresa} trata dados pessoais seus.\n\nOs dados são usados para [finalidades, ex.: atendimento e emissão de notas fiscais], com base em [hipótese legal]. Se quiser, você pode pedir uma cópia completa dos dados pelo nosso canal de privacidade.\n\nFicamos à disposição pelo e-mail {contato}.\n\nAtenciosamente,\n{encarregado}\nEncarregado(a) pelo tratamento de dados pessoais",
                ],
                'acesso' => [
                    'title' => 'Acesso aos dados (cópia)',
                    'situation' => 'O titular pediu uma cópia dos dados.',
                    'rights' => ['acesso', 'portabilidade'],
                    'final_status' => 'respondida',
                    'text' => "Olá, {nome}.\n\nEm atendimento à sua solicitação {protocolo}, segue a relação dos seus dados pessoais tratados por {empresa}:\n\n- Dados cadastrais: [nome, CPF, endereço, telefone, e-mail]\n- [Outros dados: histórico de compras, atendimentos etc.]\n\nOrigem: [fornecidos por você no cadastro].\nFinalidade: [para que usamos].\nCompartilhamento: [com quem compartilhamos, ex.: contabilidade e transportadora].\nPrazo de guarda: [por quanto tempo].\n\n[Se houver arquivo anexo: os dados também seguem no arquivo enviado em formato legível.]\n\nAtenciosamente,\n{encarregado}\n{contato}",
                ],
                'correcao' => [
                    'title' => 'Correção realizada',
                    'situation' => 'O titular pediu para corrigir ou atualizar dados.',
                    'rights' => ['correcao'],
                    'final_status' => 'respondida',
                    'text' => "Olá, {nome}.\n\nInformamos que a correção pedida na solicitação {protocolo} foi feita. O(s) dado(s) [quais] foram atualizados para [novo valor, sem expor dados sensíveis].\n\n[Se compartilhamos esses dados com outras empresas, informe: comunicamos a correção a (destinatários).]\n\nAtenciosamente,\n{encarregado}\n{contato}",
                ],
                'eliminacao' => [
                    'title' => 'Eliminação realizada',
                    'situation' => 'O titular pediu para apagar os dados e não há obrigação de guardá-los.',
                    'rights' => ['eliminacao', 'anonimizacao', 'revogacao'],
                    'final_status' => 'respondida',
                    'text' => "Olá, {nome}.\n\nEm atendimento à solicitação {protocolo}, informamos que seus dados pessoais foram eliminados dos nossos sistemas em [data].\n\nA partir de agora você não receberá mais comunicações de {empresa}. Se voltar a se cadastrar no futuro, um novo registro será criado.\n\nAtenciosamente,\n{encarregado}\n{contato}",
                ],
                'eliminacao-parcial' => [
                    'title' => 'Eliminação parcial (dados que precisamos guardar)',
                    'situation' => 'Parte dos dados precisa ser mantida por obrigação legal (fiscal, trabalhista, saúde).',
                    'rights' => ['eliminacao', 'anonimizacao', 'oposicao'],
                    'final_status' => 'respondida',
                    'text' => "Olá, {nome}.\n\nEm resposta à solicitação {protocolo}, eliminamos os dados que não precisam mais ser guardados, como [ex.: dados de marketing e preferências].\n\nAlguns dados precisam ser mantidos por obrigação legal: [quais] por [prazo], em razão de [lei ou norma, ex.: legislação fiscal]. Eles ficam guardados com acesso restrito, apenas para essa finalidade, e serão eliminados ao fim do prazo (art. 16 da LGPD).\n\nAtenciosamente,\n{encarregado}\n{contato}",
                ],
                'compartilhamento' => [
                    'title' => 'Com quem compartilhamos seus dados',
                    'situation' => 'O titular quer saber com quais empresas os dados são compartilhados.',
                    'rights' => ['compartilhamento'],
                    'final_status' => 'respondida',
                    'text' => "Olá, {nome}.\n\nEm resposta à solicitação {protocolo}, informamos que {empresa} compartilha seus dados com:\n\n- [Empresa ou categoria, ex.: escritório de contabilidade]: para [finalidade].\n- [Empresa ou categoria]: para [finalidade].\n\nTodos esses parceiros tratam os dados apenas para as finalidades informadas.\n\nAtenciosamente,\n{encarregado}\n{contato}",
                ],
                'oposicao-marketing' => [
                    'title' => 'Fim das comunicações de marketing',
                    'situation' => 'O titular não quer mais receber ofertas ou revogou o consentimento.',
                    'rights' => ['revogacao', 'oposicao', 'nao_consentir'],
                    'final_status' => 'respondida',
                    'text' => "Olá, {nome}.\n\nRecebemos sua solicitação {protocolo}. Seu contato foi retirado das nossas listas de comunicação em [data], e você não receberá mais ofertas de {empresa}.\n\nIsso não afeta o atendimento de pedidos ou serviços em andamento.\n\nAtenciosamente,\n{encarregado}\n{contato}",
                ],
                'dados-nao-encontrados' => [
                    'title' => 'Não encontramos dados seus',
                    'situation' => 'Após a busca, não há dados do titular na empresa.',
                    'final_status' => 'respondida',
                    'text' => "Olá, {nome}.\n\nEm resposta à solicitação {protocolo}, informamos que, após pesquisa em nossos sistemas e arquivos, não localizamos dados pessoais seus tratados por {empresa}.\n\nSe você tiver alguma informação que ajude na busca (ex.: outro e-mail ou número de pedido), responda a esta mensagem.\n\nAtenciosamente,\n{encarregado}\n{contato}",
                ],
                'identidade-nao-confirmada' => [
                    'title' => 'Indeferido: não foi possível confirmar a identidade',
                    'situation' => 'O solicitante não comprovou ser o titular dos dados.',
                    'final_status' => 'indeferida',
                    'text' => "Olá, {nome}.\n\nNão foi possível atender à solicitação {protocolo} porque não conseguimos confirmar que você é o(a) titular dos dados. Essa verificação protege suas informações contra pedidos feitos por outras pessoas.\n\nPara um novo pedido, envie [documento ou informação necessária] pelo canal de privacidade: {contato}.\n\nAtenciosamente,\n{encarregado}",
                ],
            ],

            // ================================================================ incidentes
            'incidente' => [
                'email-errado' => [
                    'title' => 'E-mail ou arquivo enviado à pessoa errada',
                    'situation' => 'Uma planilha, documento ou mensagem com dados pessoais foi enviada a quem não devia.',
                    'fields' => [
                        'title' => 'Arquivo com dados pessoais enviado ao destinatário errado',
                        'category' => 'erro_humano', 'personal_data' => 'sim',
                        'description' => "O que foi enviado: [planilha, documento, mensagem]\nPara quem foi enviado por engano: [nome ou empresa, se souber]\nQuais dados estavam no arquivo: [ex.: nome, CPF, telefone de 120 clientes]\nComo aconteceu: [ex.: preenchimento automático do e-mail]\nO que já foi feito: [ex.: pedimos ao destinatário que apagasse e confirmasse por escrito]",
                    ],
                ],
                'perda-furto' => [
                    'title' => 'Perda ou furto de notebook, celular ou pen drive',
                    'situation' => 'Um equipamento com dados da empresa sumiu.',
                    'fields' => [
                        'title' => 'Perda ou furto de equipamento com dados pessoais',
                        'category' => 'furto', 'personal_data' => 'sim',
                        'description' => "Equipamento: [notebook, celular, pen drive]\nOnde e quando sumiu: [local e horário aproximado]\nQue dados havia nele: [ex.: e-mails, planilhas de clientes]\nO equipamento tinha senha ou criptografia? [sim/não/não sei]\nO que já foi feito: [ex.: troca de senhas, bloqueio remoto, boletim de ocorrência]",
                    ],
                ],
                'ransomware' => [
                    'title' => 'Vírus que bloqueia os arquivos (ransomware)',
                    'situation' => 'Os arquivos ficaram ilegíveis ou apareceu um pedido de resgate.',
                    'fields' => [
                        'title' => 'Ataque de ransomware nos computadores da empresa',
                        'category' => 'ransomware', 'personal_data' => 'nao_sei',
                        'description' => "O que apareceu na tela: [ex.: arquivos com extensão estranha, mensagem de resgate]\nQuais computadores ou sistemas foram afetados: [quais]\nQuando foi percebido: [horário]\nO que já foi feito: [ex.: computadores desligados da rede, TI acionada]\nNão pague o resgate nem apague nada antes da análise.",
                    ],
                ],
                'phishing-senha' => [
                    'title' => 'Senha entregue em golpe (phishing)',
                    'situation' => 'Alguém clicou em link falso e digitou a senha, ou a conta foi invadida.',
                    'fields' => [
                        'title' => 'Conta de e-mail ou sistema acessada após golpe',
                        'category' => 'phishing', 'personal_data' => 'nao_sei',
                        'description' => "Qual conta: [e-mail ou sistema]\nComo aconteceu: [ex.: mensagem falsa pedindo atualização de senha]\nHá sinais de uso indevido? [ex.: e-mails enviados sem autorização, regras de encaminhamento]\nO que já foi feito: [ex.: senha trocada, sessões encerradas, verificação em duas etapas ativada]",
                    ],
                ],
                'acesso-indevido' => [
                    'title' => 'Acesso indevido por colaborador',
                    'situation' => 'Alguém consultou ou copiou dados sem necessidade de trabalho.',
                    'fields' => [
                        'title' => 'Consulta indevida a dados pessoais por colaborador',
                        'category' => 'acesso_indevido', 'personal_data' => 'sim',
                        'description' => "Quem acessou (função, sem acusar antes da apuração): [função]\nQue dados foram acessados: [ex.: prontuário de um paciente]\nComo foi percebido: [ex.: registro de acessos, reclamação do titular]\nO que já foi feito: [ex.: acesso suspenso, registros preservados]",
                    ],
                ],
                'descarte-papel' => [
                    'title' => 'Documentos descartados sem fragmentar',
                    'situation' => 'Papéis com dados pessoais foram para o lixo comum ou ficaram expostos.',
                    'fields' => [
                        'title' => 'Documentos com dados pessoais descartados de forma inadequada',
                        'category' => 'erro_humano', 'personal_data' => 'sim',
                        'description' => "Que documentos: [ex.: fichas de cadastro]\nOnde foram encontrados: [ex.: lixo na calçada]\nQuantas pessoas podem ser afetadas: [estimativa]\nO que já foi feito: [ex.: documentos recolhidos e fragmentados]",
                    ],
                ],
                'fornecedor' => [
                    'title' => 'Incidente em um fornecedor',
                    'situation' => 'Um fornecedor avisou que sofreu vazamento ou invasão.',
                    'fields' => [
                        'title' => 'Incidente de segurança comunicado por fornecedor',
                        'category' => 'vazamento', 'personal_data' => 'nao_sei',
                        'description' => "Fornecedor: [nome]\nO que ele informou: [resumo do aviso, data e canal]\nQue dados da empresa ele trata: [ex.: folha de pagamento]\nPerguntas enviadas ao fornecedor: [ex.: dados afetados, medidas, prazo da investigação]",
                    ],
                ],
            ],
        ];
    }
}
