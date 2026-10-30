<?php

declare(strict_types=1);

namespace Isis\Services;

use Isis\Core\Config;
use Isis\Core\Database;
use PDO;

/**
 * Cópias de segurança: banco (exportação SQL em PHP puro, sem depender do mysqldump) + evidências,
 * compactados em ZIP e criptografados (libsodium secretstream, chave derivada da APP_KEY).
 * Formato do arquivo .isisbak: "ISISBAK1" + cabeçalho do secretstream + blocos [tamanho 4 bytes][dados cifrados].
 */
final class BackupService
{
    private const string MAGIC = 'ISISBAK1';
    private const int CHUNK = 1048576;
    private const int ROWS_PER_INSERT = 200;

    public static function directory(): string
    {
        $dir = APP_ROOT . '/storage/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir;
    }

    /** Gera um backup completo. Retorna informações do arquivo criado. @return array{file: string, size: int, tables: int, rows: int, files: int} */
    public static function create(bool $withFiles = true): array
    {
        $tmp = self::tempDir();
        try {
            $sqlFile = $tmp . '/database.sql';
            [$tables, $rows] = self::dumpDatabase($sqlFile);

            $zipFile = $tmp . '/backup.zip';
            $zip = new \ZipArchive();
            if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Não foi possível criar o arquivo compactado.');
            }
            $zip->addFile($sqlFile, 'database.sql');
            $files = 0;
            $uploads = APP_ROOT . '/storage/uploads';
            if ($withFiles && is_dir($uploads)) {
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($uploads, \FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if ($file->isFile() && $file->getFilename() !== '.gitkeep') {
                        $relative = 'uploads/' . str_replace('\\', '/', substr($file->getPathname(), strlen($uploads) + 1));
                        $zip->addFile($file->getPathname(), $relative);
                        $files++;
                    }
                }
            }
            $zip->addFromString('manifest.json', (string) json_encode([
                'app' => 'ÍSIS', 'version' => APP_VERSION, 'created_at' => date('c'),
                'database' => Config::get('db.database'), 'tables' => $tables, 'rows' => $rows, 'files' => $files,
                'database_sha256' => hash_file('sha256', $sqlFile),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $zip->close();

            $target = self::directory() . '/isis-' . date('Ymd-His') . '.isisbak';
            self::encryptFile($zipFile, $target);
            @chmod($target, 0640);
            return ['file' => $target, 'size' => (int) filesize($target), 'tables' => $tables, 'rows' => $rows, 'files' => $files];
        } finally {
            self::removeDir($tmp);
        }
    }

    /** @return list<array{name: string, path: string, size: int, date: string}> mais recentes primeiro */
    public static function list(): array
    {
        $items = [];
        foreach (glob(self::directory() . '/isis-*.isisbak') ?: [] as $path) {
            $items[] = ['name' => basename($path), 'path' => $path, 'size' => (int) filesize($path), 'date' => date('Y-m-d H:i:s', (int) filemtime($path))];
        }
        usort($items, static fn (array $a, array $b): int => strcmp($b['name'], $a['name']));
        return $items;
    }

    /**
     * Mantém os N backups mais recentes e, além deles, o último de cada uma das semanas anteriores (até $weekly).
     *
     * @return list<string> arquivos removidos
     */
    public static function rotate(int $daily, int $weekly = 4): array
    {
        $keep = [];
        $weeks = [];
        foreach (self::list() as $i => $item) {
            if ($i < $daily) {
                $keep[$item['path']] = true;
                continue;
            }
            $stamp = \DateTimeImmutable::createFromFormat('Ymd-His', substr($item['name'], 5, 15));
            $week = $stamp ? $stamp->format('o-W') : $item['name'];
            if (!isset($weeks[$week]) && count($weeks) < $weekly) {
                $weeks[$week] = true;
                $keep[$item['path']] = true;
            }
        }
        $removed = [];
        foreach (self::list() as $item) {
            if (!isset($keep[$item['path']]) && @unlink($item['path'])) {
                $removed[] = $item['name'];
            }
        }
        return $removed;
    }

    /** Decifra o backup para um ZIP comum (para conferência ou restauração manual). */
    public static function decryptTo(string $backup, string $zipTarget): array
    {
        self::decryptFile($backup, $zipTarget);
        $zip = new \ZipArchive();
        if ($zip->open($zipTarget) !== true) {
            throw new \RuntimeException('Conteúdo do backup inválido.');
        }
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true) ?: [];
        $zip->close();
        return $manifest;
    }

    /**
     * Restaura o banco (e, opcionalmente, as evidências) a partir de um backup.
     * Por segurança, só restaura em banco vazio, a menos que $force seja verdadeiro.
     *
     * @return array{statements: int, files: int, manifest: array}
     */
    public static function restore(string $backup, PDO $target, bool $force, bool $withFiles): array
    {
        $tables = $target->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
        if ($tables !== [] && !$force) {
            throw new \RuntimeException('O banco de destino não está vazio. Use um banco novo ou confirme com --force (os dados atuais serão substituídos).');
        }
        $tmp = self::tempDir();
        try {
            $zipFile = $tmp . '/backup.zip';
            $manifest = self::decryptTo($backup, $zipFile);
            $zip = new \ZipArchive();
            $zip->open($zipFile);
            $zip->extractTo($tmp, 'database.sql');
            $sql = $tmp . '/database.sql';
            if (isset($manifest['database_sha256']) && hash_file('sha256', $sql) !== $manifest['database_sha256']) {
                throw new \RuntimeException('O arquivo do banco não confere com o hash registrado no backup.');
            }
            $statements = 0;
            $buffer = '';
            $handle = fopen($sql, 'rb');
            while (($line = fgets($handle)) !== false) {
                if ($buffer === '' && (str_starts_with($line, '--') || trim($line) === '')) {
                    continue;
                }
                $buffer .= $line;
                if (str_ends_with(rtrim($line), ';')) {
                    $target->exec($buffer);
                    $statements++;
                    $buffer = '';
                }
            }
            fclose($handle);

            $files = 0;
            if ($withFiles) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = (string) $zip->getNameIndex($i);
                    if (str_starts_with($name, 'uploads/') && !str_contains($name, '..') && !str_ends_with($name, '/')) {
                        $dest = APP_ROOT . '/storage/' . $name;
                        if (!is_dir(dirname($dest))) {
                            mkdir(dirname($dest), 0750, true);
                        }
                        file_put_contents($dest, $zip->getFromIndex($i));
                        $files++;
                    }
                }
            }
            $zip->close();
            return ['statements' => $statements, 'files' => $files, 'manifest' => $manifest];
        } finally {
            self::removeDir($tmp);
        }
    }

    /** Exporta o banco em SQL (estrutura + dados). @return array{0: int, 1: int} [tabelas, linhas] */
    public static function dumpDatabase(string $file): array
    {
        $pdo = Database::pdo();
        $out = fopen($file, 'wb');
        fwrite($out, "-- ÍSIS - cópia de segurança do banco\n-- Gerada em " . date('c') . "\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
        $tables = array_column($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM), 0);
        $total = 0;
        foreach ($tables as $table) {
            Database::assertIdentifier($table);
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
            fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");

            $keys = array_column($pdo->query("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'")->fetchAll(), 'Column_name');
            $order = $keys ? ' ORDER BY `' . implode('`, `', $keys) . '`' : '';
            $offset = 0;
            do {
                $rows = $pdo->query("SELECT * FROM `{$table}`{$order} LIMIT " . self::ROWS_PER_INSERT . " OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);
                if ($rows !== []) {
                    $columns = '`' . implode('`, `', array_keys($rows[0])) . '`';
                    $values = array_map(static fn (array $row): string => '(' . implode(', ', array_map(
                        static fn (mixed $v): string => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)),
                        $row,
                    )) . ')', $rows);
                    fwrite($out, "INSERT INTO `{$table}` ({$columns}) VALUES\n" . implode(",\n", $values) . ";\n");
                    $total += count($rows);
                }
                $offset += self::ROWS_PER_INSERT;
            } while (count($rows) === self::ROWS_PER_INSERT);
            fwrite($out, "\n");
        }
        fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($out);
        return [count($tables), $total];
    }

    private static function key(): string
    {
        $raw = base64_decode(substr((string) Config::get('app.key'), 7), true);
        if ($raw === false || strlen($raw) !== 32) {
            throw new \RuntimeException('APP_KEY não configurada: o backup precisa dela para ser criptografado.');
        }
        return hash_hkdf('sha256', $raw, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES, 'isis:backup');
    }

    public static function encryptFile(string $source, string $target): void
    {
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push(self::key());
        $in = fopen($source, 'rb');
        $out = fopen($target, 'wb');
        fwrite($out, self::MAGIC . $header);
        $size = (int) filesize($source);
        $read = 0;
        do {
            $chunk = (string) fread($in, self::CHUNK);
            $read += strlen($chunk);
            $tag = $read >= $size ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
            $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag);
            fwrite($out, pack('N', strlen($cipher)) . $cipher);
        } while ($read < $size);
        fclose($in);
        fclose($out);
    }

    public static function decryptFile(string $source, string $target): void
    {
        $in = fopen($source, 'rb');
        if ($in === false || fread($in, strlen(self::MAGIC)) !== self::MAGIC) {
            throw new \RuntimeException('Arquivo não é um backup do ÍSIS.');
        }
        $header = (string) fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, self::key());
        $out = fopen($target, 'wb');
        $final = false;
        while (!$final && ($len = fread($in, 4)) !== false && strlen($len) === 4) {
            $cipher = (string) fread($in, unpack('N', $len)[1]);
            $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
            if ($result === false) {
                fclose($out);
                @unlink($target);
                throw new \RuntimeException('Não foi possível decifrar o backup (APP_KEY diferente ou arquivo alterado).');
            }
            [$plain, $tag] = $result;
            fwrite($out, $plain);
            $final = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
        }
        fclose($in);
        fclose($out);
        if (!$final) {
            @unlink($target);
            throw new \RuntimeException('Backup incompleto ou truncado.');
        }
    }

    private static function tempDir(): string
    {
        $dir = APP_ROOT . '/storage/cache/backup-' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);
        return $dir;
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
