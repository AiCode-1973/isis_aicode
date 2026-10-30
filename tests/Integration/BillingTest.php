<?php

declare(strict_types=1);

namespace Isis\Tests\Integration;

use Isis\Auth\Auth;
use Isis\Auth\PlatformAuth;
use Isis\Controllers\PlatformController;
use Isis\Core\Config;
use Isis\Core\Database;
use Isis\Core\Mailer;
use Isis\Core\Request;
use Isis\Repositories\PlatformRepository;
use Isis\Repositories\SubscriptionRepository;
use Isis\Services\AccountService;
use Isis\Services\SubscriptionService;

/**
 * Planos e cobrança manual: teste grátis, situação efetiva, limites, pagamentos, avisos e somente leitura.
 */
final class BillingTest extends DatabaseTestCase
{
    public function testCadastroComecaComTesteGratis(): void
    {
        $userId = AccountService::registerCompany([
            'legal_name' => 'Empresa Teste Ltda', 'trade_name' => 'Teste', 'cnpj' => '11222333000181', 'segment_code' => 'servicos',
            'name' => 'Ana Admin', 'email' => 'ana@teste.example', 'password' => 'Senha@Forte2026',
        ]);
        $tenantId = (int) Database::value('SELECT tenant_id FROM users WHERE id = ?', [$userId]);
        $subscription = (new SubscriptionRepository())->forTenant($tenantId);
        self::assertSame('teste', $subscription['status']);
        self::assertSame('profissional', $subscription['plan_code']);
        self::assertSame(SubscriptionService::addDays(date('Y-m-d'), (int) Config::get('billing.trial_days')), $subscription['trial_ends_at']);
        $state = SubscriptionService::state($subscription);
        self::assertSame('teste', $state['status']);
        self::assertFalse($state['read_only']);
    }

    public function testSituacaoEfetivaPelasDatas(): void
    {
        $base = ['custom_price_cents' => null, 'billing_cycle' => 'mensal', 'price_monthly_cents' => 19900, 'price_yearly_cents' => 199000];
        $today = '2026-11-20';
        $trial = fn (string $end): array => SubscriptionService::state($base + ['status' => 'teste', 'trial_ends_at' => $end, 'current_period_end' => null], $today);
        $paid = fn (string $end): array => SubscriptionService::state($base + ['status' => 'ativa', 'trial_ends_at' => null, 'current_period_end' => $end], $today);

        self::assertSame(['teste', 0], [$trial('2026-11-20')['status'], $trial('2026-11-20')['days_left']]);
        self::assertTrue($trial('2026-11-19')['read_only']);
        self::assertSame('ativa', $paid('2026-12-01')['status']);
        self::assertSame('em_atraso', $paid('2026-11-13')['status']); // 7 dias de tolerância
        self::assertFalse($paid('2026-11-13')['read_only']);
        self::assertSame('vencida', $paid('2026-11-12')['status']);
        self::assertTrue($paid('2026-11-12')['read_only']);
        self::assertTrue(SubscriptionService::state($base + ['status' => 'suspensa', 'trial_ends_at' => null, 'current_period_end' => '2027-01-01'], $today)['read_only']);
        self::assertSame(19900, SubscriptionService::price($base));
        self::assertSame(199000, SubscriptionService::price(['billing_cycle' => 'anual'] + $base));
        self::assertSame(15000, SubscriptionService::price(['custom_price_cents' => 15000] + $base));
    }

    public function testLimitesDoPlanoEssencial(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        $tenantId = (int) $clinic['tenant']['id'];
        $this->setPlan($tenantId, 'essencial');
        Auth::actAs($clinic['users']['admin'], $clinic['tenant']);

        $usage = SubscriptionService::usage($tenantId);
        self::assertSame(5, $usage['users']['limit']);
        self::assertGreaterThanOrEqual(5, $usage['users']['used']);
        $error = AccountService::invite(['name' => 'Novo Colega', 'email' => 'novo@clinica.example', 'role' => 'colaborador', 'department_id' => null]);
        self::assertNotNull($error);
        self::assertStringContainsString('até 5 usuários', $error);

        self::assertSame(2, $usage['trainings']['limit']);
        self::assertNull(SubscriptionService::limitReached('trainings'));
        self::assertNotNull(SubscriptionService::limitReached('trainings', 2));

        $this->setPlan($tenantId, 'empresarial');
        self::assertNull(SubscriptionService::limitReached('users', 500));
        self::assertNull(AccountService::invite(['name' => 'Novo Colega', 'email' => 'novo@clinica.example', 'role' => 'colaborador', 'department_id' => null]));
    }

