<?php

declare(strict_types=1);

namespace Isis\Auth;

use Isis\Auth\Permission as P;

/**
 * Perfis de acesso. A matriz de permissões fica aqui, num único lugar.
 */
enum Role: string
{
    case Admin = 'admin';
    case Dpo = 'encarregado';
    case Manager = 'gestor';
    case Collaborator = 'colaborador';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador da empresa',
            self::Dpo => 'Encarregado de dados (DPO)',
            self::Manager => 'Gestor',
            self::Collaborator => 'Colaborador',
            self::Auditor => 'Auditor',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Acesso total: usuários, configurações da empresa e todos os módulos.',
            self::Dpo => 'Conduz a conformidade: inventário, riscos, titulares, incidentes, políticas e treinamentos.',
            self::Manager => 'Mantém o inventário, os riscos e os planos de ação do seu setor.',
            self::Collaborator => 'Executa tarefas atribuídas, faz treinamentos, lê políticas e comunica incidentes.',
            self::Auditor => 'Somente leitura de todos os módulos e da trilha de auditoria.',
        };
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => P::all(),
            self::Dpo => array_values(array_diff(P::all(), [P::USERS_MANAGE, P::SETTINGS_MANAGE])),
            self::Manager => [
                P::DASHBOARD_VIEW,
                P::INVENTORY_VIEW, P::INVENTORY_EDIT,
                P::RISKS_VIEW, P::RISKS_EDIT,
                P::ACTIONS_VIEW, P::ACTIONS_EDIT,
                P::REQUESTS_VIEW,
                P::INCIDENTS_VIEW, P::INCIDENTS_REPORT,
                P::VENDORS_VIEW,
                P::POLICIES_VIEW,
                P::TRAININGS_VIEW,
            ],
            self::Collaborator => [
                P::DASHBOARD_VIEW,
                P::ACTIONS_VIEW,
                P::INCIDENTS_REPORT,
                P::POLICIES_VIEW,
                P::TRAININGS_VIEW,
            ],
            self::Auditor => [...P::readOnly(), P::AUDIT_VIEW],
        };
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /** @return array<string, string> valor => rótulo */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $role) {
            $options[$role->value] = $role->label();
        }
        return $options;
    }
}
