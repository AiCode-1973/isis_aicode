<?php

declare(strict_types=1);

namespace Isis\Database\Seeds;

use Isis\Audit\AuditLogger;
use Isis\Auth\TenantContext;
use Isis\Core\Database;
use Isis\Repositories\ActionRepository;
use Isis\Repositories\ActivityRepository;
use Isis\Repositories\CatalogRepository;
use Isis\Repositories\RiskRepository;
use Isis\Services\CatalogService;

/**
 * Inventário, riscos e planos de ação FICTÍCIOS para as empresas de demonstração.
 * Roda no contexto da empresa (TenantContext) e só se ela ainda não tiver operações.
 */
final class DemoComplianceSeeder
{
    public static function run(string $segment): bool
    {
        if ((int) Database::value('SELECT COUNT(*) FROM processing_activities WHERE tenant_id = ?', [TenantContext::id()]) > 0) {
            return false;
        }
        CatalogService::importSuggestions($segment);
        Database::transaction(static fn () => $segment === 'saude' ? self::clinic() : self::store());
        return true;
    }

    private static function clinic(): void
    {
        $u = self::users();
        $d = self::departments();
        $sys = self::systems([
            ['Prontuário eletrônico (PEP)', 'nuvem'],
            ['Sistema de agendamento', 'nuvem'],
            ['WhatsApp Business', 'terceiro'],
            ['Arquivo físico de prontuários', 'papel'],
            ['Sistema da contabilidade (folha)', 'terceiro'],
        ]);
        $data = self::catalog('dados');
        $subj = self::catalog('titulares');
        $health = $data['Dados de saúde (prontuário, exames, diagnósticos)'];
        $ident = $data['Dados de identificação'];
        $contact = $data['Dados de contato'];
        $plan = $data['Dados de convênio'];

        $a = new ActivityRepository();
        $op1 = $a->create([
            'name' => 'Agendamento de consultas', 'purpose' => 'Marcar, confirmar e remarcar consultas e exames dos pacientes.',
            'legal_basis_code' => 'art7_v', 'controller_role' => 'controlador', 'department_id' => $d['Recepção e Atendimento'],
            'owner_user_id' => $u['gestor'], 'data_source' => 'titular', 'source_details' => 'Telefone, recepção e site',
            'subjects_volume' => 'ate_10000', 'retention_period' => '5 anos após o último atendimento', 'disposal_method' => 'Exclusão segura no sistema',
            'security_measures' => 'Acesso por usuário individual; perfis por função.', 'status' => 'ativa',
            'review_due_date' => self::date('+6 months'), 'created_by' => $u['encarregado'],
        ], self::rel([$ident, $contact], [$subj['Pacientes']], [$sys['Sistema de agendamento'], $sys['WhatsApp Business']], []));

        $op2 = $a->create([
            'name' => 'Prontuário eletrônico do paciente', 'description' => 'Registro clínico de consultas, exames, diagnósticos e prescrições.',
            'purpose' => 'Registrar o atendimento e garantir a continuidade do cuidado ao paciente.',
            'legal_basis_code' => 'art7_viii', 'sensitive_legal_basis_code' => 'art11_ii_f',
            'legal_basis_notes' => 'Tutela da saúde por profissionais de saúde. Sigilo profissional (CFM).',
            'controller_role' => 'controlador', 'department_id' => $d['Corpo Clínico'], 'owner_user_id' => $u['encarregado'],
            'data_source' => 'titular', 'subjects_volume' => 'ate_10000',
            'retention_period' => '20 anos a partir do último registro (Lei 13.787/2018)', 'disposal_method' => 'Eliminação segura com registro',
            'international_transfer' => 1, 'international_details' => 'Backup do PEP hospedado nos EUA; cláusulas-padrão contratuais em negociação.',
            'security_measures' => "Criptografia em trânsito e em repouso.\nPerfis de acesso por especialidade.", 'status' => 'ativa',
            'review_due_date' => self::date('+3 months'), 'created_by' => $u['encarregado'],
        ], self::rel([$health, $ident, $plan], [$subj['Pacientes'], $subj['Responsáveis legais']], [$sys['Prontuário eletrônico (PEP)'], $sys['Arquivo físico de prontuários']], [$d['Enfermagem']], [
            ['recipient' => 'Laboratório Análise Certa (fictício)', 'recipient_type' => 'operador', 'purpose' => 'Realização de exames', 'international' => false],
            ['recipient' => 'Operadoras de planos de saúde', 'recipient_type' => 'controlador', 'purpose' => 'Autorização de procedimentos', 'international' => false],
        ]));

        $op3 = $a->create([
            'name' => 'Faturamento de convênios', 'purpose' => 'Cobrar das operadoras os atendimentos realizados.',
            'legal_basis_code' => 'art7_v', 'sensitive_legal_basis_code' => 'art11_ii_d', 'controller_role' => 'controlador',
            'department_id' => $d['Faturamento e Convênios'], 'owner_user_id' => $u['gestor'], 'data_source' => 'gerado',
            'subjects_volume' => 'ate_10000', 'retention_period' => '5 anos (prazo fiscal)', 'status' => 'ativa',
            'review_due_date' => self::date('-10 days'), 'created_by' => $u['gestor'],
        ], self::rel([$plan, $ident, $health], [$subj['Pacientes']], [$sys['Prontuário eletrônico (PEP)']], [], [
            ['recipient' => 'Operadoras de planos de saúde', 'recipient_type' => 'controlador', 'purpose' => 'Faturamento', 'international' => false],
        ]));

        $op4 = $a->create([
            'name' => 'Gestão de colaboradores e folha de pagamento', 'purpose' => 'Admissão, folha, benefícios e obrigações trabalhistas.',
            'legal_basis_code' => 'art7_ii', 'controller_role' => 'controlador', 'department_id' => $d['Recursos Humanos'],
            'owner_user_id' => $u['admin'], 'data_source' => 'titular', 'subjects_volume' => 'ate_100', 'status' => 'em_revisao',
            'created_by' => $u['admin'],
        ], self::rel([$ident, $contact], [$subj['Colaboradores'], $subj['Profissionais de saúde']], [$sys['Sistema da contabilidade (folha)']], [], [
            ['recipient' => 'Escritório de contabilidade (fictício)', 'recipient_type' => 'operador', 'purpose' => 'Processamento da folha', 'international' => false],
        ]));

        $op5 = $a->create([
            'name' => 'Lembretes de consulta por WhatsApp', 'purpose' => 'Enviar lembretes e orientações de preparo para exames.',
            'controller_role' => 'controlador', 'department_id' => $d['Recepção e Atendimento'], 'status' => 'rascunho',
            'created_by' => $u['gestor'],
        ], self::rel([$contact], [$subj['Pacientes']], [$sys['WhatsApp Business']], []));

        $r = new RiskRepository();
        $r1 = $r->create(self::risk('Acesso indevido a prontuários', 'seguranca', $op2, $d['Corpo Clínico'], 3, 5, 'mitigar', 'em_tratamento', $u['encarregado'], [2, 5],
            'Usuários com perfis amplos podem consultar prontuários sem necessidade assistencial.', 'Exposição de dados de saúde; dano moral aos pacientes; sanções.'));
        $r2 = $r->create(self::risk('Envio de dados de pacientes por WhatsApp pessoal', 'seguranca', $op5, $d['Recepção e Atendimento'], 4, 3, 'mitigar', 'identificado', $u['gestor'], null,
            'Recepção usa celulares pessoais para confirmar consultas.', 'Perda de controle sobre os dados e vazamento.'));
        $r3 = $r->create(self::risk('Retenção indefinida de dados de ex-colaboradores', 'retencao', $op4, $d['Recursos Humanos'], 3, 2, 'mitigar', 'identificado', $u['admin'], null,
            'Não há prazo de retenção definido.', 'Guarda excessiva e maior exposição em caso de incidente.'));
        $r4 = $r->create(self::risk('Laboratório parceiro sem cláusulas de proteção de dados', 'terceiros', $op2, null, 2, 4, 'transferir', 'em_tratamento', $u['encarregado'], [1, 4],
            'Contrato antigo, sem obrigações sobre dados pessoais.', 'Uso indevido dos dados pelo operador.'));
        $r->create(self::risk('Perda ou deterioração de prontuários em papel', 'seguranca', $op2, $d['Corpo Clínico'], 2, 5, 'mitigar', 'identificado', null, null,
            'Arquivo físico sem controle de umidade e de acesso.', 'Perda de informações clínicas.'));
        $r6 = $r->create(self::risk('Phishing direcionado à recepção', 'seguranca', null, $d['Recepção e Atendimento'], 4, 4, 'mitigar', 'em_tratamento', $u['encarregado'], null,
            'E-mails falsos de "operadoras" pedindo dados de pacientes.', 'Vazamento de dados e acesso indevido a sistemas.'));

        $x = new ActionRepository();
        self::action($x, 'Revisar perfis de acesso ao prontuário eletrônico', $r1, $op2, $d['Corpo Clínico'], $u['gestor'], 'alta', 'em_andamento', '+10 days', 'Perfis de recepção e faturamento sem acesso ao conteúdo clínico.');
        self::action($x, 'Ativar e revisar mensalmente o registro de acessos do PEP', $r1, $op2, null, $u['encarregado'], 'alta', 'pendente', '-5 days');
        self::action($x, 'Adotar WhatsApp corporativo e proibir celulares pessoais', $r2, $op5, $d['Recepção e Atendimento'], $u['gestor'], 'media', 'pendente', '+20 days');
        self::action($x, 'Treinar a recepção para reconhecer phishing', $r6, null, $d['Recepção e Atendimento'], $u['colaborador'], 'alta', 'pendente', '+7 days');
        self::action($x, 'Incluir cláusulas de proteção de dados no contrato do laboratório', $r4, $op2, null, $u['encarregado'], 'alta', 'concluida', '-1 day', 'Aditivo assinado pelas duas partes.');
        self::action($x, 'Definir prazo de retenção de dados de ex-colaboradores', $r3, $op4, $d['Recursos Humanos'], $u['admin'], 'media', 'pendente', '+40 days');
        self::action($x, 'Ativar autenticação em dois fatores no e-mail corporativo', $r6, null, null, $u['admin'], 'alta', 'concluida', '-15 days');
        // Sem evidências de exemplo: os arquivos ficam no disco de quem roda o seed, que pode não ser o servidor.
    }

