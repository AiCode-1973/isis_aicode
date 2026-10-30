<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Database;

/**
 * Assinaturas e faturas. Consultas por empresa explícita: a área da empresa passa sempre o
 * tenant da sessão e o painel da plataforma passa a empresa escolhida.
 */
final class SubscriptionRepository
{
    private const string SELECT = 'SELECT s.*, p.name AS plan_name, p.price_monthly_cents, p.price_yearly_cents, p.max_users, p.max_vendors,
            p.max_trainings, p.contact_only, rp.name AS requested_plan_name
        FROM subscriptions s
        JOIN plans p ON p.code = s.plan_code
        LEFT JOIN plans rp ON rp.code = s.requested_plan_code';

    public function forTenant(int $tenantId): ?array
    {
        return Database::fetch(self::SELECT . ' WHERE s.tenant_id = ?', [$tenantId]);
    }

    public function find(int $id): ?array
    {
        return Database::fetch(self::SELECT . ' WHERE s.id = ?', [$id]);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        return Database::insert('subscriptions', $data);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $allowed = ['plan_code', 'billing_cycle', 'status', 'trial_ends_at', 'current_period_start', 'current_period_end', 'custom_price_cents',
            'notes', 'requested_plan_code', 'requested_cycle', 'requested_message', 'requested_at', 'last_notice'];
        $data = array_intersect_key($data, array_flip($allowed));
        if ($data === []) {
            return;
        }
        $sets = implode(', ', array_map(static fn (string $c): string => "`{$c}` = ?", array_keys($data)));
        Database::execute("UPDATE subscriptions SET {$sets}, updated_at = NOW() WHERE id = ?", [...array_values($data), $id]);
    }

    /**
     * Empresas com assinatura, para o painel da plataforma.
     *
     * @return list<array<string, mixed>>
     */
    public function listForPlatform(string $search = ''): array
    {
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = ' WHERE t.legal_name LIKE ? OR t.trade_name LIKE ? OR t.cnpj LIKE ?';
            $like = '%' . $search . '%';
            $params = [$like, $like, '%' . preg_replace('/[^0-9A-Za-z]/', '', $search) . '%'];
        }
        return Database::fetchAll(
            'SELECT t.id AS tenant_id, t.legal_name, t.trade_name, t.cnpj, t.status AS tenant_status, t.created_at AS tenant_created_at,
                s.*, p.name AS plan_name, p.price_monthly_cents, p.price_yearly_cents, p.max_users,
                (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id AND u.status = \'active\') AS active_users,
                (SELECT COUNT(*) FROM invoices i WHERE i.tenant_id = t.id AND i.status = \'aberta\') AS open_invoices,
                (SELECT MAX(u.last_login_at) FROM users u WHERE u.tenant_id = t.id) AS last_login_at
             FROM tenants t
             LEFT JOIN subscriptions s ON s.tenant_id = t.id
             LEFT JOIN plans p ON p.code = s.plan_code' . $where . '
             ORDER BY t.created_at DESC',
            $params,
        );
    }

    /** @return list<array<string, mixed>> */
    public function invoices(int $tenantId): array
    {
        return Database::fetchAll('SELECT * FROM invoices WHERE tenant_id = ? ORDER BY due_date DESC, id DESC', [$tenantId]);
    }

    public function invoice(int $tenantId, int $invoiceId): ?array
    {
        return Database::fetch('SELECT * FROM invoices WHERE tenant_id = ? AND id = ?', [$tenantId, $invoiceId]);
    }

    /** @param array<string, mixed> $data */
    public function createInvoice(array $data): int
    {
        // Número provisório único; depois vira AAAA-NNNNN a partir do id.
        $id = Database::insert('invoices', $data + ['number' => 'tmp-' . bin2hex(random_bytes(6))]);
        Database::execute('UPDATE invoices SET number = ? WHERE id = ?', [sprintf('%s-%05d', date('Y'), $id), $id]);
        return $id;
    }

    /** @param array<string, mixed> $data */
    public function updateInvoice(int $id, array $data): void
    {
        $allowed = ['status', 'paid_at', 'payment_method', 'payment_reference', 'notes'];
        $data = array_intersect_key($data, array_flip($allowed));
        $sets = implode(', ', array_map(static fn (string $c): string => "`{$c}` = ?", array_keys($data)));
        Database::execute("UPDATE invoices SET {$sets} WHERE id = ?", [...array_values($data), $id]);
    }

    /** @return list<array<string, mixed>> */
    public function recentPayments(int $limit = 10): array
    {
        return Database::fetchAll(
            'SELECT i.*, t.legal_name, t.trade_name FROM invoices i JOIN tenants t ON t.id = i.tenant_id
             WHERE i.status = \'paga\' ORDER BY i.paid_at DESC LIMIT ' . max(1, $limit),
        );
    }

    /** @return list<array<string, mixed>> */
    public function allSubscriptions(): array
    {
        return Database::fetchAll(self::SELECT . ' ORDER BY s.id');
    }
}
