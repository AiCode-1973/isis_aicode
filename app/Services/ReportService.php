<?php

declare(strict_types=1);

namespace Isis\Services;

use Isis\Auth\TenantContext;
use Isis\Core\Database;
use Isis\Repositories\ActionRepository;
use Isis\Repositories\ActivityRepository;
use Isis\Repositories\AuditRepository;
use Isis\Repositories\IncidentRepository;
use Isis\Repositories\PolicyRepository;
use Isis\Repositories\RequestRepository;
use Isis\Repositories\RiskRepository;
use Isis\Repositories\TenantRepository;
use Isis\Repositories\TrainingRepository;
use Isis\Repositories\VendorRepository;

/**
 * Reúne os números da empresa para o relatório de conformidade (situação atual + período).
 */
final class ReportService
{
    /** @return array<string, mixed> */
    public static function build(string $from, string $to): array
    {
        $tid = TenantContext::id();
        $period = [$tid, $from . ' 00:00:00', $to . ' 23:59:59'];
        $count = static fn (string $sql, array $params) => (int) Database::value($sql, $params);

        $activities = (new ActivityRepository())->search([]);
        $risks = (new RiskRepository())->search(['open' => true]);

        return [
            'tenant' => (new TenantRepository())->current(),
            'from' => $from,
            'to' => $to,
            'inventory' => (new ActivityRepository())->stats(),
            'activities' => array_values(array_filter($activities, static fn (array $a): bool => $a['status'] !== 'descontinuada')),
            'risks' => (new RiskRepository())->stats(),
            'topRisks' => array_slice($risks, 0, 10),
            'heatmap' => (new RiskRepository())->heatmap(),
            'actions' => (new ActionRepository())->stats(),
            'overdueActions' => (new ActionRepository())->search(['overdue' => true]),
            'requests' => (new RequestRepository())->stats(),
            'requestsPeriod' => [
                'received' => $count('SELECT COUNT(*) FROM dsar_requests WHERE tenant_id = ? AND received_at BETWEEN ? AND ?', $period),
                'answered' => $count("SELECT COUNT(*) FROM dsar_requests WHERE tenant_id = ? AND responded_at BETWEEN ? AND ?", $period),
                'on_time' => $count("SELECT COUNT(*) FROM dsar_requests WHERE tenant_id = ? AND responded_at BETWEEN ? AND ? AND DATE(responded_at) <= due_date", $period),
                'by_type' => Database::fetchAll('SELECT right_type, COUNT(*) AS n FROM dsar_requests WHERE tenant_id = ? AND received_at BETWEEN ? AND ? GROUP BY right_type ORDER BY n DESC', $period),
            ],
            'incidents' => (new IncidentRepository())->stats(),
            'incidentsPeriod' => [
                'total' => $count('SELECT COUNT(*) FROM incidents WHERE tenant_id = ? AND detected_at BETWEEN ? AND ?', $period),
                'relevant' => $count("SELECT COUNT(*) FROM incidents WHERE tenant_id = ? AND detected_at BETWEEN ? AND ? AND risk_to_subjects = 'relevante'", $period),
                'notified_on_time' => $count("SELECT COUNT(*) FROM incidents WHERE tenant_id = ? AND detected_at BETWEEN ? AND ? AND risk_to_subjects = 'relevante' AND DATE(anpd_notified_at) <= anpd_deadline", $period),
                'by_severity' => Database::fetchAll('SELECT COALESCE(severity, \'a_classificar\') AS severity, COUNT(*) AS n FROM incidents WHERE tenant_id = ? AND detected_at BETWEEN ? AND ? GROUP BY severity', $period),
            ],
            'vendors' => (new VendorRepository())->stats(),
            'vendorsByRisk' => Database::fetchAll("SELECT COALESCE(risk_level, 'nao_avaliado') AS level, COUNT(*) AS n FROM vendors WHERE tenant_id = ? AND status <> 'encerrado' GROUP BY level", [$tid]),
            'policies' => (new PolicyRepository())->stats(),
            'trainings' => (new TrainingRepository())->stats(),
            'trainingsPeriod' => $count("SELECT COUNT(*) FROM training_enrollments WHERE tenant_id = ? AND completed_at BETWEEN ? AND ?", $period),
            'audit' => [
                'events' => $count('SELECT COUNT(*) FROM audit_logs WHERE tenant_id = ? AND created_at BETWEEN ? AND ?', $period),
                'security' => $count("SELECT COUNT(*) FROM audit_logs WHERE tenant_id = ? AND created_at BETWEEN ? AND ? AND (action LIKE 'access.%' OR action IN ('auth.login_failed','auth.login_blocked'))", $period),
                'chain' => (new AuditRepository())->verifyChain(),
            ],
        ];
    }
}
