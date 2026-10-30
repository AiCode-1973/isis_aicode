<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Core\HttpException;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Repositories\ActionRepository;
use Isis\Repositories\IncidentRepository;
use Isis\Repositories\RequestRepository;
use Isis\Repositories\RiskRepository;
use Isis\Repositories\TrainingRepository;
use Isis\Repositories\VendorRepository;
use Isis\Services\ReportService;
use Isis\Services\RiskMatrix;
use Isis\Support\Labels;

/**
 * Relatório de conformidade (para direção, auditoria e fiscalização) e exportações em CSV.
 */
final class ReportController extends Controller
{
    /** tipo => [rótulo, descrição] */
    public const array EXPORTS = [
        'riscos' => ['Riscos', 'Todos os riscos com avaliação inerente e residual, tratamento e responsável.'],
        'acoes' => ['Planos de ação', 'Ações com responsável, prazo, situação e vínculos.'],
        'solicitacoes' => ['Solicitações de titulares', 'Protocolo, tipo, datas, prazo e situação. Sem dados pessoais do titular.'],
        'incidentes' => ['Incidentes de segurança', 'Classificação, datas, risco e comunicações. Sem a descrição detalhada.'],
        'fornecedores' => ['Fornecedores', 'Papel, avaliação, nota, risco e contratos com cláusulas de dados.'],
        'treinamentos' => ['Treinamentos', 'Participantes, situação, nota e certificados.'],
    ];

    public function index(Request $request): Response
    {
        [$from, $to] = $this->period($request);
        AuditLogger::log('report.viewed', 'report', null, null, ['de' => $from, 'ate' => $to]);
        return $this->view('reports/index', [
            'title' => 'Relatório de conformidade',
            'r' => ReportService::build($from, $to),
            'exports' => self::EXPORTS,
            'generatedBy' => Auth::user()['name'],
        ]);
    }

