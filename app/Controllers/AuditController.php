<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Audit\AuditLogger;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Repositories\AuditRepository;
use Isis\Repositories\UserRepository;

/**
 * Trilha de auditoria: consulta com filtros, exportação CSV e verificação de integridade.
 */
final class AuditController extends Controller
{
    private const int PER_PAGE = 50;

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $page = max(1, (int) ($request->query('pagina') ?? 1));
        $repo = new AuditRepository();
        $result = $repo->search($filters, $page, self::PER_PAGE);

        return $this->view('audit/index', [
            'title' => 'Trilha de auditoria',
            'filters' => $filters,
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / self::PER_PAGE)),
            'actions' => $repo->distinctActions(),
            'users' => (new UserRepository())->options(),
            'query' => http_build_query(array_filter([
                'acao' => $filters['action'], 'usuario' => $filters['user_id'], 'de' => $filters['from'], 'ate' => $filters['to'],
            ])),
        ]);
    }

    public function export(Request $request): Response
    {
        $filters = $this->filters($request);
        $rows = (new AuditRepository())->export($filters);
        AuditLogger::log('audit.exported', 'audit', null, null, ['filtros' => array_filter($filters), 'registros' => count($rows)]);

        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // BOM para o Excel reconhecer UTF-8
        fputcsv($out, ['ID', 'Data/hora', 'Usuário', 'Ação', 'Entidade', 'ID da entidade', 'IP', 'Alterações', 'Hash'], ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['id'],
                substr((string) $row['created_at'], 0, 19),
                self::csvSafe($row['user_name'] ?? 'Sistema'),
                self::csvSafe(audit_label($row['action'])),
                $row['entity_type'],
                $row['entity_id'],
                $row['ip'],
                self::csvSafe((string) $row['changes']),
                $row['hash'],
            ], ';', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);
        return Response::download($csv, 'auditoria-isis-' . date('Y-m-d-His') . '.csv', 'text/csv; charset=UTF-8');
    }

    public function verify(Request $request): Response
    {
        $result = (new AuditRepository())->verifyChain();
        AuditLogger::log('audit.verified', 'audit', null, null, ['resultado' => $result['ok'] ? 'íntegra' : 'violada', 'registros' => $result['checked']]);
        return $result['ok']
            ? $this->success('/auditoria', "Integridade confirmada: {$result['checked']} registros verificados, nenhuma alteração detectada.")
            : $this->failure('/auditoria', 'ATENÇÃO: a cadeia de auditoria foi violada' . ($result['broken_id'] ? " a partir do registro #{$result['broken_id']}" : '') . '. Acione a equipe técnica.');
    }

    /** @return array{action: ?string, user_id: ?int, from: ?string, to: ?string} */
    private function filters(Request $request): array
    {
        $date = static fn (?string $v): ?string => ($v !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) ? $v : null;
        $action = $request->query('acao');
        $user = $request->query('usuario');
        return [
            'action' => ($action !== null && preg_match('/^[a-z_.]{3,60}$/', $action)) ? $action : null,
            'user_id' => ($user !== null && ctype_digit($user)) ? (int) $user : null,
            'from' => $date($request->query('de')),
            'to' => $date($request->query('ate')),
        ];
    }

    /** Evita injeção de fórmulas ao abrir o CSV em planilhas. */
    private static function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
