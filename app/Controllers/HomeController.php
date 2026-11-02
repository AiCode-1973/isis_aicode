<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Auth\Auth;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Support\LegalDocs;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        if (Auth::check()) {
            return $this->redirect('/painel');
        }
        return $this->view('home/index', ['title' => 'Gestão da Privacidade e Proteção de Dados'] + $this->pricingData(), 'public');
    }

    /** Manual do sistema: dentro do layout da aplicação para quem está logado, público para os demais. */
    public function manual(Request $request): Response
    {
        return $this->view('manual/index', ['title' => 'Manual do sistema'], Auth::check() ? 'app' : 'public');
    }

    /**
     * Planos e preços (valores vêm do painel da plataforma). Para visitantes é uma seção da página
     * inicial; quem está logado vê a comparação dentro do sistema.
     */
    public function pricing(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/#planos');
        }
        return $this->view('home/pricing', ['title' => 'Planos e preços'] + $this->pricingData(), 'app');
    }

    /** @return array{plans: array, trialDays: int} */
    private function pricingData(): array
    {
        return [
            'plans' => (new \Isis\Repositories\PlanRepository())->all(true),
            'trialDays' => (int) \Isis\Core\Config::get('billing.trial_days', 14),
        ];
    }

    public function terms(Request $request): Response
    {
        return $this->view('home/terms', ['title' => 'Termos de uso', 'info' => LegalDocs::info()], 'public');
    }

    public function privacy(Request $request): Response
    {
        return $this->view('legal/privacy', ['title' => 'Aviso de privacidade', 'info' => LegalDocs::info()], 'public');
    }

    /** Acordo de tratamento de dados: a AiCode como operadora dos dados que cada empresa registra. */
    public function dpa(Request $request): Response
    {
        return $this->view('legal/dpa', ['title' => 'Acordo de tratamento de dados', 'info' => LegalDocs::info()], 'public');
    }
}
