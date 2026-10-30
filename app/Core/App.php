<?php

declare(strict_types=1);

namespace Isis\Core;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;

/**
 * Ciclo da requisição web: cabeçalhos de segurança, sessão, autenticação, rota e tratamento de erros.
 */
final class App
{
    public static function run(): void
    {
        $request = Request::fromGlobals();
        self::securityHeaders($request);

        try {
            $secure = (bool) Config::get('session.secure', true) || $request->isHttps();
            Session::start($secure);
            AuditLogger::setRequestInfo($request->ip(), $request->userAgent());

            $router = new Router();
            (require APP_ROOT . '/config/routes.php')($router);

            // Só consulta o banco quando há sessão autenticada; o instalador roda antes do banco existir.
            if ($request->path !== '/instalar') {
                $reason = Auth::boot();
                if ($reason !== null) {
                    Session::flash('info', $reason === 'expired'
                        ? 'Sua sessão expirou por inatividade. Entre novamente.'
                        : 'Sua sessão foi encerrada. Entre novamente.');
                }
            }
            $response = $router->dispatch($request);
        } catch (HttpException $e) {
            $response = self::errorPage($e->status, $e->getMessage());
        } catch (\Throwable $e) {
            Logger::error($e->getMessage(), ['arquivo' => $e->getFile() . ':' . $e->getLine(), 'rota' => $request->method . ' ' . $request->path]);
            $message = Config::get('app.debug') ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : 'Ocorreu um erro inesperado. A equipe técnica foi registrada no log.';
            $response = self::errorPage(500, $message);
        }

        $response->send();
    }

    private static function securityHeaders(Request $request): void
    {
        header_remove('X-Powered-By');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'; object-src 'none'");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cache-Control: no-store, max-age=0');
        if ($request->isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    private static function errorPage(int $status, string $message): Response
    {
        $titles = [403 => 'Acesso negado', 404 => 'Não encontrado', 419 => 'Sessão expirada', 429 => 'Muitas tentativas', 500 => 'Erro interno'];
        try {
            $html = View::render('errors/error', [
                'title' => $titles[$status] ?? 'Erro',
                'status' => $status,
                'message' => $message,
            ], Auth::check() ? 'app' : 'guest');
        } catch (\Throwable) {
            $html = '<!doctype html><meta charset="utf-8"><title>Erro</title><p>' . htmlspecialchars($message) . '</p>';
        }
        return Response::html($html, $status);
    }
}
