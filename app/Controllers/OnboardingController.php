<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Core\Database;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Validator;
use Isis\Repositories\DepartmentRepository;
use Isis\Repositories\SegmentRepository;
use Isis\Repositories\TenantRepository;

/**
 * Assistente de configuração inicial: segmento, setores sugeridos e encarregado.
 */
final class OnboardingController extends Controller
{
    public function show(Request $request): Response
    {
        $tenant = (new TenantRepository())->current();
        $segmentCode = $request->query('segmento');
        $segments = SegmentRepository::options();
        if ($segmentCode === null || !isset($segments[$segmentCode])) {
            $segmentCode = $tenant['segment_code'];
        }
        return $this->view('onboarding/index', [
            'title' => 'Configuração inicial',
            'tenant' => $tenant,
            'segments' => $segments,
            'segmentCode' => $segmentCode,
            'template' => SegmentRepository::template($segmentCode),
            'existing' => array_values((new DepartmentRepository())->options()),
            'user' => Auth::user(),
        ], 'onboarding');
    }

    public function store(Request $request): Response
    {
        $segments = SegmentRepository::options();
        $v = Validator::make($request->all(), [
            'segment_code' => 'required|in:' . implode(',', array_keys($segments)),
            'dpo_name' => 'required|min:3|max:150',
            'dpo_email' => 'required|email',
            'privacy_email' => 'nullable|email',
            'other_departments' => 'nullable|max:2000',
            'acknowledge' => 'accepted',
        ], [
            'segment_code' => 'o segmento',
            'dpo_name' => 'o nome do encarregado',
            'dpo_email' => 'o e-mail do encarregado',
            'privacy_email' => 'o e-mail do canal de privacidade',
            'other_departments' => 'outros setores',
            'acknowledge' => 'a ciência sobre o papel do ISIS',
        ]);

        $names = array_map('trim', $request->inputArray('departments'));
        foreach (preg_split('/\R/', (string) $request->input('other_departments', '')) ?: [] as $line) {
            $names[] = trim($line);
        }
        $names = array_values(array_unique(array_filter($names, static fn (string $n): bool => $n !== '' && mb_strlen($n) <= 120)));
        if ($names === [] && (new DepartmentRepository())->total() === 0) {
            $v->addError('departments', 'Selecione ou informe pelo menos um setor.');
        }
        if ($v->fails()) {
            return $this->backWithErrors('/configuracao-inicial', $v->errors(), $request->all());
        }

        $data = $v->validated();
        $tenants = new TenantRepository();
        $before = $tenants->current();
        Database::transaction(function () use ($data, $names, $tenants, $before): void {
            $departments = new DepartmentRepository();
            $created = 0;
            foreach ($names as $name) {
                if (!$departments->nameExists($name)) {
                    $departments->create($name);
                    $created++;
                }
            }
            $changes = [
                'segment_code' => $data['segment_code'],
                'dpo_name' => $data['dpo_name'],
                'dpo_email' => $data['dpo_email'],
                'privacy_email' => $data['privacy_email'] ?? $data['dpo_email'],
                'onboarding_completed_at' => date('Y-m-d H:i:s'),
            ];
            $tenants->updateCurrent($changes);
            $catalog = \Isis\Services\CatalogService::importSuggestions((string) $data['segment_code']);
            AuditLogger::log('onboarding.completed', 'tenant', (int) $before['id'], $before, $changes + [
                'setores_criados' => $created,
                'categorias_dados_sugeridas' => $catalog['data'],
                'categorias_titulares_sugeridas' => $catalog['subjects'],
            ]);
        });

        return $this->success('/painel', 'Configuração concluída! Próximo passo: convide sua equipe em Usuários.');
    }
}
