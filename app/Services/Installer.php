<?php

declare(strict_types=1);

namespace Isis\Services;

use Isis\Core\Database;

/**
 * Instalação e atualização do banco: migrations (database/migrations/*.sql) e catálogo de segmentos.
 */
final class Installer
{
    /** @return array<string, array{ok: bool, detail: string}> */
    public static function requirements(): array
    {
        $checks = [
            'PHP 8.3 ou superior' => [version_compare(PHP_VERSION, '8.3.0', '>='), PHP_VERSION],
        ];
        foreach (['pdo_mysql', 'sodium', 'mbstring', 'openssl', 'fileinfo'] as $ext) {
            $checks["Extensão {$ext}"] = [extension_loaded($ext), extension_loaded($ext) ? 'ativa' : 'ausente'];
        }
        foreach (['storage/logs', 'storage/sessions', 'storage/uploads', 'storage/backups'] as $dir) {
            $path = APP_ROOT . '/' . $dir;
            $checks["Pasta {$dir} gravável"] = [is_dir($path) && is_writable($path), is_dir($path) ? (is_writable($path) ? 'ok' : 'sem permissão') : 'não existe'];
        }
        $key = (string) \Isis\Core\Config::get('app.key');
        $checks['APP_KEY configurada'] = [str_starts_with($key, 'base64:') && strlen($key) > 40, $key === '' ? 'vazia' : 'ok'];

        $result = [];
        foreach ($checks as $label => [$ok, $detail]) {
            $result[$label] = ['ok' => (bool) $ok, 'detail' => (string) $detail];
        }
        return $result;
    }

    /** @return list<string> Migrations aplicadas nesta execução */
    public static function migrate(): array
    {
        $pdo = Database::pdo();
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(100) NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $applied = array_column(Database::fetchAll('SELECT version FROM schema_migrations'), 'version');
        $files = glob(APP_ROOT . '/database/migrations/*.sql') ?: [];
        sort($files);

        $ran = [];
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (in_array($version, $applied, true)) {
                continue;
            }
            foreach (self::splitStatements((string) file_get_contents($file)) as $statement) {
                $pdo->exec($statement); // DDL faz commit implícito no MySQL; cada arquivo deve ser idempotente por versão
            }
            Database::execute('INSERT INTO schema_migrations (version) VALUES (?)', [$version]);
            $ran[] = $version;
        }
        return $ran;
    }

    /** Atualiza o catálogo global de segmentos (pode rodar quantas vezes quiser). */
    public static function seedSegments(): int
    {
        $segments = require APP_ROOT . '/database/seeds/segments.php';
        foreach ($segments as $i => $segment) {
            Database::execute(
                'INSERT INTO segments (code, name, description, template, sort_order) VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), template = VALUES(template), sort_order = VALUES(sort_order)',
                [$segment['code'], $segment['name'], $segment['description'], json_encode($segment['template'], JSON_UNESCAPED_UNICODE), $i + 1],
            );
        }
        return count($segments);
    }

    public static function isInstalled(): bool
    {
        return is_file(self::lockFile());
    }

    public static function lock(): void
    {
        file_put_contents(self::lockFile(), date('c') . "\n");
    }

    public static function lockFile(): string
    {
        return APP_ROOT . '/storage/install.lock';
    }

    /**
     * Divide o SQL em comandos (separador: ";" no fim da linha). Remove comentários de linha "--".
     *
     * @return list<string>
     */
    public static function splitStatements(string $sql): array
    {
        $lines = array_filter(
            preg_split('/\R/', $sql) ?: [],
            static fn (string $line): bool => !str_starts_with(ltrim($line), '--'),
        );
        $statements = preg_split('/;\s*(?:\R|$)/', implode("\n", $lines)) ?: [];
        return array_values(array_filter(array_map('trim', $statements), static fn (string $s): bool => $s !== ''));
    }
}
