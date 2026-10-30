<?php

declare(strict_types=1);

namespace Isis\Database\Seeds;

use Isis\Auth\TenantContext;
use Isis\Core\Database;
use Isis\Repositories\IncidentRepository;
use Isis\Repositories\RequestRepository;
use Isis\Support\Deadline;

/**
 * Solicitações de titulares e incidentes FICTÍCIOS para as empresas de demonstração (fase 3).
 * Os dados pessoais dos titulares são inventados e ficam criptografados com a APP_KEY de quem roda o seed.
 */
final class DemoPrivacySeeder
{
    public static function run(string $segment): bool
    {
        $tid = TenantContext::id();
        if ((int) Database::value('SELECT COUNT(*) FROM dsar_requests WHERE tenant_id = ?', [$tid])
            + (int) Database::value('SELECT COUNT(*) FROM incidents WHERE tenant_id = ?', [$tid]) > 0) {
            return false;
        }
        $users = array_map('intval', array_column(
            Database::fetchAll('SELECT role, MIN(id) AS id FROM users WHERE tenant_id = ? GROUP BY role', [$tid]), 'id', 'role'));
        $activity = static fn (string $name): ?int => ($v = Database::value(
            'SELECT id FROM processing_activities WHERE tenant_id = ? AND name = ?', [$tid, $name])) === null ? null : (int) $v;

        Database::transaction(static function () use ($segment, $users, $activity): void {
            $clinic = $segment === 'saude';
            $requests = $clinic ? [
                ['acesso', 'portal', 'João Pereira da Silva', 'joao.pereira@exemplo.example', 'Gostaria de receber cópia do meu prontuário e dos exames feitos em 2025.', '-4 days', 'em_atendimento', 'Prontuário eletrônico do paciente', true],
                ['eliminacao', 'email', 'Ana Beatriz Moura', 'ana.moura@exemplo.example', 'Peço a exclusão do meu cadastro e o fim das mensagens de lembrete.', '-20 days', 'recebida', 'Lembretes de consulta por WhatsApp', false],
                ['correcao', 'telefone', 'Marcos Antônio Reis', 'marcos.reis@exemplo.example', 'Meu endereço e telefone estão desatualizados.', '-9 days', 'respondida', 'Agendamento de consultas', true],
                ['confirmacao', 'portal', 'Patrícia Gomes', 'patricia.gomes@exemplo.example', 'Quero saber se a clínica tem dados meus e quais.', '-13 days', 'em_verificacao', null, false],
            ] : [
                ['revogacao', 'portal', 'Roberto Lima', 'roberto.lima@exemplo.example', 'Não quero mais receber e-mails de ofertas.', '-2 days', 'recebida', 'E-mail marketing e ofertas', false],
                ['acesso', 'email', 'Carla Nogueira', 'carla.nogueira@exemplo.example', 'Quais dados meus vocês guardam e com quem compartilham?', '-6 days', 'em_atendimento', 'Cadastro de clientes da loja virtual', true],
            ];
            $repo = new RequestRepository();
            foreach ($requests as [$right, $channel, $name, $email, $desc, $when, $status, $activityName, $verified]) {
                $received = new \DateTimeImmutable($when . ' 10:30');
                $created = $repo->create([
                    'right_type' => $right, 'channel' => $channel, 'requester_relation' => 'titular',
                    'requester_name' => $name, 'requester_email' => $email, 'requester_phone' => null, 'requester_document' => null,
                    'description' => $desc, 'received_at' => $received->format('Y-m-d H:i:s'), 'due_date' => Deadline::requestDue($received),
                    'status' => $status, 'assigned_user_id' => $status === 'recebida' ? null : $users['encarregado'],
                    'activity_id' => $activityName ? $activity($activityName) : null, 'identity_verified' => $verified ? 1 : 0,
                    'identity_notes' => $verified ? 'Confirmada pelo e-mail cadastrado.' : null,
                ]);
                $repo->addEvent($created['id'], null, 'criada', 'Solicitação recebida.', null, 'recebida', true);
                if ($status !== 'recebida') {
                    $repo->addEvent($created['id'], $users['encarregado'], 'status', 'Em análise pelo encarregado.', 'recebida', $status, true);
                }
                if ($status === 'respondida') {
                    $response = 'Seus dados de contato foram atualizados conforme solicitado. Obrigado por nos avisar.';
                    $repo->save($created['id'], ['response' => $response, 'responded_at' => $received->modify('+3 days')->format('Y-m-d H:i:s')]);
                    $repo->addEvent($created['id'], $users['encarregado'], 'resposta', $response, 'em_atendimento', 'respondida', true);
                }
            }

            $incidents = new IncidentRepository();
            $list = $clinic ? [
                ['Planilha de pacientes enviada a destinatário errado', 'erro_humano', 'Funcionária do faturamento enviou planilha com nomes e convênios de 42 pacientes para um e-mail externo por engano.', '-1 day', 'alta', 'em_analise', 1, 1, 42, 'relevante', 'Faturamento de convênios', null],
                ['Tentativa de phishing contra a recepção', 'phishing', 'E-mail falso pedindo atualização de senha do sistema de agendamento. Ninguém clicou.', '-12 days', 'baixa', 'encerrado', 0, 0, null, 'baixo', null, 'Alerta enviado a toda a equipe.'],
            ] : [
                ['Painel da loja acessado com senha de ex-funcionário', 'acesso_indevido', 'Login de conta de ex-funcionário detectado no painel da loja virtual; sem evidência de exportação de dados.', '-5 days', 'media', 'contido', 1, 0, null, 'indeterminado', 'Cadastro de clientes da loja virtual', 'Conta desativada e senhas redefinidas.'],
            ];
            foreach ($list as [$title, $category, $desc, $when, $severity, $status, $personal, $sensitive, $subjects, $risk, $activityName, $containment]) {
                $detected = new \DateTimeImmutable($when . ' 09:15');
                $id = $incidents->create([
                    'title' => $title, 'category' => $category, 'description' => $desc, 'severity' => $severity, 'status' => $status,
                    'detected_at' => $detected->format('Y-m-d H:i:s'), 'personal_data' => $personal, 'sensitive_data' => $sensitive,
                    'subjects_affected' => $subjects, 'risk_to_subjects' => $risk, 'activity_id' => $activityName ? $activity($activityName) : null,
                    'owner_user_id' => $users['encarregado'], 'reported_by' => $users['colaborador'], 'containment' => $containment,
                    'anpd_deadline' => $risk === 'relevante' ? Deadline::addBusinessDays($detected, Deadline::INCIDENT_BUSINESS_DAYS) : null,
                    'closed_at' => $status === 'encerrado' ? $detected->modify('+2 days')->format('Y-m-d H:i:s') : null,
                ]);
                $incidents->addEvent($id, $users['colaborador'], 'registro', 'Incidente comunicado.', null, 'comunicado');
                $incidents->addEvent($id, $users['encarregado'], 'classificacao', 'Classificado pelo encarregado.', 'comunicado', $status);
            }
        });
        return true;
    }
}