    private static function store(): void
    {
        $u = self::users();
        $d = self::departments();
        $sys = self::systems([['Loja virtual (plataforma de e-commerce)', 'nuvem'], ['Ferramenta de e-mail marketing', 'nuvem'], ['ERP de vendas', 'local']]);
        $data = self::catalog('dados');
        $subj = self::catalog('titulares');

        $a = new ActivityRepository();
        $op1 = $a->create([
            'name' => 'Cadastro de clientes da loja virtual', 'purpose' => 'Processar pedidos, pagamentos e entregas.',
            'legal_basis_code' => 'art7_v', 'controller_role' => 'controlador', 'department_id' => $d['Vendas'], 'owner_user_id' => $u['gestor'],
            'data_source' => 'titular', 'subjects_volume' => 'ate_100000', 'retention_period' => '5 anos após a última compra',
            'disposal_method' => 'Anonimização', 'status' => 'ativa', 'review_due_date' => self::date('+5 months'), 'created_by' => $u['encarregado'],
        ], self::rel([$data['Dados de identificação'], $data['Dados de contato'], $data['Histórico de compras'], $data['Dados de pagamento']], [$subj['Clientes']],
            [$sys['Loja virtual (plataforma de e-commerce)'], $sys['ERP de vendas']], [$d['Logística']], [
                ['recipient' => 'Transportadora Rápida (fictícia)', 'recipient_type' => 'operador', 'purpose' => 'Entrega dos pedidos', 'international' => false],
                ['recipient' => 'Intermediador de pagamentos', 'recipient_type' => 'controlador', 'purpose' => 'Processamento do pagamento', 'international' => false],
            ]));
        $op2 = $a->create([
            'name' => 'E-mail marketing e ofertas', 'purpose' => 'Enviar ofertas e novidades a quem se inscreveu.',
            'legal_basis_code' => 'art7_i', 'legal_basis_notes' => 'Inscrição com caixa de seleção não marcada; descadastro em um clique.',
            'controller_role' => 'controlador', 'department_id' => $d['Marketing'], 'owner_user_id' => $u['gestor'], 'data_source' => 'titular',
            'subjects_volume' => 'ate_10000', 'international_transfer' => 1, 'status' => 'ativa', 'created_by' => $u['gestor'],
        ], self::rel([$data['Dados de contato'], $data['Dados de navegação e cookies']], [$subj['Leads e prospects'], $subj['Clientes']], [$sys['Ferramenta de e-mail marketing']], []));
        $a->create([
            'name' => 'Programa de fidelidade', 'purpose' => 'Conceder pontos e benefícios por compras.', 'controller_role' => 'controlador',
            'department_id' => $d['Marketing'], 'status' => 'rascunho', 'created_by' => $u['gestor'],
        ], self::rel([$data['Histórico de compras']], [$subj['Clientes']], [], []));

        $r = new RiskRepository();
        $r1 = $r->create(self::risk('Envio de marketing sem consentimento válido', 'conformidade', $op2, $d['Marketing'], 3, 3, 'mitigar', 'em_tratamento', $u['gestor'], null,
            'Base antiga importada sem registro do consentimento.', 'Reclamações e sanções.'));
        $r2 = $r->create(self::risk('Vazamento da base de clientes da loja virtual', 'seguranca', $op1, $d['Vendas'], 2, 5, 'mitigar', 'identificado', $u['encarregado'], null,
            'Senhas fracas no painel administrativo da plataforma.', 'Exposição de dados de milhares de clientes.'));
        $r->create(self::risk('Transferência internacional sem mecanismo formal', 'conformidade', $op2, null, 3, 3, 'mitigar', 'identificado', $u['encarregado'], null,
            'Ferramenta de e-mail hospedada fora do Brasil.', 'Descumprimento do art. 33.'));

        $x = new ActionRepository();
        self::action($x, 'Higienizar a base de e-mails e pedir reconfirmação', $r1, $op2, $d['Marketing'], $u['gestor'], 'alta', 'em_andamento', '+12 days');
        self::action($x, 'Exigir autenticação em dois fatores no painel da loja', $r2, $op1, $d['Vendas'], $u['admin'], 'alta', 'pendente', '-3 days');
        self::action($x, 'Atualizar o aviso de privacidade do site', null, $op1, $d['Atendimento ao Cliente'], $u['colaborador'], 'media', 'pendente', '+25 days');
    }

