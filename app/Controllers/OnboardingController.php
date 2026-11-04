<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Auth\Auth;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Session;
use Isis\Repositories\DepartmentRepository;
use Isis\Repositories\SegmentRepository;
use Isis\Repositories\TenantRepository;
use Isis\Services\OnboardingService as Wizard;

/**
 * Assistente de configuração inicial em etapas: empresa, áreas, dados, responsáveis e plano sugerido.
 * Cada etapa é salva ao avançar; áreas, categorias e convites só são criados na conclusão.
 */
final class OnboardingController extends Controller
{
    public function show(Request $request): Response
    {
        $tenant = (new TenantRepository())->current();
        $answers = Wizard::answers($tenant);
        if ($request->query('refazer') !== null) {
            return $this->redirect('/configuracao-inicial/empresa');
        }
        if ($tenant['onboarding_completed_at'] !== null && isset($answers['responsaveis'])) {
            return $this->redirect('/configuracao-inicial/plano');
        }
        return $this->redirect('/configuracao-inicial/' . Wizard::nextStep($answers));
    }

    public function step(Request $request, string $type): Response
    {
        if (!isset(Wizard::STEPS[$type])) {
            return $this->redirect('/configuracao-inicial');
        }
        $tenant = (new TenantRepository())->current();
        $answers = Wizard::answers($tenant);
        if (!Wizard::canOpen($type, $answers)) {
            return $this->redirect('/configuracao-inicial/' . Wizard::nextStep($answers));
        }
        $segment = (string) ($answers['empresa']['segment_code'] ?? $tenant['segment_code']);

        return $this->view('onboarding/' . $type, [
            'title' => 'Configuração inicial · ' . Wizard::STEPS[$type],
            'step' => $type,
            'tenant' => $tenant,
            'answers' => $answers,
            'saved' => $answers[$type] ?? null,
            'segments' => SegmentRepository::options(),
            'segment' => $segment,
            'template' => SegmentRepository::template($segment),
            'existing' => array_values((new DepartmentRepository())->options()),
            'user' => Auth::user(),
            'plan' => $type === 'plano' ? Wizard::plan($answers) : [],
            'completed' => $tenant['onboarding_completed_at'] !== null,
            'pending' => !empty($answers['pendente']),
        ], 'onboarding');
    }

    public function save(Request $request, string $type): Response
    {
        if (!isset(Wizard::STEPS[$type])) {
            return $this->redirect('/configuracao-inicial');
        }
        $tenant = (new TenantRepository())->current();
        $answers = Wizard::answers($tenant);
        if (!Wizard::canOpen($type, $answers)) {
            return $this->redirect('/configuracao-inicial/' . Wizard::nextStep($answers));
        }
        $path = '/configuracao-inicial/' . $type;

        if ($type === 'plano') {
            if ($request->input('acknowledge') !== '1') {
                return $this->backWithErrors($path, ['acknowledge' => 'Confirme a ciência sobre o papel do ÍSIS.']);
            }
            $warnings = Wizard::complete($answers);
            if ($warnings !== []) {
                Session::flash('warning', 'Alguns convites não foram enviados: ' . implode(' ', $warnings) . ' Você pode convidar depois em Usuários.');
            }
            $first = $tenant['onboarding_completed_at'] === null;
            return $this->success($first ? '/painel' : '/configuracao-inicial/plano', $first
                ? 'Configuração concluída! O plano sugerido fica no painel, na Jornada de implantação.'
                : 'Alterações aplicadas.');
        }

        [$data, $errors] = Wizard::validate($type, $request->all(), $answers, (new DepartmentRepository())->total());
        if ($errors !== []) {
            return $this->backWithErrors($path, $errors, $request->all());
        }
        // Mudar o segmento troca as sugestões das etapas seguintes; mudar as áreas desloca os responsáveis.
        if ($type === 'empresa' && ($answers['empresa']['segment_code'] ?? null) !== $data['segment_code']) {
            unset($answers['dados']);
        }
        if ($type === 'areas' && ($answers['areas']['departments'] ?? null) !== $data['departments']) {
            unset($answers['responsaveis']);
        }
        $answers[$type] = $data;
        $answers['pendente'] = true; // há respostas ainda não aplicadas
        Wizard::save($answers);

        $next = $request->input('go') === 'voltar' ? (Wizard::previous($type) ?? $type) : Wizard::following($type);
        return $this->redirect('/configuracao-inicial/' . (Wizard::canOpen($next, $answers) ? $next : Wizard::nextStep($answers)));
    }
}
