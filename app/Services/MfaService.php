<?php

declare(strict_types=1);

namespace Isis\Services;

use Isis\Auth\Role;
use Isis\Auth\Totp;
use Isis\Core\Config;
use Isis\Core\Crypto;
use Isis\Core\Database;

/**
 * Verificação em duas etapas dos usuários das empresas: ativação, conferência no login,
 * códigos de recuperação e política da empresa.
 */
final class MfaService
{
    public const int RECOVERY_CODES = 10;

    /** Perfis atingidos pela política "gestao". */
    private const array MANAGEMENT_ROLES = [Role::Admin, Role::Dpo];

    public static function isEnabled(array $user): bool
    {
        return !empty($user['mfa_enabled_at']) && !empty($user['mfa_secret_enc']);
    }

    /** A política da empresa exige a verificação para este usuário? */
    public static function isRequired(array $user, ?array $tenant): bool
    {
        return match ($tenant['mfa_policy'] ?? 'opcional') {
            'todos' => true,
            'gestao' => in_array(Role::tryFrom((string) $user['role']), self::MANAGEMENT_ROLES, true),
            default => false,
        };
    }

    /** Usuário logado que ainda precisa ativar (a política exige e ele não ativou). */
    public static function setupPending(array $user, ?array $tenant): bool
    {
        return self::isRequired($user, $tenant) && !self::isEnabled($user);
    }

    /**
     * Ativa com o segredo que o usuário acabou de cadastrar no aplicativo, se o código conferir.
     *
     * @return list<string>|null códigos de recuperação (mostrados uma única vez), ou null se o código não confere
     */
    public static function enable(array $user, string $secret, string $code): ?array
    {
        $step = Totp::verify($secret, $code);
        if ($step === null) {
            return null;
        }
        return Database::transaction(function () use ($user, $secret, $step): array {
            Database::execute(
                'UPDATE users SET mfa_secret_enc = ?, mfa_enabled_at = ?, mfa_last_step = ? WHERE tenant_id = ? AND id = ?',
                [Crypto::encrypt($secret, self::context($user)), date('Y-m-d H:i:s'), $step, $user['tenant_id'], $user['id']],
            );
            return self::replaceRecoveryCodes($user);
        });
    }

    public static function disable(array $user): void
    {
        Database::transaction(static function () use ($user): void {
            Database::execute(
                'UPDATE users SET mfa_secret_enc = NULL, mfa_enabled_at = NULL, mfa_last_step = NULL WHERE tenant_id = ? AND id = ?',
                [$user['tenant_id'], $user['id']],
            );
            Database::execute('DELETE FROM user_recovery_codes WHERE tenant_id = ? AND user_id = ?', [$user['tenant_id'], $user['id']]);
        });
    }

    /**
     * Confere o código do login: primeiro como código do aplicativo, depois como código de recuperação.
     *
     * @return 'totp'|'recovery'|null
     */
    public static function verify(array $user, string $input): ?string
    {
        if (!self::isEnabled($user)) {
            return null;
        }
        $input = trim($input);
        if (preg_match('/^\d[\d\s]*$/', $input) === 1) {
            $secret = (string) Crypto::decrypt($user['mfa_secret_enc'], self::context($user));
            $last = $user['mfa_last_step'] !== null ? (int) $user['mfa_last_step'] : null;
            $step = Totp::verify($secret, $input, $last);
            if ($step === null) {
                return null;
            }
            // Só grava se nenhuma outra requisição usou esta janela antes (evita reaproveitar o mesmo código).
            $updated = Database::execute(
                'UPDATE users SET mfa_last_step = ? WHERE tenant_id = ? AND id = ? AND (mfa_last_step IS NULL OR mfa_last_step < ?)',
                [$step, $user['tenant_id'], $user['id'], $step],
            );
            return $updated === 1 ? 'totp' : null;
        }
        $normalized = self::normalizeRecovery($input);
        if ($normalized === null) {
            return null;
        }
        $used = Database::execute(
            'UPDATE user_recovery_codes SET used_at = ? WHERE tenant_id = ? AND user_id = ? AND code_hash = ? AND used_at IS NULL',
            [date('Y-m-d H:i:s'), $user['tenant_id'], $user['id'], self::hashRecovery($normalized)],
        );
        return $used === 1 ? 'recovery' : null;
    }

    /** @return list<string> novos códigos; os anteriores deixam de valer */
    public static function replaceRecoveryCodes(array $user): array
    {
        Database::execute('DELETE FROM user_recovery_codes WHERE tenant_id = ? AND user_id = ?', [$user['tenant_id'], $user['id']]);
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $raw = strtolower(substr(Totp::generateSecret(), 0, 10));
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5);
            Database::insert('user_recovery_codes', [
                'tenant_id' => $user['tenant_id'], 'user_id' => $user['id'], 'code_hash' => self::hashRecovery($raw),
            ]);
        }
        return $codes;
    }

    public static function remainingRecoveryCodes(array $user): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM user_recovery_codes WHERE tenant_id = ? AND user_id = ? AND used_at IS NULL',
            [$user['tenant_id'], $user['id']],
        );
    }

    /** @return array{enabled: int, active: int} adesão na empresa */
    public static function adoption(int $tenantId): array
    {
        $row = Database::fetch(
            "SELECT SUM(mfa_enabled_at IS NOT NULL) AS enabled, COUNT(*) AS active FROM users WHERE tenant_id = ? AND status = 'active'",
            [$tenantId],
        ) ?? [];
        return ['enabled' => (int) ($row['enabled'] ?? 0), 'active' => (int) ($row['active'] ?? 0)];
    }

    private static function normalizeRecovery(string $input): ?string
    {
        $clean = strtolower(preg_replace('/[\s-]/', '', $input) ?? '');
        return preg_match('/^[a-z2-7]{10}$/', $clean) === 1 ? $clean : null;
    }

    private static function hashRecovery(string $normalized): string
    {
        return hash_hmac('sha256', $normalized, 'isis-recovery|' . (string) Config::get('app.key'));
    }

    private static function context(array $user): string
    {
        return 'user:' . (int) $user['id'];
    }
}