    public function testPagamentoEstendeOPeriodoEContinuaDoFimAnterior(): void
    {
        [, $store] = $this->seedTwoCompanies();
        $tenantId = (int) $store['tenant']['id'];
        self::assertSame('teste', SubscriptionService::forTenant($tenantId)['status']);

        // Primeiro pagamento: o período começa hoje.
        $first = SubscriptionService::createInvoice($tenantId, ['amount_cents' => 19900, 'due_date' => date('Y-m-d'), 'description' => null], 'Equipe');
        SubscriptionService::markPaid($tenantId, $first, ['paid_at' => date('Y-m-d H:i:s'), 'payment_method' => 'pix'], 'Equipe');
        $sub = SubscriptionService::forTenant($tenantId);
        $expectedEnd = (new \DateTimeImmutable())->modify('+1 month')->modify('-1 day')->format('Y-m-d');
        self::assertSame('ativa', $sub['status']);
        self::assertSame($expectedEnd, $sub['current_period_end']);

        // Segundo pagamento antecipado: continua do fim do período anterior.
        $second = SubscriptionService::createInvoice($tenantId, ['amount_cents' => 19900, 'due_date' => date('Y-m-d'), 'description' => null], 'Equipe');
        $invoice = (new SubscriptionRepository())->invoice($tenantId, $second);
        self::assertSame(SubscriptionService::addDays($expectedEnd, 1), $invoice['period_start']);
        SubscriptionService::markPaid($tenantId, $second, ['paid_at' => date('Y-m-d H:i:s'), 'payment_method' => 'boleto'], 'Equipe');
        self::assertSame($invoice['period_end'], SubscriptionService::forTenant($tenantId)['current_period_end']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{5}$/', $invoice['number']);

        // Não paga duas vezes e registra na trilha da empresa.
        self::assertSame(2, (int) Database::value("SELECT COUNT(*) FROM audit_logs WHERE tenant_id = ? AND action = 'invoice.paid'", [$tenantId]));
        $this->expectException(\DomainException::class);
        SubscriptionService::markPaid($tenantId, $second, ['paid_at' => date('Y-m-d H:i:s'), 'payment_method' => 'pix'], 'Equipe');
    }

    public function testFaturaDeOutraEmpresaNaoEPagaPorEngano(): void
    {
        [$clinic, $store] = $this->seedTwoCompanies();
        $invoiceId = (int) Database::value('SELECT id FROM invoices WHERE tenant_id = ? LIMIT 1', [$clinic['tenant']['id']]);
        self::assertGreaterThan(0, $invoiceId);
        self::assertNull((new SubscriptionRepository())->invoice((int) $store['tenant']['id'], $invoiceId));
        $this->expectException(\DomainException::class);
        SubscriptionService::cancelInvoice((int) $store['tenant']['id'], $invoiceId, 'Equipe');
    }

    public function testAvisosSaoEnviadosUmaVezPorEtapa(): void
    {
        [, $store] = $this->seedTwoCompanies();
        $tenantId = (int) $store['tenant']['id'];
        $sub = SubscriptionService::forTenant($tenantId);
        (new SubscriptionRepository())->update((int) $sub['id'], ['status' => 'teste', 'trial_ends_at' => SubscriptionService::addDays(date('Y-m-d'), 3)]);
        SubscriptionService::forget();
        Mailer::$sent = [];

        $sent = SubscriptionService::sendNotices();
        self::assertSame(1, $sent); // um administrador ativo na loja
        self::assertStringContainsString('termina em 3 dias', Mailer::$sent[0]['subject']);
        self::assertSame(0, SubscriptionService::sendNotices());
    }

    public function testSomenteLeituraBloqueiaAlteracoesMasNaoTitularesEIncidentes(): void
    {
        [, $store] = $this->seedTwoCompanies();
        $tenantId = (int) $store['tenant']['id'];
        $sub = SubscriptionService::forTenant($tenantId);
        (new SubscriptionRepository())->update((int) $sub['id'], ['status' => 'teste', 'trial_ends_at' => SubscriptionService::addDays(date('Y-m-d'), -1)]);
        SubscriptionService::forget();
        Auth::actAs($store['users']['admin'], $store['tenant']);

        self::assertTrue(SubscriptionService::isReadOnly());
        $post = static fn (string $path): Request => new Request('POST', $path, [], [], [], []);
        $blocked = \Isis\Core\Middleware::handle('billing', $post('/riscos'));
        self::assertNotNull($blocked);
        self::assertNull(\Isis\Core\Middleware::handle('billing', $post('/titulares/3/resposta')));
        self::assertNull(\Isis\Core\Middleware::handle('billing', $post('/incidentes')));
        self::assertNull(\Isis\Core\Middleware::handle('billing', new Request('GET', '/riscos', [], [], [], [])));
    }

    public function testPainelDaPlataformaExigeLoginProprio(): void
    {
        [$clinic] = $this->seedTwoCompanies();
        Auth::actAs($clinic['users']['admin'], $clinic['tenant']);
        PlatformAuth::actAs(null);
        $response = \Isis\Core\Middleware::handle('platform', new Request('GET', '/plataforma', [], [], [], []));
        self::assertNotNull($response, 'Administrador de empresa não entra no painel da plataforma.');

        (new PlatformRepository())->saveAdmin('Equipe AiCode', 'Equipe@AiCode.example', Auth::hashPassword('Plataforma@2026x'));
        self::assertSame('invalid', PlatformAuth::attempt('equipe@aicode.example', 'errada', '10.0.0.1')['error']);
        $ok = PlatformAuth::attempt('equipe@aicode.example', 'Plataforma@2026x', '10.0.0.1');
        self::assertNull($ok['error']);
        PlatformAuth::actAs($ok['admin']);
        self::assertNull(\Isis\Core\Middleware::handle('platform', new Request('GET', '/plataforma', [], [], [], [])));
        PlatformAuth::actAs(null);
    }

    public function testValoresEmReais(): void
    {
        self::assertSame(199000, PlatformController::parseMoney('1.990,00'));
        self::assertSame(54900, PlatformController::parseMoney('R$ 549'));
        self::assertSame(54990, PlatformController::parseMoney('549.9'));
        self::assertNull(PlatformController::parseMoney('abc'));
        self::assertNull(PlatformController::parseMoney(''));
        self::assertSame('R$ 1.990,00', money(199000));
        self::assertSame('Sob consulta', money(null));
    }

    private function setPlan(int $tenantId, string $plan): void
    {
        $sub = SubscriptionService::forTenant($tenantId);
        (new SubscriptionRepository())->update((int) $sub['id'], ['plan_code' => $plan]);
        SubscriptionService::forget();
    }
}
