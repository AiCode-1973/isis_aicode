<?php

declare(strict_types=1);

namespace Isis\Core;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Auth\PlatformAuth;
use Isis\Auth\Role;
use Isis\Services\MfaService;
use Isis\Services\SubscriptionService;

/**
 * Filtros de rota: auth, guest, onboarded, billing, perm:<permissão> e platform (equipe AiCode).
 */
final class Middleware
{
    public static function handle(string $name, Request $request): ?Response
    {
        [$type, $arg] = array_pad(explode(':', $name, 2), 2, null);

        return match ($type) {
            'auth' => Auth::check() ? self::mfaSetup($request) : self::toLogin($request),
            'guest' => Auth::check() ? Response::redirect('/painel') : null,
            'onboarded' => self::onboarded(),
            'billing' => self::billing($request),
            'perm' => self::permission((string) $arg, $request),
            'platform' => PlatformAuth::check() ? null : self::toPlatformLogin($request),
            default => throw new \LogicException("Middleware desconhecido: {$name}"),
        };
    }

    private static function toLogin(Request $request): Response
    {
        if ($request->method === 'GET') {
            Session::set('intended', $request->path);
        }
        Session::flash('info', 'Entre com sua conta para continuar.');
        return Response::redirect('/entrar');
    }

    /** Política da empresa exige verificação em duas etapas: até ativar, só a tela de ativação (e sair) funcionam. */
    private static function mfaSetup(Request $request): ?Response
    {
        $user = Auth::user();
        if ($user === null || !MfaService::setupPending($user, Auth::tenant())
            || str_starts_with($request->path, '/perfil/dois-fatores') || $request->path === '/sair') {
            return null;
        }
        Session::flash('info', 'Sua empresa exige a verificação em duas etapas. Ative para continuar usando o ÍSIS.');
        return Response::redirect('/perfil/dois-fatores');
    }

    /** Enquanto a configuração inicial não termina, o administrador é levado ao assistente. */
    private static function onboarded(): ?Response
    {
        $tenant = Auth::tenant();
        if ($tenant !== null && $tenant['onboarding_completed_at'] === null && Auth::role() === Role::Admin) {
            return Response::redirect('/configuracao-inicial');
        }
        return null;
    }

    /**
     * Assinatura vencida, suspensa ou teste encerrado: a empresa só consulta e exporta.
     * Atendimento a titulares e incidentes continua liberado (obrigações legais).
     */
    private static function billing(Request $request): ?Response
    {
        if ($request->method !== 'POST' || SubscriptionService::writableInReadOnly($request->path) || !SubscriptionService::isReadOnly()) {
            return null;
        }
        Session::flash('error', Auth::role() === Role::Admin
            ? 'A assinatura está em modo somente leitura. Regularize em Plano e assinatura para voltar a incluir e alterar dados.'
            : 'A assinatura da empresa está em modo somente leitura. Fale com o administrador do ÍSIS na sua empresa.');
        return Response::redirect(Auth::role() === Role::Admin ? '/plano' : '/painel');
    }

    private static function toPlatformLogin(Request $request): Response
    {
        if ($request->method === 'GET') {
            Session::set('platform_intended', $request->path);
        }
        return Response::redirect('/plataforma/entrar');
    }

    /** perm:a|b libera quem tiver qualquer uma das permissões. */
    private static function permission(string $permission, Request $request): ?Response
    {
        foreach (explode('|', $permission) as $option) {
            if (Auth::can($option)) {
                return null;
            }
        }
        AuditLogger::log('access.denied', 'route', null, null, ['rota' => $request->method . ' ' . $request->path, 'permissao' => $permission]);
        throw new HttpException(403);
    }
}
