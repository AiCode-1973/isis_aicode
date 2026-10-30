<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Core\Config;
use Isis\Core\Crypto;
use Isis\Core\HttpException;
use Isis\Core\Logger;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Services\Installer;

/**
 * Instalador web para servidores sem acesso SSH. Só funciona com INSTALL_TOKEN definido no .env
 * e é bloqueado definitivamente após a primeira instalação (storage/install.lock).
 */
final class InstallController extends Controller
{
    public function show(Request $request): Response
    {
        $this->guard();
        return $this->view('install/index', [
            'title' => 'Instalação',
            'checks' => Installer::requirements(),
            'tokenConfigured' => $this->token() !== null,
            'suggestedKey' => Config::get('app.key') ? null : Crypto::generateKey(),
        ], 'guest');
    }

    public function run(Request $request): Response
    {
        $this->guard();
        $token = $this->token();
        if ($token === null || !hash_equals($token, (string) $request->input('install_token'))) {
            return $this->failure('/instalar', 'Token de instalação incorreto.');
        }
        foreach (Installer::requirements() as $label => $check) {
            if (!$check['ok']) {
                return $this->failure('/instalar', "Requisito pendente: {$label}.");
            }
        }
        try {
            $ran = Installer::migrate();
            Installer::seedSegments();
            $summary = [];
            if ($request->input('demo') === '1') {
                require_once APP_ROOT . '/database/seeds/DemoSeeder.php';
                $summary = \Isis\Database\Seeds\DemoSeeder::run();
            }
            Installer::lock();
        } catch (\Throwable $e) {
            Logger::error('Falha na instalação', ['erro' => $e->getMessage()]);
            return $this->failure('/instalar', 'Falha na instalação: ' . $e->getMessage());
        }
        return $this->view('install/done', [
            'title' => 'Instalação concluída',
            'ran' => $ran,
            'demo' => $summary,
        ], 'guest');
    }

    private function guard(): void
    {
        if (Installer::isInstalled()) {
            throw new HttpException(404);
        }
    }

    private function token(): ?string
    {
        $token = Config::get('app.install_token');
        return (is_string($token) && strlen($token) >= 16) ? $token : null;
    }
}
