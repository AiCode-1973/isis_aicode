<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Auth\Permission as P;
use Isis\Core\HttpException;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Validator;
use Isis\Repositories\ActionRepository;
use Isis\Repositories\ActivityRepository;
use Isis\Repositories\AttachmentRepository;
use Isis\Repositories\CatalogRepository;
use Isis\Repositories\DepartmentRepository;
use Isis\Repositories\LegalBasisRepository;
use Isis\Repositories\RiskRepository;
use Isis\Repositories\TenantRepository;
use Isis\Repositories\UserRepository;
use Isis\Services\AccessScope;
use Isis\Services\CatalogService;
use Isis\Services\EvidenceService;
use Isis\Support\Labels;

/**
 * Inventário de operações de tratamento (dados, titulares, finalidades, bases legais, origem,
 * compartilhamentos, retenção, sistemas e setores) e seus catálogos.
 */
final class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q')), 0, 100) ?: null,
            'status' => isset(Labels::ACTIVITY_STATUS[(string) $request->query('status')]) ? $request->query('status') : null,
            'department_id' => ctype_digit((string) $request->query('setor')) ? (int) $request->query('setor') : null,
            'sensitive' => $request->query('sensiveis') === '1',
        ];
        $repo = new ActivityRepository();
        return $this->view('inventory/index', [
            'title' => 'Inventário de dados pessoais',
            'activities' => $repo->search($filters),
            'stats' => $repo->stats(),
            'filters' => $filters,
            'departments' => (new DepartmentRepository())->options(),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $activity = (new ActivityRepository())->findFullOrFail($id);
        return $this->view('inventory/show', [
            'title' => code('OP', (int) $activity['seq']) . ' · ' . $activity['name'],
            'a' => $activity,
            'issues' => ActivityRepository::issues($activity),
            'risks' => Auth::can(P::RISKS_VIEW) ? (new RiskRepository())->search(['activity_id' => $id]) : [],
            'actions' => Auth::can(P::ACTIONS_VIEW) && !AccessScope::onlyOwnActions()
                ? array_filter((new ActionRepository())->search([]), static fn (array $x): bool => (int) $x['activity_id'] === $id) : [],
            'evidences' => (new AttachmentRepository())->forEntity('activity', $id),
            'canEdit' => AccessScope::canEditActivity($activity),
        ]);
    }

    public function create(Request $request): Response
    {
        $me = Auth::user();
        return $this->form([
            'id' => null, 'seq' => null, 'status' => 'rascunho', 'controller_role' => 'controlador',
            'department_id' => $me['department_id'], 'owner_user_id' => $me['id'], 'international_transfer' => 0,
            'data_categories' => [], 'subject_categories' => [], 'systems' => [], 'departments' => [], 'sharings' => [],
        ], 'Nova operação de tratamento');
    }

    public function store(Request $request): Response
    {
        [$data, $relations, $errors] = $this->parse($request);
        if ($errors !== []) {
            return $this->backWithErrors('/inventario/nova', $errors, $request->all());
        }
        $id = (new ActivityRepository())->create($data + ['created_by' => Auth::id()], $relations);
        AuditLogger::log('activity.created', 'activity', $id, null, $data + self::relationsForAudit($relations));
        return $this->success("/inventario/{$id}", 'Operação de tratamento registrada.');
    }

    public function edit(Request $request, int $id): Response
    {
        $activity = (new ActivityRepository())->findFullOrFail($id);
        $this->guard($activity);
        return $this->form($activity, 'Editar ' . code('OP', (int) $activity['seq']));
    }

    public function update(Request $request, int $id): Response
    {
        $repo = new ActivityRepository();
        $before = $repo->findFullOrFail($id);
        $this->guard($before);
        [$data, $relations, $errors] = $this->parse($request);
        if ($errors !== []) {
            return $this->backWithErrors("/inventario/{$id}/editar", $errors, $request->all());
        }
        $repo->save($id, $data, $relations);
        AuditLogger::log('activity.updated', 'activity', $id, $before + self::relationsForAudit([
            'data' => array_column($before['data_categories'], 'id'),
            'subjects' => array_column($before['subject_categories'], 'id'),
            'systems' => array_column($before['systems'], 'id'),
            'departments' => array_column($before['departments'], 'id'),
            'sharings' => $before['sharings'],
        ]), $data + self::relationsForAudit($relations));
        return $this->success("/inventario/{$id}", 'Operação de tratamento atualizada.');
    }

    public function delete(Request $request, int $id): Response
    {
        $repo = new ActivityRepository();
        $activity = $repo->findFullOrFail($id);
        $this->guard($activity);
        EvidenceService::purgeEntity('activity', $id);
        $repo->remove($id);
        AuditLogger::log('activity.deleted', 'activity', $id, ['codigo' => code('OP', (int) $activity['seq']), 'nome' => $activity['name']], null);
        return $this->success('/inventario', 'Operação excluída. Riscos e ações vinculados foram mantidos, sem o vínculo.');
    }

    /** Registro das operações de tratamento (art. 37) em CSV. */
    public function export(Request $request): Response
    {
        $rows = (new ActivityRepository())->exportRows();
        $tenant = (new TenantRepository())->current();
        AuditLogger::log('inventory.exported', 'activity', null, null, ['registros' => count($rows)]);

        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        $put = static fn (array $line) => fputcsv($out, array_map(static fn ($v): string => self::csvSafe((string) $v), $line), ';', '"', '');
        $put(['Registro das operações de tratamento', $tenant['legal_name'], 'CNPJ ' . fmt_cnpj($tenant['cnpj']), 'Encarregado: ' . ($tenant['dpo_name'] ?? '—'), 'Gerado em ' . date('d/m/Y H:i')]);
        $put(['Código', 'Operação', 'Situação', 'Papel', 'Finalidade', 'Hipótese legal', 'Hipótese legal (sensíveis)', 'Categorias de dados', 'Dados sensíveis',
            'Titulares', 'Volume', 'Origem', 'Setor responsável', 'Setores envolvidos', 'Responsável', 'Sistemas', 'Compartilhamentos',
            'Transferência internacional', 'Retenção', 'Descarte', 'Medidas de segurança', 'Próxima revisão']);
        foreach ($rows as $a) {
            $put([
                code('OP', (int) $a['seq']), $a['name'], label(Labels::ACTIVITY_STATUS, $a['status']), label(Labels::CONTROLLER_ROLE, $a['controller_role']),
                $a['purpose'], trim(($a['legal_basis_article'] ?? '') . ' ' . ($a['legal_basis_name'] ?? '')),
                trim(($a['sensitive_basis_article'] ?? '') . ' ' . ($a['sensitive_basis_name'] ?? '')),
                implode(', ', array_column($a['data_categories'], 'name')), $a['has_sensitive'] ? 'Sim' : 'Não',
                implode(', ', array_column($a['subject_categories'], 'name')), label(Labels::SUBJECTS_VOLUME, $a['subjects_volume']),
                trim(label(Labels::DATA_SOURCE, $a['data_source']) . ' ' . ($a['source_details'] ?? '')), $a['department_name'],
                implode(', ', array_column($a['departments'], 'name')), $a['owner_name'], implode(', ', array_column($a['systems'], 'name')),
                implode(' | ', array_map(static fn (array $s): string => $s['recipient'] . ' (' . label(Labels::RECIPIENT_TYPE, $s['recipient_type']) . ')' . ($s['purpose'] ? ': ' . $s['purpose'] : ''), $a['sharings'])),
                $a['international_transfer'] ? 'Sim: ' . $a['international_details'] : 'Não', $a['retention_period'], $a['disposal_method'],
                $a['security_measures'], fmt_date($a['review_due_date']),
            ]);
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);
        return Response::download($csv, 'registro-operacoes-tratamento-' . date('Y-m-d') . '.csv', 'text/csv; charset=UTF-8');
    }

    // ---------------- Catálogos ----------------

    public function catalogs(Request $request): Response
    {
        return $this->view('inventory/catalogs', [
            'title' => 'Catálogos do inventário',
            'catalogs' => array_map(static fn (string $type): CatalogRepository => new CatalogRepository($type), array_keys(CatalogRepository::TYPES)),
            'canEdit' => Auth::can(P::INVENTORY_EDIT),
        ]);
    }

    public function catalogStore(Request $request, string $type): Response
    {
        $repo = new CatalogRepository($type);
        $name = trim((string) $request->input('name'));
        if ($name === '' || mb_strlen($name) > 150 || $repo->nameExists($name)) {
            return $this->failure("/inventario/catalogos#{$type}", 'Informe um nome válido e que ainda não exista no catálogo.');
        }
        $extra = $this->catalogExtra($repo, $request);
        $id = $repo->create($name, $extra);
        AuditLogger::log('catalog.created', $repo->type, $id, null, ['nome' => $name, $repo->extraColumn() => $extra]);
        return $this->success("/inventario/catalogos#{$type}", "\"{$name}\" adicionado.");
    }

    public function catalogUpdate(Request $request, string $type, int $id): Response
    {
        $repo = new CatalogRepository($type);
        $before = $repo->findOrFail($id);
        $name = trim((string) $request->input('name'));
        if ($name === '' || mb_strlen($name) > 150 || $repo->nameExists($name, $id)) {
            return $this->failure("/inventario/catalogos#{$type}", 'Nome inválido ou já existente.');
        }
        $extra = $this->catalogExtra($repo, $request);
        $repo->change($id, $name, $extra);
        AuditLogger::log('catalog.updated', $repo->type, $id, $before, ['name' => $name, $repo->extraColumn() => $extra]);
        return $this->success("/inventario/catalogos#{$type}", 'Item atualizado.');
    }

    public function catalogDelete(Request $request, string $type, int $id): Response
    {
        $repo = new CatalogRepository($type);
        $item = $repo->findOrFail($id);
        $repo->remove($id);
        AuditLogger::log('catalog.deleted', $repo->type, $id, $item, null);
        return $this->success("/inventario/catalogos#{$type}", "\"{$item['name']}\" excluído e desvinculado das operações.");
    }

    public function catalogImport(Request $request): Response
    {
        $tenant = (new TenantRepository())->current();
        $created = CatalogService::importSuggestions($tenant['segment_code']);
        AuditLogger::log('catalog.imported', 'tenant', (int) $tenant['id'], null, ['segmento' => $tenant['segment_code']] + $created);
        return $this->success('/inventario/catalogos', sprintf('%d categoria(s) de dados e %d de titulares importadas das sugestões do segmento.', $created['data'], $created['subjects']));
    }

    // ---------------- Apoio ----------------

    private function form(array $activity, string $title): Response
    {
        return $this->view('inventory/form', [
            'title' => $title,
            'a' => $activity,
            'bases' => LegalBasisRepository::all(false),
            'sensitiveBases' => LegalBasisRepository::all(true),
            'dataCategories' => (new CatalogRepository('dados'))->options(),
            'subjectCategories' => (new CatalogRepository('titulares'))->options(),
            'systems' => (new CatalogRepository('sistemas'))->options(),
            'departments' => (new DepartmentRepository())->options(),
            'users' => (new UserRepository())->options(),
        ]);
    }

    private function guard(array $activity): void
    {
        if (!AccessScope::canEditActivity($activity)) {
            AuditLogger::log('access.denied', 'activity', (int) $activity['id'], null, ['motivo' => 'fora do escopo do perfil']);
            throw new HttpException(403, 'Esta operação pertence a outro setor. Peça ao encarregado ou ao responsável para alterá-la.');
        }
    }

    /** @return array{0: array<string, mixed>, 1: array, 2: array<string, string>} */
    private function parse(Request $request): array
    {
        $bases = LegalBasisRepository::all(false);
        $sensitiveBases = LegalBasisRepository::all(true);
        $v = Validator::make($request->all(), [
            'name' => 'required|max:190',
            'description' => 'nullable|max:5000',
            'purpose' => 'required|max:5000',
            'controller_role' => 'required|' . Labels::rule(Labels::CONTROLLER_ROLE),
            'legal_basis_code' => 'nullable|in:' . implode(',', array_keys($bases)),
            'sensitive_legal_basis_code' => 'nullable|in:' . implode(',', array_keys($sensitiveBases)),
            'legal_basis_notes' => 'nullable|max:5000',
            'department_id' => 'nullable|int',
            'owner_user_id' => 'nullable|int',
            'data_source' => 'nullable|' . Labels::rule(Labels::DATA_SOURCE),
            'source_details' => 'nullable|max:400',
            'subjects_volume' => 'nullable|' . Labels::rule(Labels::SUBJECTS_VOLUME),
            'retention_period' => 'nullable|max:255',
            'disposal_method' => 'nullable|max:255',
            'international_details' => 'nullable|max:400',
            'security_measures' => 'nullable|max:5000',
            'status' => 'required|' . Labels::rule(Labels::ACTIVITY_STATUS),
            'review_due_date' => 'nullable|date',
        ], [
            'name' => 'o nome da operação', 'purpose' => 'a finalidade', 'controller_role' => 'o papel da empresa',
            'legal_basis_code' => 'a hipótese legal', 'sensitive_legal_basis_code' => 'a hipótese legal para dados sensíveis',
            'department_id' => 'o setor', 'owner_user_id' => 'o responsável', 'status' => 'a situação', 'review_due_date' => 'a data de revisão',
        ]);
        $data = $v->validated();

        $departments = (new DepartmentRepository())->options();
        $users = array_column((new UserRepository())->options(), 'name', 'id');
        foreach (['department_id' => $departments, 'owner_user_id' => $users] as $field => $allowed) {
            if ($data[$field] !== null && !isset($allowed[(int) $data[$field]])) {
                $v->addError($field, 'Opção inválida.');
            }
            $data[$field] = $data[$field] !== null ? (int) $data[$field] : null;
        }
        $data['international_transfer'] = $request->input('international_transfer') === '1' ? 1 : 0;

        $relations = [
            'data' => (new CatalogRepository('dados'))->ownIds($request->inputArray('data_categories')),
            'subjects' => (new CatalogRepository('titulares'))->ownIds($request->inputArray('subject_categories')),
            'systems' => (new CatalogRepository('sistemas'))->ownIds($request->inputArray('systems')),
            'departments' => array_values(array_intersect(array_map('intval', $request->inputArray('departments')), array_keys($departments))),
            'sharings' => [],
        ];

        $recipients = $request->inputArray('sharing_recipient');
        $types = $request->inputArray('sharing_type');
        $purposes = $request->inputArray('sharing_purpose');
        $intl = $request->inputArray('sharing_international');
        foreach ($recipients as $i => $recipient) {
            $recipient = trim($recipient);
            if ($recipient === '') {
                continue;
            }
            $type = $types[$i] ?? 'operador';
            $relations['sharings'][] = [
                'recipient' => mb_substr($recipient, 0, 190),
                'recipient_type' => isset(Labels::RECIPIENT_TYPE[$type]) ? $type : 'outro',
                'purpose' => mb_substr(trim($purposes[$i] ?? ''), 0, 400) ?: null,
                'international' => ($intl[$i] ?? '0') === '1',
            ];
        }
        if (count($relations['sharings']) > 30) {
            $v->addError('sharing_recipient', 'Máximo de 30 compartilhamentos por operação.');
        }
        return [$data, $relations, $v->errors()];
    }

    private static function relationsForAudit(array $relations): array
    {
        return [
            'categorias_dados' => implode(',', $relations['data']),
            'titulares' => implode(',', $relations['subjects']),
            'sistemas' => implode(',', $relations['systems']),
            'setores_envolvidos' => implode(',', $relations['departments']),
            'compartilhamentos' => implode(' | ', array_column($relations['sharings'], 'recipient')),
        ];
    }

    private function catalogExtra(CatalogRepository $repo, Request $request): int|string
    {
        if ($repo->type === 'sistemas') {
            $hosting = (string) $request->input('hosting');
            return isset(Labels::SYSTEM_HOSTING[$hosting]) ? $hosting : 'nuvem';
        }
        return $request->input('flag') === '1' ? 1 : 0;
    }

    private static function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
