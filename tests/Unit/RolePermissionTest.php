<?php

declare(strict_types=1);

namespace Isis\Tests\Unit;

use Isis\Auth\Permission;
use Isis\Auth\Role;
use PHPUnit\Framework\TestCase;

final class RolePermissionTest extends TestCase
{
    public function testAdministradorTemTodasAsPermissoes(): void
    {
        self::assertEqualsCanonicalizing(Permission::all(), Role::Admin->permissions());
    }

    public function testAuditorSoLe(): void
    {
        foreach (Role::Auditor->permissions() as $permission) {
            self::assertTrue(
                str_ends_with($permission, '.view'),
                "Auditor não deveria ter a permissão de escrita {$permission}",
            );
        }
        self::assertTrue(Role::Auditor->can(Permission::AUDIT_VIEW));
        self::assertFalse(Role::Auditor->can(Permission::USERS_MANAGE));
    }

    public function testEncarregadoNaoAdministraUsuariosNemConfiguracoes(): void
    {
        self::assertFalse(Role::Dpo->can(Permission::USERS_MANAGE));
        self::assertFalse(Role::Dpo->can(Permission::SETTINGS_MANAGE));
        self::assertTrue(Role::Dpo->can(Permission::POLICIES_APPROVE));
        self::assertTrue(Role::Dpo->can(Permission::AUDIT_VIEW));
    }

    public function testColaboradorTemMenorPrivilegio(): void
    {
        $role = Role::Collaborator;
        self::assertTrue($role->can(Permission::INCIDENTS_REPORT));
        self::assertTrue($role->can(Permission::TRAININGS_VIEW));
        foreach ([Permission::USERS_VIEW, Permission::AUDIT_VIEW, Permission::INVENTORY_EDIT, Permission::INCIDENTS_MANAGE, Permission::SETTINGS_MANAGE] as $p) {
            self::assertFalse($role->can($p), $p);
        }
    }

    public function testGestorNaoVeAuditoriaNemUsuarios(): void
    {
        self::assertTrue(Role::Manager->can(Permission::INVENTORY_EDIT));
        self::assertFalse(Role::Manager->can(Permission::AUDIT_VIEW));
        self::assertFalse(Role::Manager->can(Permission::USERS_VIEW));
    }

    public function testPermissoesDeCadaPerfilExistem(): void
    {
        foreach (Role::cases() as $role) {
            self::assertSame([], array_diff($role->permissions(), Permission::all()), $role->value);
        }
        self::assertNull(Role::tryFrom('superuser'));
    }
}
