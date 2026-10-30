<?php

declare(strict_types=1);

namespace Isis\Tests\Integration;

use Isis\Auth\Auth;
use Isis\Auth\Permission;
use Isis\Auth\Role;
use Isis\Auth\TenantContext;
use Isis\Core\Config;
use Isis\Core\Database;
use Isis\Core\Mailer;
use Isis\Services\BackupService;
use Isis\Services\MaintenanceService;
use Isis\Services\ReminderService;
use Isis\Services\ReportService;

/**
 * Fase 5: backup criptografado e restauração, lembretes por e-mail, relatório e manutenção.
 */
final class OperationsTest extends DatabaseTestCase
{
    public function testArquivoCriptografadoVoltaIgualENaoAbreSemAChave(): void
    {
        $plain = tempnam(sys_get_temp_dir(), 'isis');
        file_put_contents($plain, str_repeat("dado pessoal de teste\n", 120000)); // ~2,6 MB: vários blocos
        $enc = $plain . '.isisbak';
        $dec = $plain . '.out';
        BackupService::encryptFile($plain, $enc);
        self::assertStringNotContainsString('dado pessoal', (string) file_get_contents($enc));
        BackupService::decryptFile($enc, $dec);
        self::assertSame(hash_file('sha256', $plain), hash_file('sha256', $dec));

        // Arquivo adulterado é recusado
        $bytes = (string) file_get_contents($enc);
        $bytes[200] = chr(ord($bytes[200]) ^ 1);
        file_put_contents($enc, $bytes);
        try {
            BackupService::decryptFile($enc, $dec);
            self::fail('Deveria recusar backup alterado.');
        } catch (\RuntimeException) {
            self::assertTrue(true);
        }
        array_map('unlink', array_filter([$plain, $enc, $dec], 'is_file'));
    }

    public function testBackupCompletoRestauraEmOutroBanco(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        $result = BackupService::create(false);
        self::assertFileExists($result['file']);
        self::assertGreaterThan(30, $result['tables']);

        $restoreDb = Config::get('db.database') . '_restore';
        $server = new \PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', Config::get('db.host'), Config::get('db.port')),
            (string) Config::get('db.username'), (string) Config::get('db.password'), [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $server->exec("DROP DATABASE IF EXISTS `{$restoreDb}`");
        $server->exec("CREATE DATABASE `{$restoreDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        try {
            $target = new \PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', Config::get('db.host'), Config::get('db.port'), $restoreDb),
                (string) Config::get('db.username'), (string) Config::get('db.password'), [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            BackupService::restore($result['file'], $target, false, false);

            foreach (['users', 'audit_logs', 'dsar_requests', 'incidents', 'policies', 'training_enrollments'] as $table) {
                self::assertSame(
                    (int) Database::value("SELECT COUNT(*) FROM `{$table}`"),
                    (int) $target->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn(),
                    $table,
                );
            }
            // Dados criptografados continuam idênticos (e legíveis com a mesma APP_KEY)
            self::assertSame(
                Database::value('SELECT requester_name_enc FROM dsar_requests ORDER BY id LIMIT 1'),
                $target->query('SELECT requester_name_enc FROM dsar_requests ORDER BY id LIMIT 1')->fetchColumn(),
            );
            // Banco com dados não é sobrescrito sem confirmação
            $this->expectException(\RuntimeException::class);
            BackupService::restore($result['file'], $target, false, false);
        } finally {
            $server->exec("DROP DATABASE IF EXISTS `{$restoreDb}`");
            @unlink($result['file']);
        }
    }

    public function testLembretesUmPorPessoaPorDia(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        $tenant = Database::fetch('SELECT * FROM tenants WHERE id = ?', [$clinic['tenant']['id']]);
        $entries = TenantContext::runAs((int) $tenant['id'], static fn () => ReminderService::usersWithItems($tenant, date('Y-m-d')));
        $byUser = array_column(array_map(static fn (array $e): array => ['id' => (int) $e['user']['id'], 'groups' => $e['groups']], $entries), 'groups', 'id');

        $dpo = (int) $clinic['users']['encarregado']['id'];
        self::assertArrayHasKey($dpo, $byUser, 'O encarregado tem pendências nos dados demonstrativos.');
        self::assertArrayHasKey('Ações dos planos', $byUser[$dpo], 'Ação vencida do encarregado.');
        self::assertArrayHasKey('Solicitações sem responsável', $byUser[$dpo], 'Pedido de titular ainda sem responsável.');
        self::assertArrayNotHasKey((int) $clinic['users']['auditor']['id'], $byUser, 'Quem não tem pendências não recebe e-mail.');

        Mailer::$sent = [];
        $first = ReminderService::run();
        $second = ReminderService::run();
        self::assertGreaterThan(0, $first['sent']);
        self::assertSame(0, $second['sent']);
        self::assertSame($first['sent'], $second['skipped']);
        self::assertCount($first['sent'], Mailer::$sent);
        foreach (Mailer::$sent as $mail) {
            self::assertStringNotContainsString('João Pereira', $mail['html'], 'Lembretes não trazem dados pessoais de titulares.');
        }
    }

    public function testRelatorioReuneOsModulos(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        Auth::actAs($clinic['users']['admin']);
        $report = ReportService::build(date('Y-m-d', strtotime('-90 days')), date('Y-m-d'));
        self::assertSame(5, $report['inventory']['total']);
        self::assertSame(6, $report['risks']['total']);
        self::assertGreaterThan(0, $report['requestsPeriod']['received']);
        self::assertTrue($report['audit']['chain']['ok']);
        self::assertSame((int) $clinic['tenant']['id'], (int) $report['tenant']['id']);
    }

    public function testQuemPodeVerRelatorios(): void
    {
        self::assertTrue(Role::Admin->can(Permission::REPORTS_VIEW));
        self::assertTrue(Role::Dpo->can(Permission::REPORTS_VIEW));
        self::assertTrue(Role::Auditor->can(Permission::REPORTS_VIEW));
        self::assertFalse(Role::Manager->can(Permission::REPORTS_VIEW));
        self::assertFalse(Role::Collaborator->can(Permission::REPORTS_VIEW));
    }

    public function testManutencaoRemoveRegistrosVencidos(): void
    {
        $this->seedTwoCompanies();
        Database::execute("INSERT INTO rate_limit_hits (bucket, key_hash, success, created_at) VALUES ('login_ip', REPEAT('a', 64), 0, NOW() - INTERVAL 5 DAY)");
        Database::execute("INSERT INTO rate_limit_hits (bucket, key_hash, success, created_at) VALUES ('login_ip', REPEAT('b', 64), 0, NOW())");
        $result = MaintenanceService::cleanup();
        self::assertSame(1, $result['tentativas_de_acesso']);
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM rate_limit_hits'));
    }
}
