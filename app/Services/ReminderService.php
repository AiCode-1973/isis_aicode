<?php

declare(strict_types=1);

namespace Isis\Services;

use Isis\Auth\TenantContext;
use Isis\Core\Database;
use Isis\Core\Mailer;

/**
 * Lembretes diários por e-mail: um resumo por pessoa com o que vence ou venceu.
 * Rode uma vez por dia (agendador): php bin/isis lembretes
 */
final class ReminderService
{
    /** @return array{tenants: int, sent: int, skipped: int} */
    public static function run(?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $stats = ['tenants' => 0, 'sent' => 0, 'skipped' => 0];
        $tenants = Database::fetchAll("SELECT * FROM tenants WHERE status = 'active' AND onboarding_completed_at IS NOT NULL");
        foreach ($tenants as $tenant) {
            $stats['tenants']++;
            TenantContext::runAs((int) $tenant['id'], static function () use ($tenant, $today, &$stats): void {
                foreach (self::usersWithItems($tenant, $today) as $entry) {
                    $already = Database::value('SELECT 1 FROM reminder_log WHERE tenant_id = ? AND user_id = ? AND sent_on = ?', [$tenant['id'], $entry['user']['id'], $today]);
                    if ($already !== null) {
                        $stats['skipped']++;
                        continue;
                    }
                    $count = array_sum(array_map('count', $entry['groups']));
                    Mailer::sendTemplate($entry['user']['email'], "ÍSIS: {$count} pendência(s) de proteção de dados", 'reminders', [
                        'name' => explode(' ', (string) $entry['user']['name'])[0],
                        'company' => $tenant['trade_name'] ?: $tenant['legal_name'],
                        'groups' => $entry['groups'],
                        'link' => absolute_url('/painel'),
                    ]);
                    Database::execute('INSERT INTO reminder_log (tenant_id, user_id, sent_on, items) VALUES (?, ?, ?, ?)', [$tenant['id'], $entry['user']['id'], $today, $count]);
                    $stats['sent']++;
                }
            });
        }
        return $stats;
    }

    /**
     * Pendências por pessoa da empresa do contexto.
     *
     * @return list<array{user: array, groups: array<string, list<array{text: string, due: ?string, late: bool, link: string}>>}>
     */
    public static function usersWithItems(array $tenant, string $today): array
    {
        $tid = TenantContext::id();
        $soon3 = date('Y-m-d', strtotime($today . ' +3 days'));
        $soon7 = date('Y-m-d', strtotime($today . ' +7 days'));
        $monday = (int) date('N', strtotime($today)) === 1;
        $users = Database::fetchAll(
            "SELECT id, name, email, role FROM users WHERE tenant_id = ? AND status = 'active' AND email_verified_at IS NOT NULL",
            [$tid],
        );
        $hasDpo = in_array('encarregado', array_column($users, 'role'), true);
        $result = [];
        foreach ($users as $user) {
            $uid = (int) $user['id'];
            $groups = [];
            $item = static fn (string $text, ?string $due, string $link): array => [
                'text' => $text, 'due' => $due, 'late' => $due !== null && $due < $today, 'link' => absolute_url($link),
            ];

            foreach (Database::fetchAll(
                "SELECT id, seq, title, due_date FROM actions WHERE tenant_id = ? AND owner_user_id = ? AND status IN ('pendente','em_andamento')
                   AND due_date IS NOT NULL AND due_date <= ? ORDER BY due_date", [$tid, $uid, $soon3]) as $a) {
                $groups['Ações dos planos'][] = $item(code('ACT', (int) $a['seq']) . ' · ' . $a['title'], $a['due_date'], '/planos/' . $a['id']);
            }
            foreach (Database::fetchAll(
                "SELECT m.id, m.title, e.due_date FROM training_enrollments e JOIN training_modules m ON m.tenant_id = e.tenant_id AND m.id = e.module_id
                  WHERE e.tenant_id = ? AND e.user_id = ? AND e.status <> 'concluido' AND m.status = 'publicado' AND e.due_date IS NOT NULL AND e.due_date <= ?
               ORDER BY e.due_date", [$tid, $uid, $soon7]) as $t) {
                $groups['Treinamentos'][] = $item($t['title'], $t['due_date'], '/treinamentos/' . $t['id']);
            }
            foreach (Database::fetchAll(
                "SELECT id, protocol, due_date FROM dsar_requests WHERE tenant_id = ? AND assigned_user_id = ?
                   AND status IN ('recebida','em_verificacao','em_atendimento') AND due_date <= ? ORDER BY due_date", [$tid, $uid, $soon3]) as $r) {
                $groups['Solicitações de titulares'][] = $item('Protocolo ' . $r['protocol'], $r['due_date'], '/titulares/' . $r['id']);
            }
            if ($monday) {
                foreach (Database::fetchAll(
                    'SELECT p.id, p.title FROM policies p WHERE p.tenant_id = ? AND p.archived_at IS NULL AND p.requires_ack = 1 AND p.current_version_id IS NOT NULL
                       AND NOT EXISTS (SELECT 1 FROM policy_acknowledgements a WHERE a.tenant_id = p.tenant_id AND a.version_id = p.current_version_id AND a.user_id = ?)',
                    [$tid, $uid]) as $p) {
                    $groups['Políticas para ler e dar ciência'][] = $item($p['title'], null, '/politicas/' . $p['id']);
                }
            }
            // Quem conduz a privacidade: o encarregado (ou o administrador, se não houver encarregado)
            if ($user['role'] === 'encarregado' || (!$hasDpo && $user['role'] === 'admin')) {
                foreach (Database::fetchAll(
                    "SELECT id, seq, title, anpd_deadline FROM incidents WHERE tenant_id = ? AND risk_to_subjects = 'relevante' AND anpd_notified_at IS NULL
                       AND anpd_deadline <= ? ORDER BY anpd_deadline", [$tid, date('Y-m-d', strtotime($today . ' +1 day'))]) as $i) {
                    $groups['Comunicação de incidente à ANPD'][] = $item(code('INC', (int) $i['seq']) . ' · ' . $i['title'], $i['anpd_deadline'], '/incidentes/' . $i['id']);
                }
                foreach (Database::fetchAll("SELECT id, seq, title FROM incidents WHERE tenant_id = ? AND status = 'comunicado'", [$tid]) as $i) {
                    $groups['Incidentes aguardando análise'][] = $item(code('INC', (int) $i['seq']) . ' · ' . $i['title'], null, '/incidentes/' . $i['id']);
                }
                foreach (Database::fetchAll(
                    "SELECT id, protocol, due_date FROM dsar_requests WHERE tenant_id = ? AND assigned_user_id IS NULL
                       AND status IN ('recebida','em_verificacao','em_atendimento') ORDER BY due_date", [$tid]) as $r) {
                    $groups['Solicitações sem responsável'][] = $item('Protocolo ' . $r['protocol'], $r['due_date'], '/titulares/' . $r['id']);
                }
            }
            if ($groups !== []) {
                $result[] = ['user' => $user, 'groups' => $groups];
            }
        }
        return $result;
    }
}
