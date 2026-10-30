<?php

declare(strict_types=1);

namespace Isis\Services;

use Isis\Auth\Auth;
use Isis\Auth\Permission as P;
use Isis\Auth\Role;

/**
 * Regras de escopo por registro (além da permissão por perfil):
 * - Gestor só altera o que é do seu setor, o que criou ou o que está sob sua responsabilidade.
 * - Colaborador só vê e atualiza as ações atribuídas a ele.
 */
final class AccessScope
{
    public static function canEditActivity(array $activity): bool
    {
        return Auth::can(P::INVENTORY_EDIT) && self::withinManagerScope($activity);
    }

    public static function canEditRisk(array $risk): bool
    {
        return Auth::can(P::RISKS_EDIT) && self::withinManagerScope($risk);
    }

    public static function canEditAction(array $action): bool
    {
        return Auth::can(P::ACTIONS_EDIT) && self::withinManagerScope($action);
    }

    /** Registrar andamento (status, nota, evidência): quem edita a ação ou o próprio responsável. */
    public static function canProgressAction(array $action): bool
    {
        if (Auth::role() === Role::Auditor) {
            return false;
        }
        return self::canEditAction($action) || (int) ($action['owner_user_id'] ?? 0) === Auth::id();
    }

    public static function canViewAction(array $action): bool
    {
        return !self::onlyOwnActions() || (int) ($action['owner_user_id'] ?? 0) === Auth::id();
    }

    /** Colaborador enxerga apenas as próprias tarefas. */
    public static function onlyOwnActions(): bool
    {
        return Auth::role() === Role::Collaborator;
    }

    private static function withinManagerScope(array $record): bool
    {
        if (Auth::role() !== Role::Manager) {
            return true;
        }
        $user = Auth::user() ?? [];
        $me = (int) ($user['id'] ?? 0);
        $myDepartment = (int) ($user['department_id'] ?? 0);
        return ($myDepartment > 0 && (int) ($record['department_id'] ?? 0) === $myDepartment)
            || (int) ($record['created_by'] ?? 0) === $me
            || (int) ($record['owner_user_id'] ?? 0) === $me;
    }
}
