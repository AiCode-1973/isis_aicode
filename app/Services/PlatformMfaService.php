<?php

declare(strict_types=1);

namespace Isis\Services;

use Isis\Auth\Totp;
use Isis\Core\Config;
use Isis\Core\Crypto;
use Isis\Core\Database;

/**
 * Verificação em duas etapas da equipe AiCode (painel da plataforma). Obrigatória: quem ainda não ativou
 * cadastra o aplicativo logo depois da senha. Sem opção de desativar pelo painel; a redefinição é feita
 * no servidor (php bin/isis plataforma:2fa-redefinir).
 */
final class PlatformMfaService
{
    public const int RECOVERY_CODES = 10;

    public static function isEnabled(array $admin): bool
    {
        return !empty($admin['mfa_enabled_at']) && !empty($admin['mfa_secret_enc']);
    }

    /** @return list<string>|null códigos de recuperação (mostrados uma única vez), ou null se o código não confere */
    public static function enable(array $admin, string $secret, string $code): ?array
    {
        $step = Totp::verify($secret, $code);
        if ($step === null) {
            return null;
        }
        return Database::transaction(function () use ($admin, $secret, $step): array {
            Database::execute(
                'UPDATE platform_admins SET mfa_secret_enc = ?, mfa_enabled_at = ?, mfa_last_step = ? WHERE id = ?',
                [Crypto::encrypt($secret, self::context($admin)), date('Y-m-d H:i:s'), $step, $admin['id']],
            );
            return self::replaceRecoveryCodes($admin);
        });
    }

    /** Apaga o cadastro do aplicativo e os códigos; na próxima entrada a pessoa cadastra de novo. */
    public static function reset(array $admin): void
    {
        Database::transaction(static function () use ($admin): void {
            Database::execute('UPDATE platform_admins SET mfa_secret_enc = NULL, mfa_enabled_at = NULL, mfa_last_step = NULL WHERE id = ?', [$admin['id']]);
            Database::execute('DELETE FROM platform_admin_recovery_codes WHERE admin_id = ?', [$admin['id']]);
        });
    }

    /** @return 'totp'|'recovery'|null */
    public static function verify(array $admin, string $input): ?string
    {
        if (!self::isEnabled($admin)) {
            return null;
        }
        $input = trim($input);
        if (preg_match('/^\d[\d\s]*$/', $input) === 1) {
            $secret = (string) Crypto::decrypt($admin['mfa_secret_enc'], self::context($admin));
            $last = $admin['mfa_last_step'] !== null ? (int) $admin['mfa_last_step'] : null;
            $step = Totp::verify($secret, $input, $last);
            if ($step === null) {
                return null;
            }
            // Só grava se nenhuma outra requisição usou esta janela antes (evita reaproveitar o mesmo código).
            $updated = Database::execute(
                'UPDATE platform_admins SET mfa_last_step = ? WHERE id = ? AND (mfa_last_step IS NULL OR mfa_last_step < ?)',
                [$step, $admin['id'], $step],
            );
            return $updated === 1 ? 'totp' : null;
        }
        $clean = strtolower(preg_replace('/[\s-]/', '', $input) ?? '');
        if (preg_match('/^[a-z2-7]{10}$/', $clean) !== 1) {
            return null;
        }
        $used = Database::execute(
            'UPDATE platform_admin_recovery_codes SET used_at = ? WHERE admin_id = ? AND code_hash = ? AND used_at IS NULL',
            [date('Y-m-d H:i:s'), $admin['id'], self::hashRecovery($clean)],
        );
        return $used === 1 ? 'recovery' : null;
    }

    /** @return list<string> novos códigos; os anteriores deixam de valer */
    public static function replaceRecoveryCodes(array $admin): array
    {
        Database::execute('DELETE FROM platform_admin_recovery_codes WHERE admin_id = ?', [$admin['id']]);
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $raw = strtolower(substr(Totp::generateSecret(), 0, 10));
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5);
            Database::insert('platform_admin_recovery_codes', ['admin_id' => $admin['id'], 'code_hash' => self::hashRecovery($raw)]);
        }
        return $codes;
    }

    public static function remainingRecoveryCodes(array $admin): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM platform_admin_recovery_codes WHERE admin_id = ? AND used_at IS NULL', [$admin['id']]);
    }

    private static function hashRecovery(string $normalized): string
    {
        return hash_hmac('sha256', $normalized, 'isis-platform-recovery|' . (string) Config::get('app.key'));
    }

    private static function context(array $admin): string
    {
        return 'platform_admin:' . (int) $admin['id'];
    }
}
