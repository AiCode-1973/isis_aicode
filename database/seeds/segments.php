<?php

declare(strict_types=1);

/*
 * Segmentos de atuação e modelos de sugestões. Os modelos só sugerem: a empresa pode aceitar,
 * editar ou ignorar tudo, então o sistema não fica preso a um setor.
 * Chaves do modelo: departments, subject_categories, data_categories, examples (atividades típicas), guidance.
 */

return [
    [
        'code' => 'saude',
        'name' => 'Saúde',
        'description' => 'Clínicas, consultórios, laboratórios, hospitais e operadoras.',
        'template' => [
            'departments' => ['Recepção e Atendimento', 'Corpo Clínico', 'Enfermagem', 'Faturamento e Convênios', 'Recursos Humanos', 'Tecnologia da Informação', 'Administrativo'],
            'subject_categories' => ['Pacientes', 'Responsáveis legais', 'Acompanhantes', 'Profissionais de saúde', 'Colaboradores'],
            'data_categories' => ['Dados de saúde (prontuário, exames, diagnósticos)', 'Dados de identificação', 'Dados de convênio', 'Dados genéticos ou biométricos', 'Dados de contato'],
            'examples' => ['Agendamento de consultas', 'Prontuário eletrônico', 'Faturamento de convênios', 'Resultados de exames'],
            'guidance' => [
                'Dados de saúde são sensíveis (art. 5º, II): priorize o mapeamento de prontuários e exames.',
                'Verifique a base legal de tutela da saúde (art. 11, II, "f") e o sigilo profissional aplicável.',
                'Controle quem acessa prontuários e mantenha registro desses acessos.',
                'Defina prazos de guarda conforme as normas do conselho profissional e da vigilância sanitária.',
            ],
        ],
    ],
    [
        'code' => 'comercio',
        'name' => 'Comércio e Varejo',
        'description' => 'Lojas físicas, e-commerce, atacado e distribuição.',
        'template' => [
            'departments' => ['Vendas', 'Atendimento ao Cliente', 'Marketing', 'Financeiro', 'Logística', 'Recursos Humanos', 'Tecnologia da Informação'],
            'subject_categories' => ['Clientes', 'Leads e prospects', 'Colaboradores', 'Representantes de fornecedores'],
            'data_categories' => ['Dados de identificação', 'Dados de contato', 'Histórico de compras', 'Dados de pagamento', 'Dados de navegação e cookies'],
            'examples' => ['Cadastro de clientes', 'Programa de fidelidade', 'Campanhas de e-mail marketing', 'Entrega de pedidos'],
            'guidance' => [
                'Revise o consentimento e o opt-out das comunicações de marketing.',
                'Mapeie cookies e ferramentas de análise do site e da loja virtual.',
                'Limite o acesso a dados de pagamento e prefira tokenização pelo intermediador.',
                'Mantenha um canal simples para o cliente exercer seus direitos.',
            ],
        ],
    ],
    [
        'code' => 'educacao',
        'name' => 'Educação',
        'description' => 'Escolas, faculdades, cursos livres e plataformas de ensino.',
        'template' => [
            'departments' => ['Secretaria Acadêmica', 'Coordenação Pedagógica', 'Financeiro', 'Marketing e Captação', 'Recursos Humanos', 'Tecnologia da Informação'],
            'subject_categories' => ['Alunos', 'Alunos menores de idade', 'Pais e responsáveis', 'Professores', 'Colaboradores'],
            'data_categories' => ['Dados de identificação', 'Histórico escolar e notas', 'Dados de crianças e adolescentes', 'Dados de saúde (laudos, necessidades especiais)', 'Imagem e voz'],
            'examples' => ['Matrícula', 'Diário de classe', 'Plataforma de ensino a distância', 'Uso de imagem em eventos'],
            'guidance' => [
                'Dados de crianças e adolescentes exigem atenção ao melhor interesse (art. 14).',
                'Revise autorizações de uso de imagem de alunos.',
                'Defina prazos de guarda de documentos acadêmicos conforme a regulação educacional.',
                'Avalie as plataformas educacionais contratadas como operadoras.',
            ],
        ],
    ],
    [
        'code' => 'tecnologia',
        'name' => 'Tecnologia',
        'description' => 'Software, SaaS, provedores de internet e serviços digitais.',
        'template' => [
            'departments' => ['Produto', 'Engenharia', 'Suporte', 'Comercial', 'Segurança da Informação', 'Recursos Humanos'],
            'subject_categories' => ['Usuários da plataforma', 'Clientes (contratantes)', 'Leads', 'Colaboradores', 'Candidatos'],
            'data_categories' => ['Dados de cadastro', 'Registros de acesso (logs)', 'Dados de uso do produto', 'Dados de pagamento', 'Dados tratados em nome de clientes'],
            'examples' => ['Cadastro e autenticação', 'Registros de acesso (Marco Civil)', 'Suporte técnico', 'Telemetria do produto'],
            'guidance' => [
                'Separe os papéis: quando a empresa é controladora e quando é operadora dos dados dos clientes.',
                'Registre transferências internacionais (nuvem, ferramentas SaaS).',
                'Aplique privacidade desde a concepção no ciclo de desenvolvimento.',
                'Guarde registros de acesso pelo prazo do Marco Civil da Internet.',
            ],
        ],
    ],
    [
        'code' => 'industria',
        'name' => 'Indústria',
        'description' => 'Fabricação, agroindústria, construção e energia.',
        'template' => [
            'departments' => ['Produção', 'Segurança do Trabalho', 'Recursos Humanos', 'Compras e Suprimentos', 'Comercial', 'Financeiro', 'Tecnologia da Informação'],
            'subject_categories' => ['Colaboradores', 'Terceirizados', 'Visitantes', 'Representantes de clientes e fornecedores'],
            'data_categories' => ['Dados de saúde ocupacional', 'Dados biométricos (ponto, acesso)', 'Imagens de CFTV', 'Dados de identificação', 'Dados bancários para pagamento'],
            'examples' => ['Controle de acesso à planta', 'Saúde e segurança ocupacional (PCMSO)', 'Folha de pagamento', 'Monitoramento por câmeras'],
            'guidance' => [
                'Dados biométricos e de saúde ocupacional são sensíveis: revise bases legais e acessos.',
                'Sinalize áreas com câmeras e defina o prazo de retenção das imagens.',
                'Inclua terceirizados e visitantes no mapeamento.',
                'Avalie fornecedores que recebem dados dos colaboradores (benefícios, medicina do trabalho).',
            ],
        ],
    ],
    [
        'code' => 'servicos',
        'name' => 'Serviços',
        'description' => 'Consultorias, escritórios, agências, imobiliárias e prestadores em geral.',
        'template' => [
            'departments' => ['Atendimento', 'Operações', 'Comercial', 'Financeiro', 'Recursos Humanos', 'Tecnologia da Informação'],
            'subject_categories' => ['Clientes', 'Contatos de clientes empresariais', 'Colaboradores', 'Candidatos', 'Prestadores de serviço'],
            'data_categories' => ['Dados de identificação', 'Dados de contato', 'Dados financeiros', 'Documentos de clientes', 'Dados profissionais'],
            'examples' => ['Cadastro de clientes', 'Contratos e propostas', 'Recrutamento e seleção', 'Atendimento por WhatsApp'],
            'guidance' => [
                'Mapeie documentos de clientes armazenados em e-mail, nuvem e mensageiros.',
                'Revise contratos com cláusulas de proteção de dados.',
                'Defina a retenção de currículos de candidatos não selecionados.',
                'Oriente a equipe sobre o compartilhamento de dados por aplicativos de mensagem.',
            ],
        ],
    ],
    [
        'code' => 'outro',
        'name' => 'Outro segmento',
        'description' => 'Organizações de qualquer outra área.',
        'template' => [
            'departments' => ['Administrativo', 'Atendimento', 'Financeiro', 'Recursos Humanos', 'Tecnologia da Informação'],
            'subject_categories' => ['Clientes ou usuários', 'Colaboradores', 'Fornecedores', 'Visitantes'],
            'data_categories' => ['Dados de identificação', 'Dados de contato', 'Dados financeiros', 'Dados profissionais'],
            'examples' => ['Cadastro de clientes', 'Folha de pagamento', 'Atendimento', 'Gestão de fornecedores'],
            'guidance' => [
                'Comece mapeando as atividades que envolvem mais pessoas ou dados sensíveis.',
                'Nomeie o encarregado e publique um canal de contato para os titulares.',
                'Revise contratos com quem recebe dados pessoais da organização.',
                'Treine a equipe nos cuidados básicos com dados pessoais.',
            ],
        ],
    ],
];
