<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Auth\Permission as P;
use Isis\Core\HttpException;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Repositories\ActionRepository;
use Isis\Repositories\ActivityRepository;
use Isis\Repositories\AttachmentRepository;
use Isis\Repositories\RiskRepository;
use Isis\Services\AccessScope;
use Isis\Services\EvidenceService;

/**
 * Evidências: envio, download e exclusão, sempre conferindo empresa e permissão sobre o registro.
 */
final class EvidenceController extends Controller
{
    private const array PATHS = [
        'action' => '/planos/', 'risk' => '/riscos/', 'activity' => '/inventario/', 'request' => '/titulares/', 'incident' => '/incidentes/',
    ];

    public function upload(Request $request): Response
    {
        $type = (string) $request->input('entity_type');
        $id = ctype_digit((string) $request->input('entity_id')) ? (int) $request->input('entity_id') : 0;
        $this->authorizeEntity($type, $id, true);
        $back = self::PATHS[$type] . $id . '#evidencias';
        try {
            EvidenceService::store($request->file('file'), $type, $id, trim((string) $request->input('description')) ?: null);
        } catch (\DomainException $e) {
            return $this->failure($back, $e->getMessage());
        }
        return $this->success($back, 'Evidência anexada.');
    }

    public function download(Request $request, int $id): Response
    {
        $attachment = (new AttachmentRepository())->findOrFail($id);
        $this->authorizeEntity($attachment['entity_type'], (int) $attachment['entity_id'], false);
        $path = EvidenceService::path($attachment);
        if (!is_file($path)) {
            throw new HttpException(404, 'O arquivo desta evidência não foi encontrado no servidor.');
        }
        AuditLogger::log('evidence.downloaded', $attachment['entity_type'], (int) $attachment['entity_id'], null, ['arquivo' => $attachment['original_name']]);
        return Response::download((string) file_get_contents($path), $attachment['original_name'], $attachment['mime']);
    }

    public function delete(Request $request, int $id): Response
    {
        $attachment = (new AttachmentRepository())->findOrFail($id);
        $this->authorizeEntity($attachment['entity_type'], (int) $attachment['entity_id'], true);
        EvidenceService::delete($attachment);
        return $this->success(self::PATHS[$attachment['entity_type']] . $attachment['entity_id'] . '#evidencias', 'Evidência excluída.');
    }

    /** Confere se o registro existe na empresa e se o usuário pode vê-lo (ou alterá-lo). */
    private function authorizeEntity(string $type, int $id, bool $write): void
    {
        $allowed = match ($type) {
            'action' => (function () use ($id, $write): bool {
                $action = (new ActionRepository())->findOrFail($id);
                return $write ? AccessScope::canProgressAction($action) : (Auth::can(P::ACTIONS_VIEW) && AccessScope::canViewAction($action));
            })(),
            'risk' => (function () use ($id, $write): bool {
                $risk = (new RiskRepository())->findOrFail($id);
                return $write ? AccessScope::canEditRisk($risk) : Auth::can(P::RISKS_VIEW);
            })(),
            'activity' => (function () use ($id, $write): bool {
                $activity = (new ActivityRepository())->findOrFail($id);
                return $write ? AccessScope::canEditActivity($activity) : Auth::can(P::INVENTORY_VIEW);
            })(),
            'request' => (function () use ($id, $write): bool {
                (new \Isis\Repositories\RequestRepository())->findOrFail($id);
                return Auth::can($write ? P::REQUESTS_MANAGE : P::REQUESTS_VIEW);
            })(),
            'incident' => (function () use ($id, $write): bool {
                $incident = (new \Isis\Repositories\IncidentRepository())->findOrFail($id);
                $isReporter = (int) $incident['reported_by'] === Auth::id();
                return $write
                    ? Auth::can(P::INCIDENTS_MANAGE) || $isReporter
                    : Auth::can(P::INCIDENTS_VIEW) || $isReporter;
            })(),
            default => throw new HttpException(404),
        };
        if (!$allowed) {
            AuditLogger::log('access.denied', $type, $id, null, ['recurso' => 'evidência']);
            throw new HttpException(403);
        }
    }
}
