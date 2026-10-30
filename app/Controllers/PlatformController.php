<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Auth\PlatformAuth;
use Isis\Core\Database;
use Isis\Core\HttpException;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Session;
use Isis\Core\Validator;
use Isis\Repositories\PlanRepository;
use Isis\Repositories\PlatformRepository;
use Isis\Repositories\SubscriptionRepository;
use Isis\Services\SubscriptionService;

/**
 * Painel da plataforma (equipe AiCode): empresas, assinaturas, cobranças manuais, planos e preços.
 */
final class PlatformController extends Controller
{
    public function showLogin(Request $request): Response
    {
        if (PlatformAuth::check()) {
            return $this->redirect('/plataforma');
        }
        return $this->view('platform/login', ['title' => 'Painel da plataforma'], 'guest');
    }

    public function login(Request $request): Response
    {
        $email = trim((string) $request->input('email'));
        $result = PlatformAuth::attempt($email, (string) $request->input('password'), $request->ip());
        if ($result['error'] !== null) {
            Session::flash('_old', ['email' => $email]);
            return $this->failure('/plataforma/entrar', $result['error'] === 'throttled'
                ? 'Muitas tentativas sem sucesso. Aguarde 15 minutos.'
                : 'E-mail ou senha incorretos.');
        }
        PlatformAuth::login($result['admin']);
        $intended = Session::get('platform_intended');
        Session::forget('platform_intended');
        return $this->redirect(is_string($intended) && str_starts_with($intended, '/plataforma') ? $intended : '/plataforma');
    }

    public function logout(Request $request): Response
    {
        PlatformAuth::logout();
        return $this->success('/plataforma/entrar', 'Você saiu do painel da plataforma.');
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('situacao', '');
        $rows = [];
        $totals = ['mrr' => 0, 'ativa' => 0, 'teste' => 0, 'atencao' => 0, 'pedidos' => 0];
        foreach ((new SubscriptionRepository())->listForPlatform($search) as $row) {
            $row['state'] = $row['id'] !== null ? SubscriptionService::state($row) : null;
            $status = $row['state']['status'] ?? 'sem';
            if (in_array($status, ['ativa', 'em_atraso'], true)) {
                $totals['ativa']++;
                $price = SubscriptionService::price($row) ?? 0;
                $totals['mrr'] += $row['billing_cycle'] === 'anual' ? intdiv($price, 12) : $price;
            }
            $totals['teste'] += $status === 'teste' ? 1 : 0;
            $totals['atencao'] += in_array($status, ['em_atraso', 'vencida', 'teste_encerrado'], true) ? 1 : 0;
            $totals['pedidos'] += $row['requested_at'] !== null ? 1 : 0;
            if ($statusFilter === '' || $statusFilter === $status || ($statusFilter === 'pedido' && $row['requested_at'] !== null)) {
                $rows[] = $row;
            }
        }
        return $this->view('platform/index', [
            'title' => 'Empresas',
            'rows' => $rows,
            'totals' => $totals,
            'filters' => ['q' => $search, 'situacao' => $statusFilter],
            'payments' => (new SubscriptionRepository())->recentPayments(8),
        ], 'platform');
    }

    public function tenant(Request $request, int $id): Response
    {
        $tenant = $this->tenantOrFail($id);
        $subscription = SubscriptionService::forTenant($id);
        [$start, $end] = SubscriptionService::nextPeriod($subscription);
        return $this->view('platform/tenant', [
            'title' => $tenant['trade_name'] ?: $tenant['legal_name'],
            't' => $tenant,
            's' => $subscription,
            'state' => SubscriptionService::state($subscription),
            'price' => SubscriptionService::price($subscription),
            'usage' => SubscriptionService::usage($id),
            'invoices' => (new SubscriptionRepository())->invoices($id),
            'plans' => (new PlanRepository())->options(),
            'nextPeriod' => [$start, $end],
            'admins' => Database::fetchAll("SELECT name, email, last_login_at FROM users WHERE tenant_id = ? AND role = 'admin' AND status = 'active' ORDER BY name", [$id]),
        ], 'platform');
    }

