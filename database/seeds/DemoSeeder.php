<?php

declare(strict_types=1);

namespace Isis\Database\Seeds;

require_once __DIR__ . '/DemoComplianceSeeder.php';
require_once __DIR__ . '/DemoPrivacySeeder.php';
require_once __DIR__ . '/DemoProgramSeeder.php';

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Auth\TenantContext;
use Isis\Core\Crypto;
use Isis\Core\Database;
use Isis\Repositories\DepartmentRepository;
use Isis\Repositories\InvitationRepository;
use Isis\Repositories\TenantRepository;
use Isis\Repositories\UserRepository;
use Isis\Repositories\SubscriptionRepository;
use Isis\Services\SubscriptionService;

/**
 * Dados demonstrativos FICTÍCIOS: duas empresas de segmentos diferentes, com um usuário de cada perfil.
 * Não use em produção com clientes reais.
 */
final class DemoSeeder
{
    public const string PASSWORD = 'Demo@Isis2026';

    /** @return list<string> Resumo do que foi criado */
    public static function run(): array
    {
        $companies = [
            [
                'legal_name' => 'Clínica Vida Plena Serviços Médicos Ltda.',
                'trade_name' => 'Clínica Vida Plena',
                'cnpj_base' => '123456780001',
                'segment_code' => 'saude',
                'domain' => 'vidaplena.example',
                'departments' => ['Recepção e Atendimento', 'Corpo Clínico', 'Enfermagem', 'Faturamento e Convênios', 'Recursos Humanos', 'Tecnologia da Informação'],
                'people' => [
                    ['Mariana Costa', 'admin', 'Tecnologia da Informação', 'Diretora administrativa'],
                    ['Rafael Nunes', 'encarregado', null, 'Encarregado de dados'],
                    ['Beatriz Lima', 'gestor', 'Recepção e Atendimento', 'Coordenadora de atendimento'],
                    ['Carlos Souza', 'colaborador', 'Faturamento e Convênios', 'Analista de faturamento'],
                    ['Helena Prado', 'auditor', null, 'Auditora interna'],
                ],
            ],
            [
                'legal_name' => 'Bom Preço Comércio Varejista S.A.',
                'trade_name' => 'Loja Bom Preço',
                'cnpj_base' => '98765432000A',
                'segment_code' => 'comercio',
                'domain' => 'bompreco.example',
                'departments' => ['Vendas', 'Atendimento ao Cliente', 'Marketing', 'Financeiro', 'Logística'],
                'people' => [
                    ['Paulo Mendes', 'admin', 'Financeiro', 'Gerente geral'],
                    ['Juliana Rocha', 'encarregado', null, 'Encarregada de dados'],
                    ['André Martins', 'gestor', 'Marketing', 'Gerente de marketing'],
                    ['Fernanda Alves', 'colaborador', 'Atendimento ao Cliente', 'Atendente'],
                    ['Ricardo Teles', 'auditor', null, 'Auditor externo'],
                ],
            ],
        ];

        $summary = [];
        $hash = Auth::hashPassword(self::PASSWORD);

        foreach ($companies as $company) {
            $cnpj = self::cnpj($company['cnpj_base']);
            if ((new TenantRepository())->cnpjExists($cnpj)) {
                $summary[] = "{$company['trade_name']}: já existe; cadastro mantido.";
            } else {
                self::createCompany($company, $cnpj, $hash, $summary);
            }
            $tenantId = (int) Database::value('SELECT id FROM tenants WHERE cnpj = ?', [$cnpj]);
            $created = TenantContext::runAs($tenantId, static fn (): bool => DemoComplianceSeeder::run($company['segment_code']));
            $summary[] = $created
                ? "{$company['trade_name']}: inventário, riscos e planos de ação demonstrativos criados."
                : "{$company['trade_name']}: já tinha inventário; dados de conformidade mantidos.";
            $privacy = TenantContext::runAs($tenantId, static fn (): bool => DemoPrivacySeeder::run($company['segment_code']));
            $summary[] = $privacy
                ? "{$company['trade_name']}: solicitações de titulares e incidentes demonstrativos criados."
                : "{$company['trade_name']}: já tinha solicitações ou incidentes; mantidos.";
            $program = TenantContext::runAs($tenantId, static fn (): bool => DemoProgramSeeder::run($company['segment_code'], $company['trade_name']));
            $summary[] = $program
                ? "{$company['trade_name']}: fornecedores, políticas e treinamento demonstrativos criados."
                : "{$company['trade_name']}: já tinha fornecedores; mantidos.";
            $summary[] = self::billing($tenantId, $company);
        }
        return $summary;
    }

