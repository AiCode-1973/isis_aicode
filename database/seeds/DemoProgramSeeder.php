<?php

declare(strict_types=1);

namespace Isis\Database\Seeds;

use Isis\Auth\TenantContext;
use Isis\Core\Database;
use Isis\Repositories\PolicyRepository;
use Isis\Repositories\TrainingRepository;
use Isis\Repositories\VendorRepository;
use Isis\Services\Templates;
use Isis\Services\TrainingService;
use Isis\Services\VendorQuestionnaire;

/**
 * Fornecedores, políticas e treinamentos FICTÍCIOS para as empresas de demonstração (fase 4).
 */
final class DemoProgramSeeder
{
    public static function run(string $segment, string $company): bool
    {
        $tid = TenantContext::id();
        if ((int) Database::value('SELECT COUNT(*) FROM vendors WHERE tenant_id = ?', [$tid]) > 0) {
            return false;
        }
        $users = array_map('intval', array_column(
            Database::fetchAll('SELECT role, MIN(id) AS id FROM users WHERE tenant_id = ? GROUP BY role', [$tid]), 'id', 'role'));
        $activity = static fn (string $name): ?int => ($v = Database::value(
            'SELECT id FROM processing_activities WHERE tenant_id = ? AND name = ?', [$tid, $name])) === null ? null : (int) $v;

        Database::transaction(static function () use ($segment, $company, $users, $activity, $tid): void {
            $clinic = $segment === 'saude';
            $all = static fn (string $a): array => array_fill_keys(array_keys(VendorQuestionnaire::QUESTIONS), $a);

            // Fornecedores
            $vendors = $clinic ? [
                ['Laboratório Análise Certa (fictício)', 'Exames laboratoriais', 'operador', 1, 0, ['Prontuário eletrônico do paciente'], ['contrato' => 'sim', 'internacional' => 'na', 'certificacao' => 'nao'] + $all('sim'), true, '+8 months'],
                ['Escritório de contabilidade (fictício)', 'Folha de pagamento e obrigações trabalhistas', 'operador', 0, 0, ['Gestão de colaboradores e folha de pagamento'], ['contrato' => 'nao', 'criptografia' => 'parcial', 'certificacao' => 'nao', 'internacional' => 'na', 'treinamento' => 'nao'] + $all('parcial'), false, '+20 days'],
                ['NuvemSaúde Sistemas (fictício)', 'Prontuário eletrônico em nuvem', 'operador', 1, 1, ['Prontuário eletrônico do paciente', 'Agendamento de consultas'], null, false, null],
            ] : [
                ['Transportadora Rápida (fictícia)', 'Entrega de pedidos', 'operador', 0, 0, ['Cadastro de clientes da loja virtual'], ['internacional' => 'na', 'certificacao' => 'nao'] + $all('sim'), true, '+5 months'],
                ['MailMax Marketing (fictício)', 'Disparo de e-mail marketing', 'operador', 0, 1, ['E-mail marketing e ofertas'], ['contrato' => 'parcial', 'internacional' => 'nao', 'suboperadores' => 'nao'] + $all('parcial'), false, null],
            ];
            $vrepo = new VendorRepository();
            foreach ($vendors as [$name, $service, $role, $sensitive, $international, $acts, $answers, $dpa, $contractEnd]) {
                $id = $vrepo->create([
                    'name' => $name, 'service' => $service, 'role' => $role, 'data_access' => 1, 'sensitive_data' => $sensitive,
                    'international' => $international, 'country' => $international ? 'Estados Unidos' : null,
                    'status' => $answers ? 'ativo' : 'em_avaliacao', 'owner_user_id' => $users['encarregado'],
                    'contact_name' => 'Contato Comercial', 'contact_email' => 'contato@fornecedor.example',
                ], array_values(array_filter(array_map($activity, $acts))));
                if ($answers !== null) {
                    $result = VendorQuestionnaire::evaluate($answers, (bool) $sensitive);
                    $vrepo->addAssessment($id, $answers, $result['score'], $result['level'], 'Avaliação com base no contrato e em questionário respondido pelo fornecedor.',
                        $users['encarregado'], date('Y-m-d', strtotime($result['level'] === 'alto' ? '+6 months' : '+12 months')));
                }
                if ($contractEnd !== null) {
                    $vrepo->addContract($id, ['title' => 'Contrato de prestação de serviços', 'reference' => 'CT-' . date('Y') . '-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT),
                        'start_date' => date('Y-m-d', strtotime('-1 year')), 'end_date' => date('Y-m-d', strtotime($contractEnd)), 'has_data_clauses' => $dpa ? 1 : 0]);
                }
            }

            // Políticas a partir dos modelos: a primeira vigente, a segunda aguardando aprovação
            $prepo = new PolicyRepository();
            $templates = Templates::policies($company);
            foreach (array_slice($templates, 0, $clinic ? 3 : 2) as $i => $t) {
                $pid = $prepo->create(['title' => $t['title'], 'category' => $t['category'], 'review_months' => $t['review_months'], 'requires_ack' => 1,
                    'owner_user_id' => $users['encarregado']], $t['content'], 'Versão inicial a partir do modelo.', $users['encarregado']);
                $version = Database::fetch('SELECT * FROM policy_versions WHERE tenant_id = ? AND policy_id = ? ORDER BY id DESC LIMIT 1', [$tid, $pid]);
                if ($i === 1) {
                    $prepo->setVersionStatus((int) $version['id'], 'em_aprovacao', ['submitted_at' => date('Y-m-d H:i:s')]);
                    continue;
                }
                if ($i === 0 || $clinic) {
                    $prepo->approve($prepo->findOrFail($pid), $version, $users['admin'], null);
                    foreach (['admin', 'encarregado', 'gestor'] as $role) {
                        $prepo->acknowledge((int) $version['id'], $users[$role], '127.0.0.1');
                    }
                }
            }

            // Treinamento modelo publicado, designado a todos; parte já concluiu
            $trepo = new TrainingRepository();
            $t = Templates::lgpdTraining();
            $mid = $trepo->createModule(['title' => $t['title'], 'summary' => $t['summary'], 'content' => $t['content'], 'duration_minutes' => $t['duration_minutes'],
                'passing_score' => 70, 'certificate_months' => 12, 'status' => 'publicado'], $t['questions']);
            $trepo->enroll($mid, array_values($users), $users['admin'], date('Y-m-d', strtotime('+10 days')));
            foreach (['encarregado' => 100, 'gestor' => 80, 'auditor' => 60] as $role => $score) {
                $enrollment = $trepo->enrollment($mid, $users[$role]);
                $passed = $score >= 70;
                $trepo->saveEnrollment((int) $enrollment['id'], [
                    'status' => $passed ? 'concluido' : 'reprovado', 'attempts' => 1, 'best_score' => $score,
                    'started_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
                    'completed_at' => $passed ? date('Y-m-d H:i:s', strtotime('-2 days')) : null,
                    'certificate_code' => $passed ? TrainingService::certificateCode() : null,
                    'certificate_valid_until' => $passed ? date('Y-m-d', strtotime('+12 months -2 days')) : null,
                ]);
            }
        });
        return true;
    }
}