    public function updateSubscription(Request $request, int $id): Response
    {
        $this->tenantOrFail($id);
        $v = Validator::make($request->all(), [
            'plan_code' => 'required|in:' . implode(',', array_keys((new PlanRepository())->options())),
            'billing_cycle' => 'required|in:' . implode(',', array_keys(SubscriptionService::CYCLES)),
            'status' => 'required|in:' . implode(',', array_keys(SubscriptionService::STORED_STATUSES)),
            'trial_ends_at' => 'nullable|date',
            'current_period_end' => 'nullable|date',
            'custom_price' => 'nullable|max:20',
            'notes' => 'nullable|max:500',
        ], ['plan_code' => 'o plano', 'billing_cycle' => 'o ciclo', 'status' => 'a situação', 'trial_ends_at' => 'o fim do teste',
            'current_period_end' => 'o fim do período pago', 'custom_price' => 'o preço combinado', 'notes' => 'as observações']);
        $data = $v->validated();
        $custom = self::parseMoney($data['custom_price']);
        if ($data['custom_price'] !== null && $custom === null) {
            $v->addError('custom_price', 'Informe um valor como 549,00.');
        }
        if ($data['status'] === 'teste' && $data['trial_ends_at'] === null) {
            $v->addError('trial_ends_at', 'Informe até quando vai o teste.');
        }
        if ($v->fails()) {
            return $this->backWithErrors("/plataforma/empresas/{$id}#assinatura", $v->errors(), $request->all());
        }
        SubscriptionService::updateByPlatform($id, [
            'plan_code' => $data['plan_code'],
            'billing_cycle' => $data['billing_cycle'],
            'status' => $data['status'],
            'trial_ends_at' => $data['trial_ends_at'],
            'current_period_end' => $data['current_period_end'],
            'custom_price_cents' => $custom,
            'notes' => $data['notes'],
        ], $this->actor());
        return $this->success("/plataforma/empresas/{$id}", 'Assinatura atualizada.');
    }

    public function createInvoice(Request $request, int $id): Response
    {
        $this->tenantOrFail($id);
        $v = Validator::make($request->all(), [
            'amount' => 'required|max:20',
            'due_date' => 'required|date',
            'description' => 'nullable|max:200',
            'notes' => 'nullable|max:500',
            'paid' => 'nullable|in:1',
            'payment_method' => 'nullable|in:' . implode(',', array_keys(SubscriptionService::PAYMENT_METHODS)),
            'payment_reference' => 'nullable|max:120',
        ], ['amount' => 'o valor', 'due_date' => 'o vencimento', 'description' => 'a descrição', 'notes' => 'as observações',
            'payment_method' => 'a forma de pagamento', 'payment_reference' => 'a referência']);
        $data = $v->validated();
        $amount = self::parseMoney($data['amount']);
        if ($amount === null || $amount <= 0) {
            $v->addError('amount', 'Informe um valor como 549,00.');
        }
        if ($data['paid'] === '1' && $data['payment_method'] === null) {
            $v->addError('payment_method', 'Informe a forma de pagamento.');
        }
        if ($v->fails()) {
            return $this->backWithErrors("/plataforma/empresas/{$id}#cobranca", $v->errors(), $request->all());
        }
        $invoiceId = SubscriptionService::createInvoice($id, [
            'amount_cents' => $amount, 'due_date' => $data['due_date'], 'description' => $data['description'], 'notes' => $data['notes'],
        ], $this->actor());
        if ($data['paid'] === '1') {
            SubscriptionService::markPaid($id, $invoiceId, [
                'paid_at' => date('Y-m-d H:i:s'), 'payment_method' => $data['payment_method'], 'payment_reference' => $data['payment_reference'],
            ], $this->actor());
            return $this->success("/plataforma/empresas/{$id}", 'Cobrança lançada e paga. O período da assinatura foi estendido.');
        }
        return $this->success("/plataforma/empresas/{$id}", 'Cobrança lançada em aberto. Registre o pagamento quando ele cair.');
    }

