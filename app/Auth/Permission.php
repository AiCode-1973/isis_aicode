<?php

declare(strict_types=1);

namespace Isis\Auth;

/**
 * Permissões do sistema. Cada perfil (Role) recebe só o necessário (menor privilégio).
 */
final class Permission
{
    public const string DASHBOARD_VIEW = 'dashboard.view';

    public const string USERS_VIEW = 'users.view';
    public const string USERS_MANAGE = 'users.manage';
    public const string SETTINGS_MANAGE = 'settings.manage';
    public const string AUDIT_VIEW = 'audit.view';

    public const string INVENTORY_VIEW = 'inventory.view';
    public const string INVENTORY_EDIT = 'inventory.edit';
    public const string RISKS_VIEW = 'risks.view';
    public const string RISKS_EDIT = 'risks.edit';
    public const string ACTIONS_VIEW = 'actions.view';
    public const string ACTIONS_EDIT = 'actions.edit';
    public const string REQUESTS_VIEW = 'requests.view';
    public const string REQUESTS_MANAGE = 'requests.manage';
    public const string INCIDENTS_VIEW = 'incidents.view';
    public const string INCIDENTS_REPORT = 'incidents.report';
    public const string INCIDENTS_MANAGE = 'incidents.manage';
    public const string VENDORS_VIEW = 'vendors.view';
    public const string VENDORS_EDIT = 'vendors.edit';
    public const string POLICIES_VIEW = 'policies.view';
    public const string POLICIES_EDIT = 'policies.edit';
    public const string POLICIES_APPROVE = 'policies.approve';
    public const string TRAININGS_VIEW = 'trainings.view';
    public const string TRAININGS_MANAGE = 'trainings.manage';
    public const string REPORTS_VIEW = 'reports.view';
    public const string DPIA_VIEW = 'dpia.view';
    public const string DPIA_EDIT = 'dpia.edit';
    public const string DPIA_APPROVE = 'dpia.approve';

    /** @return list<string> */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }

    /** Permissões de leitura (terminam em .view). @return list<string> */
    public static function readOnly(): array
    {
        return array_values(array_filter(self::all(), static fn (string $p): bool => str_ends_with($p, '.view')));
    }
}
