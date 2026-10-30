<?php

declare(strict_types=1);

namespace Isis\Tests\Integration;

use Isis\Auth\Auth;
use Isis\Auth\TenantContext;
use Isis\Core\Config;
use Isis\Core\Database;
use Isis\Core\Mailer;
use Isis\Database\Seeds\DemoSeeder;
use Isis\Services\Installer;
use PHPUnit\Framework\TestCase;

/**
 * Recria o banco de testes uma vez por execução e começa cada teste com dados limpos.
 */
abstract class DatabaseTestCase extends TestCase
{
    private static bool $migrated = false;
    private static ?string $skipReason = null;

    protected function setUp(): void
    {
        if (self::$skipReason !== null) {
            self::markTestSkipped(self::$skipReason);
        }
        if (!self::$migrated) {
            try {
                $name = (string) Config::get('db.database');
                if (!str_contains($name, 'test')) {
                    throw new \RuntimeException("Por segurança, o banco de testes precisa ter 'test' no nome (atual: {$name}).");
                }
                $server = new \PDO(
                    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', Config::get('db.host'), Config::get('db.port')),
                    (string) Config::get('db.username'),
                    (string) Config::get('db.password'),
                    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
                );
                $server->exec("DROP DATABASE IF EXISTS `{$name}`");
                $server->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                Database::setPdo(null);
                Installer::migrate();
                Installer::seedCatalogs();
                self::$migrated = true;
            } catch (\Throwable $e) {
                self::$skipReason = 'Banco de testes indisponível: ' . $e->getMessage();
                self::markTestSkipped(self::$skipReason);
            }
        }
        $this->truncate();
        Auth::actAs(null);
        TenantContext::clear();
        Mailer::$sent = [];
        \Isis\Services\SubscriptionService::forget();
        $_SESSION = [];
    }

    private function truncate(): void
    {
        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ([
            'dpia_versions', 'dpia_activities', 'dpias', 'invoices', 'subscriptions', 'platform_admins', 'reminder_log', 'training_enrollments', 'training_questions', 'training_modules', 'policy_acknowledgements', 'policy_versions', 'policies',
            'vendor_contracts', 'vendor_assessments', 'vendor_activities', 'vendors',
            'dsar_events', 'dsar_requests', 'incident_events', 'attachments', 'action_updates', 'actions', 'incidents', 'risks', 'activity_sharings', 'activity_departments', 'activity_systems',
            'activity_subject_categories', 'activity_data_categories', 'processing_activities', 'systems', 'subject_categories',
            'data_categories', 'audit_logs', 'audit_chain', 'rate_limit_hits', 'user_tokens', 'invitations', 'users',
            'departments', 'tenant_settings', 'tenants',
        ] as $table) {
            $pdo->exec("TRUNCATE TABLE `{$table}`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** Cria as duas empresas fictícias e devolve [empresaA, empresaB] com seus usuários por perfil. */
    protected function seedTwoCompanies(): array
    {
        DemoSeeder::run();
        $result = [];
        foreach (Database::fetchAll('SELECT * FROM tenants ORDER BY id') as $tenant) {
            $users = [];
            foreach (Database::fetchAll('SELECT * FROM users WHERE tenant_id = ?', [$tenant['id']]) as $user) {
                $users[$user['role']] = $user;
            }
            $result[] = ['tenant' => $tenant, 'users' => $users];
        }
        return $result;
    }

    /** Extrai o token do último link enviado por e-mail. */
    protected function lastMailToken(string $path): string
    {
        $mail = end(Mailer::$sent);
        self::assertNotFalse($mail, 'Nenhum e-mail enviado.');
        self::assertMatchesRegularExpression('#' . preg_quote($path, '#') . '/([A-Za-z0-9_-]+)#', $mail['html']);
        preg_match('#' . preg_quote($path, '#') . '/([A-Za-z0-9_-]+)#', $mail['html'], $m);
        return $m[1];
    }
}
