<?php

declare(strict_types=1);

namespace Isis\Services;

use Isis\Audit\AuditHasher;
use Isis\Core\Config;
use Isis\Core\Database;

/**
 * Limpeza periódica e verificação do ambiente (para o agendador e para o suporte).
 */
final class MaintenanceService
{
    /** Remove registros temporários vencidos. @return array<string, int> */
    public static function cleanup(): array
    {
        $result = [
            'tentativas_de_acesso' => Database::execute('DELETE FROM rate_limit_hits WHERE created_at < (NOW() - INTERVAL 2 DAY)'),
            'tokens_vencidos' => Database::execute('DELETE FROM user_tokens WHERE (used_at IS NOT NULL OR expires_at < NOW()) AND created_at < (NOW() - INTERVAL 30 DAY)'),
            'registro_de_lembretes' => Database::execute('DELETE FROM reminder_log WHERE sent_on < (CURDATE() - INTERVAL 90 DAY)'),
            'sessoes_expiradas' => 0,
            'temporarios' => 0,
        ];
        $maxAge = ((int) Config::get('session.max_hours', 8)) * 3600 + 3600;
        foreach (glob(APP_ROOT . '/storage/sessions/sess_*') ?: [] as $file) {
            if (filemtime($file) < time() - $maxAge && @unlink($file)) {
                $result['sessoes_expiradas']++;
            }
        }
        foreach (glob(APP_ROOT . '/storage/cache/backup-*') ?: [] as $dir) {
            if (is_dir($dir) && filemtime($dir) < time() - 86400) {
                array_map('unlink', glob($dir . '/*') ?: []);
                @rmdir($dir) && $result['temporarios']++;
            }
        }
        return $result;
    }

    /**
     * Verificação do ambiente: requisitos, banco, migrations, auditoria e backup.
     *
     * @return list<array{ok: bool, label: string, detail: string}>
     */
    public static function check(): array
    {
        $checks = [];
        foreach (Installer::requirements() as $label => $req) {
            $checks[] = ['ok' => $req['ok'], 'label' => $label, 'detail' => $req['detail']];
        }
        try {
            $version = (string) Database::value('SELECT VERSION()');
            $checks[] = ['ok' => true, 'label' => 'Conexão com o banco', 'detail' => $version];
            $files = array_map(static fn (string $f): string => basename($f, '.sql'), glob(APP_ROOT . '/database/migrations/*.sql') ?: []);
            $applied = array_column(Database::fetchAll('SELECT version FROM schema_migrations'), 'version');
            $pending = array_diff($files, $applied);
            $checks[] = ['ok' => $pending === [], 'label' => 'Migrations aplicadas', 'detail' => $pending === [] ? count($applied) . ' de ' . count($files) : 'pendentes: ' . implode(', ', $pending) . ' (rode php bin/isis migrate)'];

            $broken = [];
            foreach (array_map('intval', array_column(Database::fetchAll('SELECT chain_key FROM audit_chain'), 'chain_key')) as $key) {
                $stmt = Database::pdo()->prepare($key === 0 ? 'SELECT * FROM audit_logs WHERE tenant_id IS NULL ORDER BY id' : 'SELECT * FROM audit_logs WHERE tenant_id = ? ORDER BY id');
                $stmt->execute($key === 0 ? [] : [$key]);
                if (!AuditHasher::verify($stmt->fetchAll())['ok']) {
                    $broken[] = $key === 0 ? 'global' : "empresa {$key}";
                }
            }
            $checks[] = ['ok' => $broken === [], 'label' => 'Integridade da auditoria', 'detail' => $broken === [] ? 'íntegra' : 'violada: ' . implode(', ', $broken)];
        } catch (\Throwable $e) {
            $checks[] = ['ok' => false, 'label' => 'Conexão com o banco', 'detail' => $e->getMessage()];
        }
        $last = BackupService::list()[0] ?? null;
        $fresh = $last !== null && strtotime($last['date']) > time() - 2 * 86400;
        $checks[] = ['ok' => $fresh, 'label' => 'Backup recente (até 2 dias)', 'detail' => $last ? $last['name'] . ' · ' . fmt_bytes($last['size']) : 'nenhum backup em storage/backups'];
        $checks[] = ['ok' => Config::get('mail.driver') === 'smtp', 'label' => 'E-mail por SMTP', 'detail' => (string) Config::get('mail.driver') . (Config::get('mail.driver') === 'log' ? ' (e-mails só no log; configure MAIL_* para produção)' : '')];
        $checks[] = ['ok' => !Config::get('app.debug'), 'label' => 'Modo de depuração desligado', 'detail' => Config::get('app.debug') ? 'APP_DEBUG=true: desligue em produção' : 'ok'];
        return $checks;
    }
}
