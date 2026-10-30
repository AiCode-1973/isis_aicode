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
use Isis\Repositories\ActivityRepository;
use Isis\Repositories\AttachmentRepository;
use Isis\Repositories\DpiaRepository;
use Isis\Repositories\UserRepository;
use Isis\Services\DpiaService;
use Isis\Services\EvidenceService;
use Isis\Support\Labels;

/**
 * Relatório de Impacto à Proteção de Dados Pessoais (RIPD): elaboração, aprovação com versão
 * congelada, revisões e documento para impressão.
 */
final class DpiaController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => mb_substr(trim((string) $request->query('q')), 0, 100) ?: null,
            'status' => isset(Labels::DPIA_STATUS[(string) $request->query('status')]) ? $request->query('status') : null,
        ];
        $repo = new DpiaRepository();
        return $this->view('dpia/index', [
            'title' => 'Relatórios de impacto (RIPD)',
            'dpias' => $repo->search($filters),
            'filters' => $filters,
            'stats' => $repo->stats(),
            'candidates' => DpiaService::candidates(),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $repo = new DpiaRepository();
        $dpia = $repo->findFullOrFail($id);
        return $this->view('dpia/show', [
            'title' => code('RIPD', (int) $dpia['seq']) . ' · ' . $dpia['title'],
            'd' => $dpia,
            'doc' => DpiaService::document($dpia),
            'missing' => DpiaService::missing($dpia),
            'versions' => $repo->versions($id),
            'evidences' => (new AttachmentRepository())->forEntity('dpia', $id),
        ]);
    }

    public function create(Request $request): Response
    {
        $dpia = [
            'id' => null, 'title' => '', 'activity_ids' => [], 'triggers' => [], 'owner_user_id' => Auth::id(),
            'review_due_date' => date('Y-m-d', strtotime('+1 year')),
        ];
        $activityId = ctype_digit((string) $request->query('operacao')) ? (int) $request->query('operacao') : null;
        $activity = $activityId !== null ? (new ActivityRepository())->findFull($activityId) : null;
        if ($activity !== null) {
            $dpia['title'] = 'RIPD · ' . $activity['name'];
            $dpia['activity_ids'] = [$activityId];
            $dpia['triggers'] = DpiaService::suggestTriggers($activity);
            $dpia['purpose'] = $activity['purpose'];
        }
        return $this->form($dpia, 'Novo RIPD');
    }

    public function store(Request $request): Response
    {
        [$data, $activityIds, $errors] = $this->parse($request);
        if ($errors !== []) {
            return $this->backWithErrors('/ripd/novo', $errors, $request->all());
        }
        $id = (new DpiaRepository())->create($data + ['created_by' => Auth::id()], $activityIds);
        AuditLogger::log('dpia.created', 'dpia', $id, null, $data + ['operacoes' => $activityIds]);
        return $this->success("/ripd/{$id}", 'RIPD criado. Complete as seções e envie para aprovação.');
    }

    public function edit(Request $request, int $id): Response
    {
        $dpia = (new DpiaRepository())->findFullOrFail($id);
        $this->guardDraft($dpia);
        return $this->form($dpia, 'Editar ' . code('RIPD', (int) $dpia['seq']));
    }

    public function update(Request $request, int $id): Response
    {
        $repo = new DpiaRepository();
        $before = $repo->findFullOrFail($id);
        $this->guardDraft($before);
        [$data, $activityIds, $errors] = $this->parse($request, $before['activity_ids']);
        if ($errors !== []) {
            return $this->backWithErrors("/ripd/{$id}/editar", $errors, $request->all());
        }
        $repo->save($id, $data, $activityIds);
        AuditLogger::log('dpia.updated', 'dpia', $id, $before, $data + ['operacoes' => $activityIds]);
        return $this->success("/ripd/{$id}", 'RIPD salvo.');
    }

    public function submit(Request $request, int $id): Response
    {
        $repo = new DpiaRepository();
        $dpia = $repo->findFullOrFail($id);
        $this->guardDraft($dpia);
        $missing = DpiaService::missing($dpia);
        if ($missing !== []) {
            return $this->failure("/ripd/{$id}", 'Antes de enviar: ' . implode(' ', $missing));
        }
        $repo->save($id, ['status' => 'em_aprovacao', 'submitted_at' => date('Y-m-d H:i:s'), 'returned_notes' => null]);
        AuditLogger::log('dpia.submitted', 'dpia', $id, null, ['versao' => (int) $dpia['version']]);
        return $this->success("/ripd/{$id}", 'RIPD enviado para aprovação.');
    }

    public function approve(Request $request, int $id): Response
    {
        $repo = new DpiaRepository();
        $dpia = $repo->findFullOrFail($id);
        if ($dpia['status'] !== 'em_aprovacao') {
            return $this->failure("/ripd/{$id}", 'Este RIPD não está aguardando aprovação.');
        }
        $notes = mb_substr(trim((string) $request->input('notes')), 0, 500) ?: null;
        $snapshot = (string) json_encode(DpiaService::document($dpia), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $user = Auth::user();
        $repo->approve($dpia, $snapshot, (int) $user['id'], (string) $user['name'], $notes);
        AuditLogger::log('dpia.approved', 'dpia', $id, null, ['versao' => (int) $dpia['version'], 'sha256' => hash('sha256', $snapshot), 'observacao' => $notes]);
        return $this->success("/ripd/{$id}", 'Versão ' . (int) $dpia['version'] . ' aprovada. O conteúdo aprovado ficou guardado e pode ser impresso a qualquer momento.');
    }

    public function reject(Request $request, int $id): Response
    {
        $repo = new DpiaRepository();
        $dpia = $repo->findFullOrFail($id);
        $notes = trim((string) $request->input('notes'));
        if ($dpia['status'] !== 'em_aprovacao' || $notes === '') {
            return $this->failure("/ripd/{$id}", 'Informe o motivo para devolver o RIPD.');
        }
        $repo->save($id, ['status' => 'rascunho', 'returned_notes' => mb_substr($notes, 0, 500), 'submitted_at' => null]);
        AuditLogger::log('dpia.returned', 'dpia', $id, null, ['versao' => (int) $dpia['version'], 'motivo' => $notes]);
        return $this->success("/ripd/{$id}", 'RIPD devolvido para ajustes.');
    }

    /** Abre uma nova versão em elaboração a partir de um RIPD aprovado (a versão aprovada continua guardada). */
    public function revise(Request $request, int $id): Response
    {
        $repo = new DpiaRepository();
        $dpia = $repo->findFullOrFail($id);
        if ($dpia['status'] !== 'aprovado') {
            return $this->failure("/ripd/{$id}", 'Só é possível revisar um RIPD aprovado.');
        }
        $next = (int) $dpia['version'] + 1;
        $repo->save($id, [
            'status' => 'rascunho', 'version' => $next, 'approved_by' => null, 'approved_at' => null,
            'approval_notes' => null, 'submitted_at' => null,
        ]);
        AuditLogger::log('dpia.revision', 'dpia', $id, null, ['versao' => $next]);
        return $this->success("/ripd/{$id}/editar", "Versão {$next} em elaboração. A versão aprovada anterior continua valendo até a nova ser aprovada.");
    }

    public function delete(Request $request, int $id): Response
    {
        $repo = new DpiaRepository();
        $dpia = $repo->findFullOrFail($id);
        if ($dpia['approved_version'] !== null) {
            return $this->failure("/ripd/{$id}", 'Um RIPD que já foi aprovado não pode ser excluído: as versões aprovadas fazem parte do histórico.');
        }
        $this->guardDraft($dpia);
        EvidenceService::purgeEntity('dpia', $id);
        $repo->remove($id);
        AuditLogger::log('dpia.deleted', 'dpia', $id, $dpia, null);
        return $this->success('/ripd', 'RIPD excluído.');
    }

    /**
     * Documento para impressão. Aprovado: abre a versão guardada na aprovação. Em elaboração ou em
     * aprovação: mostra o conteúdo atual, marcado como não aprovado.
     */
    public function document(Request $request, int $id): Response
    {
        $dpia = (new DpiaRepository())->findFullOrFail($id);
        if ($dpia['status'] === 'aprovado') {
            return $this->redirect("/ripd/{$id}/versoes/" . (int) $dpia['version']);
        }
        return $this->view('dpia/document', [
            'title' => code('RIPD', (int) $dpia['seq']) . ' · documento',
            'id' => $id,
            'doc' => DpiaService::document($dpia),
            'approval' => null,
        ]);
    }

    /** Documento exatamente como foi aprovado numa versão. */
    public function version(Request $request, int $id, int $version): Response
    {
        $repo = new DpiaRepository();
        $dpia = $repo->findFullOrFail($id);
        $row = $repo->version($id, $version) ?? throw new HttpException(404);
        $doc = json_decode((string) $row['snapshot'], true);
        if (!is_array($doc)) {
            throw new HttpException(500, 'O conteúdo desta versão não pôde ser lido.');
        }
        return $this->view('dpia/document', [
            'title' => code('RIPD', (int) $dpia['seq']) . ' · versão ' . $version,
            'id' => $id,
            'doc' => $doc,
            'approval' => $row + ['intact' => hash_equals((string) $row['sha256'], hash('sha256', (string) $row['snapshot']))],
        ]);
    }

    private function form(array $dpia, string $title): Response
    {
        $repo = new ActivityRepository();
        $activities = $repo->options();
        foreach (array_diff($dpia['activity_ids'], array_keys($activities)) as $linkedId) {
            $row = $repo->find($linkedId);
            if ($row !== null) {
                $activities[$linkedId] = code('OP', (int) $row['seq']) . ' · ' . $row['name'] . ' (descontinuada)';
            }
        }
        return $this->view('dpia/form', [
            'title' => $title,
            'd' => $dpia,
            'activities' => $activities,
            'users' => (new UserRepository())->options(),
        ]);
    }

    private function guardDraft(array $dpia): void
    {
        if ($dpia['status'] !== 'rascunho') {
            throw new HttpException(409, $dpia['status'] === 'aprovado'
                ? 'Este RIPD está aprovado. Para alterar, inicie uma nova revisão.'
                : 'Este RIPD está aguardando aprovação e não pode ser alterado agora.');
        }
    }

    /**
     * @param list<int> $linked operações já vinculadas (continuam válidas mesmo se descontinuadas)
     * @return array{0: array<string, mixed>, 1: list<int>, 2: array<string, string>}
     */
    private function parse(Request $request, array $linked = []): array
    {
        $text = 'nullable|max:10000';
        $v = Validator::make($request->all(), [
            'title' => 'required|max:190',
            'need_justification' => $text,
            'nature' => $text,
            'scope' => $text,
            'context' => $text,
            'purpose' => $text,
            'stakeholders' => $text,
            'necessity' => $text,
            'safeguards' => $text,
            'conclusion' => 'nullable|' . Labels::rule(Labels::DPIA_CONCLUSION),
            'conclusion_notes' => $text,
            'dpo_opinion' => $text,
            'owner_user_id' => 'nullable|int',
            'review_due_date' => 'nullable|date',
        ], ['title' => 'o título', 'conclusion' => 'a conclusão', 'review_due_date' => 'a data de revisão']);
        $data = $v->validated();

        $activities = (new ActivityRepository())->options();
        $activityIds = array_values(array_unique(array_map('intval', $request->inputArray('activity_ids'))));
        if (array_diff($activityIds, array_keys($activities), $linked) !== []) {
            $v->addError('activity_ids', 'Operação inválida.');
        }
        $triggers = array_values(array_intersect($request->inputArray('triggers'), array_keys(Labels::DPIA_TRIGGER)));
        $data['triggers'] = json_encode($triggers);

        $users = array_column((new UserRepository())->options(), 'name', 'id');
        if ($data['owner_user_id'] !== null && !isset($users[(int) $data['owner_user_id']])) {
            $v->addError('owner_user_id', 'Opção inválida.');
        }
        $data['owner_user_id'] = $data['owner_user_id'] !== null ? (int) $data['owner_user_id'] : null;
        return [$data, $activityIds, $v->errors()];
    }
}
