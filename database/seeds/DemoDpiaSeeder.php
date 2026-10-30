<?php

declare(strict_types=1);

namespace Isis\Database\Seeds;

use Isis\Auth\TenantContext;
use Isis\Core\Database;
use Isis\Repositories\DpiaRepository;
use Isis\Services\DpiaService;

/**
 * RIPD FICTÍCIOS para as empresas de demonstração: a clínica tem o do prontuário aprovado;
 * a loja tem o do programa de fidelidade em elaboração.
 */
final class DemoDpiaSeeder
{
    public static function run(string $segment): bool
    {
        $tid = TenantContext::id();
        if ((int) Database::value('SELECT COUNT(*) FROM dpias WHERE tenant_id = ?', [$tid]) > 0) {
            return false;
        }
        $users = array_column(Database::fetchAll('SELECT role, MIN(id) AS id FROM users WHERE tenant_id = ? GROUP BY role', [$tid]), null, 'role');
        $userName = static fn (string $role): string => (string) Database::value('SELECT name FROM users WHERE id = ?', [(int) $users[$role]['id']]);
        $repo = new DpiaRepository();

        if ($segment === 'saude') {
            $activity = Database::value('SELECT id FROM processing_activities WHERE tenant_id = ? AND name = ?', [$tid, 'Prontuário eletrônico do paciente']);
            if ($activity === null) {
                return false;
            }
            $id = $repo->create([
                'title' => 'RIPD · Prontuário eletrônico do paciente',
                'triggers' => json_encode(['dados_sensiveis', 'vulneraveis', 'transferencia_internacional', 'novas_tecnologias']),
                'need_justification' => 'Tratamento contínuo de dados de saúde de pacientes, inclusive crianças, em sistema na nuvem contratado de terceiro.',
                'nature' => "Os dados são coletados na recepção e durante o atendimento, registrados no prontuário eletrônico (PEP) em nuvem e, para documentos antigos, no arquivo físico.\nAcessam médicos, enfermagem e faturamento, cada um com perfil próprio. O fornecedor do PEP atua como operador.",
                'scope' => "Identificação, contato, convênio e dados clínicos (consultas, exames, diagnósticos e prescrições).\nCerca de 8.000 pacientes ativos por ano. Guarda mínima de 20 anos, conforme a Lei 13.787/2018.",
                'context' => 'Os pacientes esperam sigilo absoluto. Há atendimento de crianças, com dados informados pelos responsáveis legais. Não há histórico de incidentes com o PEP.',
                'purpose' => 'Prestar assistência à saúde com segurança, manter o histórico clínico e cumprir as obrigações do Conselho Federal de Medicina.',
                'stakeholders' => 'Encarregado, diretoria clínica, coordenação de enfermagem, TI e o fornecedor do PEP (questionário de segurança e contrato).',
                'necessity' => "Hipótese legal: tutela da saúde (art. 11, II, f) e obrigação legal de guarda do prontuário.\nSó são registrados os dados clínicos necessários ao atendimento. Os pacientes são informados pelo aviso de privacidade da recepção e podem pedir acesso e cópia pelo canal do titular.",
                'safeguards' => 'Perfis de acesso por função, registro de acessos no PEP, criptografia em trânsito e em repouso no fornecedor, termo de confidencialidade dos colaboradores e treinamento anual de LGPD.',
                'conclusion' => 'aceitavel_com_medidas',
                'conclusion_notes' => 'Os riscos residuais ficam baixos ou médios desde que a revisão periódica de acessos e a autenticação em dois fatores no PEP sejam implantadas.',
                'dpo_opinion' => 'De acordo com o tratamento, condicionado à conclusão do plano de ação. Recomendo revisar este relatório em 12 meses ou se o fornecedor do PEP mudar.',
                'owner_user_id' => (int) $users['encarregado']['id'],
                'review_due_date' => date('Y-m-d', strtotime('+1 year')),
                'status' => 'em_aprovacao',
                'submitted_at' => date('Y-m-d H:i:s'),
                'created_by' => (int) $users['encarregado']['id'],
            ], [(int) $activity]);
            $dpia = $repo->findFull($id);
            $snapshot = (string) json_encode(DpiaService::document($dpia), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $repo->approve($dpia, $snapshot, (int) $users['admin']['id'], $userName('admin'), 'Aprovado na reunião de diretoria.');
            return true;
        }

        $activity = Database::value('SELECT id FROM processing_activities WHERE tenant_id = ? AND name = ?', [$tid, 'Programa de fidelidade']);
        if ($activity === null) {
            return false;
        }
        $repo->create([
            'title' => 'RIPD · Programa de fidelidade',
            'triggers' => json_encode(['larga_escala', 'decisao_automatizada', 'combinacao']),
            'need_justification' => 'O programa cruza o histórico de compras para oferecer benefícios personalizados.',
            'nature' => 'Dados coletados no cadastro do programa e a cada compra (loja física e virtual), guardados no sistema de fidelidade.',
            'purpose' => 'Conceder pontos e benefícios e personalizar ofertas aos participantes.',
            'owner_user_id' => (int) $users['encarregado']['id'],
            'review_due_date' => date('Y-m-d', strtotime('+1 year')),
            'created_by' => (int) $users['encarregado']['id'],
        ], [(int) $activity]);
        return true;
    }
}