    // ---------------- apoio ----------------

    /** @return array<string, int> perfil => id do usuário */
    private static function users(): array
    {
        return array_map('intval', array_column(
            Database::fetchAll('SELECT role, MIN(id) AS id FROM users WHERE tenant_id = ? GROUP BY role', [TenantContext::id()]),
            'id', 'role',
        ));
    }

    /** @return array<string, int> nome => id */
    private static function departments(): array
    {
        return array_map('intval', array_column(Database::fetchAll('SELECT id, name FROM departments WHERE tenant_id = ?', [TenantContext::id()]), 'id', 'name'));
    }

    /** @return array<string, int> */
    private static function catalog(string $type): array
    {
        $map = [];
        foreach ((new CatalogRepository($type))->options() as $id => $item) {
            $map[$item['name']] = $id;
        }
        return $map;
    }

    /** @param list<array{0: string, 1: string}> $items @return array<string, int> */
    private static function systems(array $items): array
    {
        $repo = new CatalogRepository('sistemas');
        foreach ($items as [$name, $hosting]) {
            if (!$repo->nameExists($name)) {
                $repo->create($name, $hosting);
            }
        }
        return self::catalog('sistemas');
    }

    private static function rel(array $data, array $subjects, array $systems, array $departments, array $sharings = []): array
    {
        return ['data' => $data, 'subjects' => $subjects, 'systems' => $systems, 'departments' => $departments, 'sharings' => $sharings];
    }