    /**
     * Assinatura demonstrativa: a clínica paga o Profissional mensal (com uma fatura paga);
     * a loja está no teste grátis do Essencial.
     */
    private static function billing(int $tenantId, array $company): string
    {
        $name = $company['trade_name'];
        if (Database::value('SELECT 1 FROM invoices WHERE tenant_id = ?', [$tenantId]) !== null) {
            return "{$name}: assinatura mantida.";
        }
        SubscriptionService::forget($tenantId);
        $subscription = SubscriptionService::forTenant($tenantId);
        $repo = new SubscriptionRepository();
        if ($company['segment_code'] !== 'saude') {
            $repo->update((int) $subscription['id'], ['plan_code' => 'essencial', 'status' => 'teste', 'trial_ends_at' => SubscriptionService::addDays(date('Y-m-d'), 9)]);
            SubscriptionService::forget($tenantId);
            return "{$name}: teste grátis do plano Essencial.";
        }
        $repo->update((int) $subscription['id'], ['plan_code' => 'profissional', 'status' => 'ativa', 'billing_cycle' => 'mensal',
            'current_period_end' => SubscriptionService::addDays(date('Y-m-d'), -1)]);
        SubscriptionService::forget($tenantId);
        $invoiceId = SubscriptionService::createInvoice($tenantId, ['amount_cents' => 54900, 'due_date' => date('Y-m-d'), 'description' => null], 'Demonstração');
        SubscriptionService::markPaid($tenantId, $invoiceId, ['paid_at' => date('Y-m-d H:i:s'), 'payment_method' => 'pix', 'payment_reference' => 'PIX-DEMO-0001'], 'Demonstração');
        return "{$name}: plano Profissional mensal ativo, com uma fatura paga.";
    }

    /** @param array<string, mixed> $company @param list<string> $summary */
    private static function createCompany(array $company, string $cnpj, string $hash, array &$summary): void
    {
        Database::transaction(function () use ($company, $cnpj, $hash, &$summary): void {
            $tenantId = (new TenantRepository())->create([
                'legal_name' => $company['legal_name'],
                'trade_name' => $company['trade_name'],
                'cnpj' => $cnpj,
                'segment_code' => $company['segment_code'],
                'dpo_name' => $company['people'][1][0],
                'dpo_email' => self::email($company['people'][1][0], $company['domain']),
                'privacy_email' => 'privacidade@' . $company['domain'],
                'onboarding_completed_at' => date('Y-m-d H:i:s'),
            ]);

            TenantContext::runAs($tenantId, function () use ($company, $hash, $tenantId, &$summary): void {
                $departments = new DepartmentRepository();
                $deptIds = [];
                foreach ($company['departments'] as $name) {
                    $deptIds[$name] = $departments->create($name);
                }

                $users = new UserRepository();
                $adminId = null;
                foreach ($company['people'] as [$name, $role, $dept, $title]) {
                    $email = self::email($name, $company['domain']);
                    $id = $users->create([
                        'name' => $name,
                        'email' => $email,
                        'password_hash' => $hash,
                        'role' => $role,
                        'department_id' => $dept !== null ? $deptIds[$dept] : null,
                        'job_title' => $title,
                        'email_verified_at' => date('Y-m-d H:i:s'),
                    ]);
                    $adminId ??= $id;
                    $summary[] = sprintf('%-28s %-12s %s', $company['trade_name'], $role, $email);
                }

                (new InvitationRepository())->create([
                    'email' => 'novo.colaborador@' . $company['domain'],
                    'name' => 'Novo Colaborador',
                    'role' => 'colaborador',
                    'department_id' => reset($deptIds),
                    'token_hash' => Crypto::hashToken(Crypto::randomToken()),
                    'invited_by' => $adminId,
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
                ]);

                $actor = ['tenant_id' => $tenantId, 'user_id' => $adminId, 'user_name' => $company['people'][0][0]];
                AuditLogger::log('tenant.registered', 'tenant', $tenantId, null, ['razao_social' => $company['legal_name'], 'segmento' => $company['segment_code']], $actor);
                AuditLogger::log('onboarding.completed', 'tenant', $tenantId, null, ['setores' => count($deptIds)], $actor);
                AuditLogger::log('user.invited', 'invitation', null, null, ['email' => 'novo.colaborador@' . $company['domain'], 'perfil' => 'colaborador'], $actor);
            });
        });
    }

    private static function email(string $name, string $domain): string
    {
        return \Isis\Support\Text::slug($name, '.') . '@' . $domain;
    }

    /** CNPJ fictício com dígitos verificadores válidos (aceita base alfanumérica). */
    public static function cnpj(string $base12): string
    {
        $values = array_map(static fn (string $c): int => ord($c) - 48, str_split(strtoupper($base12)));
        foreach ([12, 13] as $length) {
            $sum = 0;
            $weight = $length - 7;
            for ($i = 0; $i < $length; $i++) {
                $sum += $values[$i] * $weight;
                $weight = $weight === 2 ? 9 : $weight - 1;
            }
            $values[] = $sum % 11 < 2 ? 0 : 11 - $sum % 11;
        }
        return strtoupper($base12) . $values[12] . $values[13];
    }
}
