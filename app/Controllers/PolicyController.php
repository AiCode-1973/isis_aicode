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
use Isis\Repositories\AttachmentRepository;
use Isis\Repositories\PolicyRepository;
use Isis\Repositories\TenantRepository;
use Isis\Repositories\UserRepository;
use Isis\Services\Templates;
use Isis\Support\Labels;

/**
 * Políticas e documentos: rascunho, aprovação, vigência, versões e ciência dos colaboradores.
 */
final class PolicyController extends Controller
{
    public function index(Request $request): Response
    {
        $editor = Auth::can(P::POLICIES_EDIT) || Auth::can(P::POLICIES_APPROVE);
        $repo = new PolicyRepository();
        return $this->view('policies/index', [
            'title' => 'Políticas e documentos',
            'policies' => $repo->search((int) Auth::id(), !$editor),
            'stats' => $editor ? $repo->stats() : null,
            'editor' => $editor,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->view('policies/form', [
            'title' => 'Nova política',
            'p' => ['id' => null, 'category' => 'privacidade_interna', 'review_months' => 12, 'requires_ack' => 1, 'owner_user_id' => Auth::id()],
            'content' => '',
            'summary' => 'Primeira versão.',
            'users' => (new UserRepository())->options(),
        ]);
    }

    public function store(Request $request): Response
    {
        [$data, $content, $summary, $errors] = $this->parse($request);
        if ($errors !== []) {
            return $this->backWithErrors('/politicas/nova', $errors, $request->all());
        }
        $id = (new PolicyRepository())->create($data, $content, $summary, Auth::id());
        AuditLogger::log('policy.created', 'policy', $id, null, $data);
        return $this->success("/politicas/{$id}", 'Política criada como rascunho. Envie para aprovação quando estiver pronta.');
    }

    public function show(Request $request, int $id): Response
    {
        $repo = new PolicyRepository();
        $policy = $repo->findFullOrFail($id);
        $editor = Auth::can(P::POLICIES_EDIT) || Auth::can(P::POLICIES_APPROVE);
        if (!$editor && $policy['current_version_id'] === null) {
            throw new HttpException(404);
        }
        $current = $policy['current_version_id'] ? $repo->version((int) $policy['current_version_id']) : null;
        return $this->view('policies/show', [
            'title' => code('POL', (int) $policy['seq']) . ' · ' . $policy['title'],
            'p' => $policy,
            'current' => $current,
            'pending' => $editor ? $repo->pendingApproval($id) : null,
            'draft' => $editor ? $repo->editableVersion($id) : null,
            'versions' => $editor ? $repo->versions($id) : [],
            'acks' => $editor && $current ? $repo->acknowledgements((int) $current['id']) : [],
            'myAck' => $current ? $repo->hasAcknowledged((int) $current['id'], (int) Auth::id()) : null,
            'evidences' => (new AttachmentRepository())->forEntity('policy', $id),
            'editor' => $editor,
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $repo = new PolicyRepository();
        $policy = $repo->findFullOrFail($id);
        if ($repo->pendingApproval($id) !== null) {
            return $this->failure("/politicas/{$id}", 'Há uma versão aguardando aprovação. Aprove ou devolva antes de editar.');
        }
        $draft = $repo->editableVersion($id);
        $base = $draft ?? ($policy['current_version_id'] ? $repo->version((int) $policy['current_version_id']) : null);
        return $this->view('policies/form', [
            'title' => 'Editar ' . $policy['title'],
            'p' => $policy,
            'content' => (string) ($base['content'] ?? ''),
            'summary' => $draft['change_summary'] ?? '',
            'newVersion' => $draft === null,
            'users' => (new UserRepository())->options(),
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $repo = new PolicyRepository();
        $before = $repo->findFullOrFail($id);
        if ($repo->pendingApproval($id) !== null) {
            return $this->failure("/politicas/{$id}", 'Há uma versão aguardando aprovação.');
        }
        [$data, $content, $summary, $errors] = $this->parse($request);
        if ($errors !== []) {
            return $this->backWithErrors("/politicas/{$id}/editar", $errors, $request->all());
        }
        $repo->save($id, $data);
        $versionId = $repo->saveDraft($id, $content, $summary, Auth::id());
        AuditLogger::log('policy.updated', 'policy', $id, $before, $data + ['versao_rascunho' => $versionId]);
        return $this->success("/politicas/{$id}", 'Rascunho salvo.');
    }

    public function submit(Request $request, int $id): Response
    {
        $repo = new PolicyRepository();
        $repo->findOrFail($id);
        $draft = $repo->editableVersion($id);
        if ($draft === null) {
            return $this->failure("/politicas/{$id}", 'Não há rascunho para enviar.');
        }
        $repo->setVersionStatus((int) $draft['id'], 'em_aprovacao', ['submitted_at' => date('Y-m-d H:i:s')]);
        AuditLogger::log('policy.submitted', 'policy', $id, null, ['versao' => (int) $draft['version_number']]);
        return $this->success("/politicas/{$id}", 'Versão enviada para aprovação.');
    }

    public function approve(Request $request, int $id): Response
    {
        $repo = new PolicyRepository();
        $policy = $repo->findOrFail($id);
        $pending = $repo->pendingApproval($id);
        if ($pending === null) {
            return $this->failure("/politicas/{$id}", 'Não há versão aguardando aprovação.');
        }
        $notes = mb_substr(trim((string) $request->input('notes')), 0, 500) ?: null;
        $validUntil = $repo->approve($policy, $pending, (int) Auth::id(), $notes);
        AuditLogger::log('policy.approved', 'policy', $id, null, ['versao' => (int) $pending['version_number'], 'valida_ate' => $validUntil, 'autor' => $pending['created_by']]);
        return $this->success("/politicas/{$id}", 'Versão ' . (int) $pending['version_number'] . ' aprovada e publicada. Válida até ' . fmt_date($validUntil) . '.');
    }

    public function reject(Request $request, int $id): Response
    {
        $repo = new PolicyRepository();
        $repo->findOrFail($id);
        $pending = $repo->pendingApproval($id);
        $notes = trim((string) $request->input('notes'));
        if ($pending === null || $notes === '') {
            return $this->failure("/politicas/{$id}", 'Informe o motivo para devolver a versão.');
        }
        $repo->setVersionStatus((int) $pending['id'], 'rejeitada', [
            'decided_by' => Auth::id(), 'decided_at' => date('Y-m-d H:i:s'), 'decision_notes' => mb_substr($notes, 0, 500),
        ]);
        AuditLogger::log('policy.rejected', 'policy', $id, null, ['versao' => (int) $pending['version_number'], 'motivo' => $notes]);
        return $this->success("/politicas/{$id}", 'Versão devolvida para ajustes.');
    }

    public function acknowledge(Request $request, int $id): Response
    {
        $repo = new PolicyRepository();
        $policy = $repo->findOrFail($id);
        if ($policy['current_version_id'] === null) {
            throw new HttpException(404);
        }
        if ($repo->acknowledge((int) $policy['current_version_id'], (int) Auth::id(), $request->ip())) {
            AuditLogger::log('policy.acknowledged', 'policy', $id, null, ['versao_id' => (int) $policy['current_version_id']]);
        }
        return $this->success("/politicas/{$id}", 'Ciência registrada. Obrigado!');
    }

    public function archive(Request $request, int $id): Response
    {
        $repo = new PolicyRepository();
        $policy = $repo->findOrFail($id);
        $repo->save($id, ['archived_at' => date('Y-m-d H:i:s')]);
        AuditLogger::log('policy.archived', 'policy', $id, null, ['titulo' => $policy['title']]);
        return $this->success('/politicas', 'Política arquivada. O histórico e as ciências continuam guardados.');
    }

    /** Cria rascunhos a partir dos modelos (sem duplicar títulos existentes). */
    public function importTemplates(Request $request): Response
    {
        $tenant = (new TenantRepository())->current();
        $repo = new PolicyRepository();
        $created = 0;
        foreach (Templates::policies($tenant['trade_name'] ?: $tenant['legal_name']) as $template) {
            if ($repo->titleExists($template['title'])) {
                continue;
            }
            $id = $repo->create([
                'title' => $template['title'], 'category' => $template['category'], 'review_months' => $template['review_months'],
                'requires_ack' => 1, 'owner_user_id' => Auth::id(),
            ], $template['content'], 'Criada a partir do modelo do ÍSIS.', Auth::id());
            AuditLogger::log('policy.created', 'policy', $id, null, ['titulo' => $template['title'], 'origem' => 'modelo']);
            $created++;
        }
        return $this->success('/politicas', $created > 0
            ? "{$created} modelo(s) criado(s) como rascunho. Adapte o texto antes de enviar para aprovação."
            : 'Os modelos já existem na lista.');
    }

    /** @return array{0: array<string, mixed>, 1: string, 2: ?string, 3: array<string, string>} */
    private function parse(Request $request): array
    {
        $v = Validator::make($request->all(), [
            'title' => 'required|max:190',
            'category' => 'required|' . Labels::rule(Labels::POLICY_CATEGORY),
            'owner_user_id' => 'nullable|int',
            'review_months' => 'required|in:3,6,12,24,36',
            'content' => 'required|min:20|max:200000',
            'change_summary' => 'nullable|max:400',
        ], ['title' => 'o título', 'category' => 'o tipo', 'review_months' => 'o prazo de revisão', 'content' => 'o texto da política']);
        $data = $v->validated();
        if ($data['owner_user_id'] !== null && !isset(array_column((new UserRepository())->options(), 'name', 'id')[(int) $data['owner_user_id']])) {
            $v->addError('owner_user_id', 'Responsável inválido.');
        }
        $content = str_replace("\r\n", "\n", (string) ($request->input('content') ?? ''));
        return [[
            'title' => $data['title'],
            'category' => $data['category'],
            'owner_user_id' => $data['owner_user_id'] !== null ? (int) $data['owner_user_id'] : null,
            'review_months' => (int) $data['review_months'],
            'requires_ack' => $request->input('requires_ack') === '1' ? 1 : 0,
        ], $content, $data['change_summary'], $v->errors()];
    }
}
