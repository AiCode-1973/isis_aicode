<?php

declare(strict_types=1);

namespace Isis\Services;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Auth\Role;
use Isis\Auth\TenantContext;
use Isis\Core\Config;
use Isis\Core\Crypto;
use Isis\Core\Database;
use Isis\Core\Mailer;
use Isis\Repositories\InvitationRepository;
use Isis\Repositories\TenantRepository;
use Isis\Repositories\UserRepository;
use Isis\Repositories\UserTokenRepository;

/**
 * Ciclo de vida das contas: cadastro da empresa, confirmação de e-mail, recuperação de senha e convites.
 */
final class AccountService
{
    /**
     * Cria a empresa e o administrador. Retorna o id do usuário criado.
     *
     * @param array{legal_name: string, trade_name: ?string, cnpj: string, segment_code: string, name: string, email: string, password: string} $data
     */
    public static function registerCompany(array $data): int
    {
        $userId = Database::transaction(function () use ($data): int {
            $tenantId = (new TenantRepository())->create([
                'legal_name' => $data['legal_name'],
                'trade_name' => $data['trade_name'] ?: null,
                'cnpj' => $data['cnpj'],
                'segment_code' => $data['segment_code'],
            ]);
            return TenantContext::runAs($tenantId, function () use ($data, $tenantId): int {
                $userId = (new UserRepository())->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password_hash' => Auth::hashPassword($data['password']),
                    'role' => Role::Admin->value,
                ]);
                SubscriptionService::startTrial($tenantId);
                AuditLogger::log('tenant.registered', 'tenant', $tenantId, null, [
                    'razao_social' => $data['legal_name'],
                    'cnpj' => $data['cnpj'],
                    'segmento' => $data['segment_code'],
                    'administrador' => $data['email'],
                ], ['tenant_id' => $tenantId, 'user_id' => $userId, 'user_name' => $data['name']]);
                return $userId;
            });
        });

        $user = UserRepository::findByIdGlobal($userId);
        if ($user !== null) {
            self::sendVerification($user);
        }
        return $userId;
    }

    public static function sendVerification(array $user): void
    {
        $token = UserTokenRepository::issue((int) $user['id'], UserTokenRepository::VERIFY_EMAIL, (int) Config::get('security.verify_token_hours', 48) * 60);
        Mailer::sendTemplate($user['email'], 'Confirme seu e-mail no ÍSIS', 'verify', [
            'name' => $user['name'],
            'link' => absolute_url('/verificar-email/' . $token),
        ]);
    }

    /** @return bool true se o token era válido */
    public static function verifyEmail(string $token): bool
    {
        $row = UserTokenRepository::findValid($token, UserTokenRepository::VERIFY_EMAIL);
        if ($row === null || !UserTokenRepository::consume((int) $row['id'])) {
            return false;
        }
        $user = UserRepository::findByIdGlobal((int) $row['user_id']);
        if ($user === null) {
            return false;
        }
        Database::execute('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?', [$user['id']]);
        AuditLogger::log('auth.email_verified', 'user', (int) $user['id'], null, null, [
            'tenant_id' => (int) $user['tenant_id'], 'user_id' => (int) $user['id'], 'user_name' => $user['name'],
        ]);
        return true;
    }

    /** Envia o link de redefinição se a conta existir e estiver ativa. A resposta ao usuário é sempre a mesma. */
    public static function requestPasswordReset(string $email): void
    {
        $user = UserRepository::findByEmailGlobal($email);
        if ($user === null || $user['status'] !== 'active') {
            return;
        }
        $token = UserTokenRepository::issue((int) $user['id'], UserTokenRepository::PASSWORD_RESET, (int) Config::get('security.reset_token_minutes', 60));
        Mailer::sendTemplate($user['email'], 'Redefinição de senha do ÍSIS', 'reset', [
            'name' => $user['name'],
            'link' => absolute_url('/redefinir-senha/' . $token),
            'minutes' => (int) Config::get('security.reset_token_minutes', 60),
        ]);
        AuditLogger::log('auth.password_reset_requested', 'user', (int) $user['id'], null, null, [
            'tenant_id' => (int) $user['tenant_id'], 'user_id' => (int) $user['id'], 'user_name' => $user['name'],
        ]);
    }

    public static function resetPassword(string $token, string $password): bool
    {
        $row = UserTokenRepository::findValid($token, UserTokenRepository::PASSWORD_RESET);
        if ($row === null) {
            return false;
        }
        return Database::transaction(function () use ($row, $password): bool {
            if (!UserTokenRepository::consume((int) $row['id'])) {
                return false;
            }
            $user = UserRepository::findByIdGlobal((int) $row['user_id']);
            if ($user === null) {
                return false;
            }
            // Troca a senha, confirma o e-mail (o link chegou nele) e derruba sessões abertas.
            Database::execute(
                'UPDATE users SET password_hash = ?, session_epoch = session_epoch + 1, email_verified_at = COALESCE(email_verified_at, NOW()), updated_at = NOW() WHERE id = ?',
                [Auth::hashPassword($password), $user['id']],
            );
            AuditLogger::log('auth.password_reset', 'user', (int) $user['id'], null, null, [
                'tenant_id' => (int) $user['tenant_id'], 'user_id' => (int) $user['id'], 'user_name' => $user['name'],
            ]);
            return true;
        });
    }

    /**
     * Convida um colaborador para a empresa atual. Retorna null ou a mensagem de erro.
     *
     * @param array{name: string, email: string, role: string, department_id: ?int} $data
     */
    public static function invite(array $data): ?string
    {
        if (UserRepository::emailExists($data['email'])) {
            return 'Este e-mail já tem uma conta no ÍSIS e não pode ser convidado.';
        }
        if (($limit = SubscriptionService::limitReached('users')) !== null) {
            return $limit;
        }
        $repo = new InvitationRepository();
        if ($repo->pendingForEmail($data['email']) !== null) {
            return 'Já existe um convite pendente para este e-mail. Use "Reenviar".';
        }
        $token = Crypto::randomToken();
        $id = $repo->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'department_id' => $data['department_id'],
            'token_hash' => Crypto::hashToken($token),
            'invited_by' => Auth::id(),
            'expires_at' => self::inviteExpiry(),
        ]);
        self::mailInvitation($data['email'], $data['name'], $data['role'], $token);
        AuditLogger::log('user.invited', 'invitation', $id, null, [
            'nome' => $data['name'], 'email' => $data['email'], 'perfil' => $data['role'], 'setor' => $data['department_id'],
        ]);
        return null;
    }

    public static function resendInvitation(int $id): void
    {
        $repo = new InvitationRepository();
        $invitation = $repo->findOrFail($id);
        if ($invitation['accepted_at'] !== null || $invitation['revoked_at'] !== null) {
            return;
        }
        $token = Crypto::randomToken();
        $repo->renew($id, Crypto::hashToken($token), self::inviteExpiry());
        self::mailInvitation($invitation['email'], $invitation['name'], $invitation['role'], $token);
        AuditLogger::log('invitation.resent', 'invitation', $id, null, ['email' => $invitation['email']]);
    }

    public static function revokeInvitation(int $id): void
    {
        $repo = new InvitationRepository();
        $invitation = $repo->findOrFail($id);
        $repo->revoke($id);
        AuditLogger::log('invitation.revoked', 'invitation', $id, null, ['email' => $invitation['email']]);
    }

    /**
     * Aceita o convite criando o usuário na empresa do convite. Retorna o usuário criado ou null se inválido.
     */
    public static function acceptInvitation(string $token, string $name, string $password): ?array
    {
        $invitation = InvitationRepository::findValidByTokenHash(Crypto::hashToken($token));
        if ($invitation === null || $invitation['tenant_status'] !== 'active' || UserRepository::emailExists($invitation['email'])) {
            return null;
        }
        $tenantId = (int) $invitation['tenant_id'];
        $userId = Database::transaction(fn (): int => TenantContext::runAs($tenantId, function () use ($invitation, $name, $password, $tenantId): int {
            $userId = (new UserRepository())->create([
                'name' => $name,
                'email' => $invitation['email'],
                'password_hash' => Auth::hashPassword($password),
                'role' => $invitation['role'],
                'department_id' => $invitation['department_id'],
                'email_verified_at' => date('Y-m-d H:i:s'), // o link chegou no e-mail convidado
            ]);
            (new InvitationRepository())->markAccepted((int) $invitation['id']);
            AuditLogger::log('invitation.accepted', 'user', $userId, null, ['email' => $invitation['email'], 'perfil' => $invitation['role']], [
                'tenant_id' => $tenantId, 'user_id' => $userId, 'user_name' => $name,
            ]);
            return $userId;
        }));
        return UserRepository::findByIdGlobal($userId);
    }

    private static function mailInvitation(string $email, string $name, string $role, string $token): void
    {
        $tenant = Auth::tenant() ?? [];
        Mailer::sendTemplate($email, 'Você foi convidado para o ÍSIS', 'invite', [
            'name' => $name,
            'company' => $tenant['trade_name'] ?? $tenant['legal_name'] ?? 'sua empresa',
            'inviter' => Auth::user()['name'] ?? 'O administrador',
            'role' => Role::tryFrom($role)?->label() ?? $role,
            'link' => absolute_url('/convite/' . $token),
            'days' => (int) Config::get('security.invite_days', 7),
        ]);
    }

    private static function inviteExpiry(): string
    {
        return date('Y-m-d H:i:s', strtotime('+' . (int) Config::get('security.invite_days', 7) . ' days'));
    }
}