    private static function risk(string $title, string $category, ?int $activity, ?int $department, int $l, int $i, string $treatment, string $status, ?int $owner, ?array $residual, string $cause, string $consequence): array
    {
        return [
            'title' => $title, 'category' => $category, 'activity_id' => $activity, 'department_id' => $department,
            'likelihood' => $l, 'impact' => $i, 'residual_likelihood' => $residual[0] ?? null, 'residual_impact' => $residual[1] ?? null,
            'treatment' => $treatment, 'treatment_notes' => $treatment === 'transferir' ? 'Responsabilidades contratuais do operador.' : null,
            'status' => $status, 'owner_user_id' => $owner, 'cause' => $cause, 'consequence' => $consequence,
            'review_date' => self::date('+90 days'), 'created_by' => $owner,
        ];
    }

    private static function action(ActionRepository $repo, string $title, ?int $risk, ?int $activity, ?int $department, ?int $owner, string $priority, string $status, string $due, ?string $note = null): int
    {
        $dueDate = self::date($due);
        $id = $repo->create([
            'title' => $title, 'risk_id' => $risk, 'activity_id' => $activity, 'department_id' => $department, 'owner_user_id' => $owner,
            'priority' => $priority, 'status' => $status, 'due_date' => $dueDate,
            'completed_at' => $status === 'concluida' ? date('Y-m-d H:i:s', strtotime($dueDate . ' -2 days 10:00')) : null,
            'created_by' => $owner,
        ]);
        $repo->addUpdate($id, $owner, null, 'pendente', 'Ação criada.');
        if ($status !== 'pendente') {
            $repo->addUpdate($id, $owner, 'pendente', $status, $note);
        }
        AuditLogger::log('action.created', 'action', $id, null, ['titulo' => $title, 'situacao' => $status], ['user_id' => $owner, 'user_name' => null]);
        return $id;
    }

    private static function date(string $relative): string
    {
        return date('Y-m-d', strtotime($relative));
    }
}