    public function export(Request $request, string $type): Response
    {
        if (!isset(self::EXPORTS[$type])) {
            throw new HttpException(404);
        }
        [$header, $rows] = match ($type) {
            'riscos' => $this->risks(),
            'acoes' => $this->actions(),
            'solicitacoes' => $this->requests(),
            'incidentes' => $this->incidents(),
            'fornecedores' => $this->vendors(),
            'treinamentos' => $this->trainings(),
        };
        AuditLogger::log('report.exported', 'report', null, null, ['tipo' => $type, 'registros' => count($rows)]);
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header, ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, array_map(static fn ($v): string => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v, $row), ';', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);
        return Response::download($csv, "isis-{$type}-" . date('Y-m-d') . '.csv', 'text/csv; charset=UTF-8');
    }

    /** @return array{0: string, 1: string} */
    private function period(Request $request): array
    {
        $days = (int) ($request->query('dias') ?? 90);
        $days = in_array($days, [30, 90, 180, 365], true) ? $days : 90;
        return [date('Y-m-d', strtotime("-{$days} days")), date('Y-m-d')];
    }

    private function risks(): array
    {
        $rows = [];
        foreach ((new RiskRepository())->search([]) as $r) {
            $level = RiskMatrix::levelOf((int) $r['likelihood'], (int) $r['impact']);
            $residual = $r['residual_likelihood'] ? RiskMatrix::label(RiskMatrix::levelOf((int) $r['residual_likelihood'], (int) $r['residual_impact'])) . ' (' . ($r['residual_likelihood'] * $r['residual_impact']) . ')' : '';
            $rows[] = [code('RSK', (int) $r['seq']), $r['title'], label(Labels::RISK_CATEGORY, $r['category']), $r['activity_seq'] ? code('OP', (int) $r['activity_seq']) . ' ' . $r['activity_name'] : '',
                $r['likelihood'], $r['impact'], RiskMatrix::label($level) . ' (' . $r['score'] . ')', $residual, label(Labels::RISK_TREATMENT, $r['treatment']),
                label(Labels::RISK_STATUS, $r['status']), $r['owner_name'], $r['actions_done'] . '/' . $r['actions_count'], fmt_date($r['review_date'])];
        }
        return [['Código', 'Risco', 'Categoria', 'Operação', 'Probabilidade', 'Impacto', 'Nível', 'Residual', 'Tratamento', 'Situação', 'Responsável', 'Ações concluídas', 'Revisão'], $rows];
    }

    private function actions(): array
    {
        $rows = [];
        foreach ((new ActionRepository())->search([]) as $x) {
            $rows[] = [code('ACT', (int) $x['seq']), $x['title'], $x['risk_seq'] ? code('RSK', (int) $x['risk_seq']) : '', $x['incident_seq'] ? code('INC', (int) $x['incident_seq']) : '',
                $x['owner_name'], $x['department_name'], label(Labels::PRIORITY, $x['priority']), fmt_date($x['due_date']), label(Labels::ACTION_STATUS, $x['status']),
                fmt_datetime($x['completed_at']), $x['overdue'] ? 'Sim' : 'Não', (int) $x['evidence_count']];
        }
        return [['Código', 'Ação', 'Risco', 'Incidente', 'Responsável', 'Setor', 'Prioridade', 'Prazo', 'Situação', 'Concluída em', 'Vencida', 'Evidências'], $rows];
    }

    private function requests(): array
    {
        $rows = [];
        foreach ((new RequestRepository())->search([]) as $r) {
            $rows[] = [$r['protocol'], label(Labels::RIGHT_TYPE, $r['right_type']), label(Labels::REQUEST_CHANNEL, $r['channel']), fmt_datetime($r['received_at']), fmt_date($r['due_date']),
                label(Labels::REQUEST_STATUS, $r['status']), fmt_datetime($r['responded_at']), $r['responded_at'] ? (substr((string) $r['responded_at'], 0, 10) <= $r['due_date'] ? 'Sim' : 'Não') : '',
                $r['identity_verified'] ? 'Sim' : 'Não', $r['assigned_name']];
        }
        return [['Protocolo', 'Pedido', 'Canal', 'Recebida em', 'Prazo', 'Situação', 'Respondida em', 'No prazo', 'Identidade confirmada', 'Responsável'], $rows];
    }

    private function incidents(): array
    {
        $rows = [];
        foreach ((new IncidentRepository())->search([]) as $i) {
            $rows[] = [code('INC', (int) $i['seq']), $i['title'], label(Labels::INCIDENT_CATEGORY, $i['category']), label(Labels::SEVERITY, $i['severity']),
                explode(' (', Labels::INCIDENT_STATUS[$i['status']])[0], fmt_datetime($i['detected_at']), $i['personal_data'] === null ? 'Não se sabe' : ($i['personal_data'] ? 'Sim' : 'Não'),
                $i['sensitive_data'] ? 'Sim' : 'Não', $i['subjects_affected'], Labels::RISK_TO_SUBJECTS[$i['risk_to_subjects']], fmt_date($i['anpd_deadline']),
                fmt_date($i['anpd_notified_at']), $i['anpd_protocol'], fmt_date($i['subjects_notified_at']), $i['owner_name'], fmt_datetime($i['closed_at'])];
        }
        return [['Código', 'Incidente', 'Tipo', 'Gravidade', 'Situação', 'Percebido em', 'Dados pessoais', 'Dados sensíveis', 'Titulares afetados', 'Risco aos titulares',
            'Prazo ANPD', 'ANPD comunicada em', 'Protocolo ANPD', 'Titulares comunicados em', 'Responsável', 'Encerrado em'], $rows];
    }

    private function vendors(): array
    {
        $rows = [];
        foreach ((new VendorRepository())->search([]) as $v) {
            $rows[] = [code('FOR', (int) $v['seq']), $v['name'], $v['cnpj'] ? fmt_cnpj($v['cnpj']) : '', $v['service'], explode(' (', label(Labels::VENDOR_ROLE, $v['role']))[0],
                $v['sensitive_data'] ? 'Sim' : 'Não', $v['international'] ? 'Sim' : 'Não', label(Labels::LEVEL, $v['risk_level']), $v['last_score'], fmt_date($v['last_assessment_at']),
                fmt_date($v['next_review_date']), (int) $v['dpa_count'] > 0 ? 'Sim' : 'Não', label(Labels::VENDOR_STATUS, $v['status'])];
        }
        return [['Código', 'Fornecedor', 'CNPJ', 'Serviço', 'Papel', 'Dados sensíveis', 'Exterior', 'Risco', 'Nota', 'Avaliado em', 'Próxima revisão', 'Contrato com cláusulas', 'Situação'], $rows];
    }

    private function trainings(): array
    {
        $repo = new TrainingRepository();
        $rows = [];
        foreach ($repo->modules() as $m) {
            foreach ($repo->enrollments((int) $m['id']) as $e) {
                $rows[] = [code('TRE', (int) $m['seq']), $m['title'], $e['user_name'], $e['department_name'], label(Labels::ENROLLMENT_STATUS, $e['status']),
                    fmt_date($e['due_date']), (int) $e['attempts'], $e['best_score'], fmt_date($e['completed_at']), $e['certificate_code'], fmt_date($e['certificate_valid_until'])];
            }
        }
        return [['Código', 'Treinamento', 'Participante', 'Setor', 'Situação', 'Prazo', 'Tentativas', 'Nota', 'Concluído em', 'Certificado', 'Válido até'], $rows];
    }
}