    public function payInvoice(Request $request, int $id, int $item): Response
    {
        $this->tenantOrFail($id);
        $v = Validator::make($request->all(), [
            'paid_on' => 'required|date',
            'payment_method' => 'required|in:' . implode(',', array_keys(SubscriptionService::PAYMENT_METHODS)),
            'payment_reference' => 'nullable|max:120',
        ], ['paid_on' => 'a data do pagamento', 'payment_method' => 'a forma de pagamento', 'payment_reference' => 'a referência']);
        if ($v->fails()) {
            return $this->backWithErrors("/plataforma/empresas/{$id}#faturas", $v->errors(), $request->all());
        }
        $data = $v->validated();
        try {
            SubscriptionService::markPaid($id, $item, [
                'paid_at' => $data['paid_on'] . ' ' . date('H:i:s'), 'payment_method' => $data['payment_method'], 'payment_reference' => $data['payment_reference'],
            ], $this->actor());
        } catch (\DomainException $e) {
            return $this->failure("/plataforma/empresas/{$id}", $e->getMessage());
        }
        return $this->success("/plataforma/empresas/{$id}", 'Pagamento registrado. O período da assinatura foi estendido.');
    }

    public function cancelInvoice(Request $request, int $id, int $item): Response
    {
        $this->tenantOrFail($id);
        try {
            SubscriptionService::cancelInvoice($id, $item, $this->actor());
        } catch (\DomainException $e) {
            return $this->failure("/plataforma/empresas/{$id}", $e->getMessage());
        }
        return $this->success("/plataforma/empresas/{$id}", 'Cobrança cancelada.');
    }

    public function plans(Request $request): Response
    {
        return $this->view('platform/plans', ['title' => 'Planos e preços', 'plans' => (new PlanRepository())->all()], 'platform');
    }

