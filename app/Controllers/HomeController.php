<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Auth\Auth;
use Isis\Core\Request;
use Isis\Core\Response;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        if (Auth::check()) {
            return $this->redirect('/painel');
        }
        return $this->view('home/index', ['title' => 'Gestão da Privacidade e Proteção de Dados'], 'public');
    }

    public function terms(Request $request): Response
    {
        return $this->view('home/terms', ['title' => 'Termos de uso e aviso de privacidade'], 'public');
    }
}