    public function updatePlan(Request $request, string $slug): Response
    {
        $repo = new PlanRepository();
        $plan = $repo->find($slug) ?? throw new HttpException(404);
        $v = Validator::make($request->all(), [
            'name' => 'required|min:2|max:80',
            'description' => 'nullable|max:300',
            'price_monthly' => 'nullable|max:20',
            'price_yearly' => 'nullable|max:20',
            'max_users' => 'nullable|int',
            'max_vendors' => 'nullable|int',
            'max_trainings' => 'nullable|int',
            'highlights' => 'nullable|max:2000',
            'contact_only' => 'nullable|in:1',
            'is_public' => 'nullable|in:1',
        ], ['name' => 'o nome', 'description' => 'a descrição', 'price_monthly' => 'o preço mensal', 'price_yearly' => 'o preço anual',
            'max_users' => 'o limite de usuários', 'max_vendors' => 'o limite de fornecedores', 'max_trainings' => 'o limite de treinamentos',
            'highlights' => 'os destaques']);
        $data = $v->validated();
        $monthly = self::parseMoney($data['price_monthly']);
        $yearly = self::parseMoney($data['price_yearly']);
        foreach (['price_monthly' => $monthly, 'price_yearly' => $yearly] as $field => $value) {
            if ($data[$field] !== null && $value === null) {
                $v->addError($field, 'Informe um valor como 199,00.');
            }
        }
        $contactOnly = $data['contact_only'] === '1';
        if (!$contactOnly && ($monthly === null || $yearly === null)) {
            $v->addError('price_monthly', 'Informe os preços mensal e anual ou marque "sob consulta".');
        }
        if ($v->fails()) {
            return $this->backWithErrors('/plataforma/planos#plano-' . $slug, $v->errors(), $request->all());
        }
        $changes = [
            'name' => $data['name'],
            'description' => $data['description'],
            'price_monthly_cents' => $monthly,
            'price_yearly_cents' => $yearly,
            'max_users' => $data['max_users'] !== null ? (int) $data['max_users'] : null,
            'max_vendors' => $data['max_vendors'] !== null ? (int) $data['max_vendors'] : null,
            'max_trainings' => $data['max_trainings'] !== null ? (int) $data['max_trainings'] : null,
            'contact_only' => $contactOnly ? 1 : 0,
            'is_public' => $data['is_public'] === '1' ? 1 : 0,
            'highlights' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $data['highlights']) ?: []))),
        ];
        $repo->update($slug, $changes);
        $before = array_intersect_key($plan, $changes);
        AuditLogger::log('platform.plan_updated', 'plan', null, $before, $changes + ['codigo' => $slug],
            ['tenant_id' => null, 'user_id' => null, 'user_name' => 'AiCode · ' . $this->actor()]);
        return $this->success('/plataforma/planos', "Plano {$data['name']} atualizado. Os novos valores valem para as próximas cobranças; assinaturas com preço combinado não mudam.");
    }

    public function settings(Request $request): Response
    {
        $repo = new PlatformRepository();
        return $this->view('platform/settings', [
            'title' => 'Configurações da plataforma',
            'instructions' => $repo->setting('payment_instructions', ''),
            'billingEmail' => $repo->setting('billing_email', ''),
            'admins' => $repo->activeAdmins(),
        ], 'platform');
    }

    public function saveSettings(Request $request): Response
    {
        $v = Validator::make($request->all(), [
            'payment_instructions' => 'nullable|max:2000',
            'billing_email' => 'nullable|email',
        ], ['payment_instructions' => 'as instruções de pagamento', 'billing_email' => 'o e-mail de cobrança']);
        if ($v->fails()) {
            return $this->backWithErrors('/plataforma/configuracoes', $v->errors(), $request->all());
        }
        $data = $v->validated();
        $repo = new PlatformRepository();
        $repo->saveSetting('payment_instructions', $data['payment_instructions']);
        $repo->saveSetting('billing_email', $data['billing_email']);
        return $this->success('/plataforma/configuracoes', 'Configurações salvas.');
    }

    public function password(Request $request): Response
    {
        $admin = PlatformAuth::admin();
        $v = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => 'required|password',
            'password_confirmation' => 'required|same:password',
        ], ['current_password' => 'a senha atual', 'password' => 'a nova senha', 'password_confirmation' => 'a confirmação']);
        if (!$v->fails() && !password_verify((string) $request->input('current_password'), (string) $admin['password_hash'])) {
            $v->addError('current_password', 'Senha atual incorreta.');
        }
        if ($v->fails()) {
            return $this->backWithErrors('/plataforma/configuracoes#senha', $v->errors());
        }
        (new PlatformRepository())->updatePassword((int) $admin['id'], Auth::hashPassword((string) $request->input('password')));
        return $this->success('/plataforma/configuracoes', 'Senha alterada.');
    }

    private function tenantOrFail(int $id): array
    {
        return Database::fetch('SELECT t.*, s.name AS segment_name FROM tenants t JOIN segments s ON s.code = t.segment_code WHERE t.id = ?', [$id])
            ?? throw new HttpException(404);
    }

    private function actor(): string
    {
        return (string) (PlatformAuth::admin()['name'] ?? 'plataforma');
    }

    /** "1.990,00", "1990", "549.9" → centavos; vazio ou inválido → null. */
    public static function parseMoney(?string $value): ?int
    {
        $value = trim(str_replace(['R$', ' '], '', (string) $value));
        if ($value === '') {
            return null;
        }
        if (str_contains($value, ',')) {
            $value = str_replace(',', '.', str_replace('.', '', $value));
        }
        if (!preg_match('/^\d{1,9}(\.\d{1,2})?$/', $value)) {
            return null;
        }
        return (int) round((float) $value * 100);
    }
}
