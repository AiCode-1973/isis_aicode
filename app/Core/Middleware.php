<?php

declare(strict_types=1);

namespace Isis\Core;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Auth\Role;

/**
 * Filtros de rota: auth, guest, onboarded e perm:<permissão>.
 */
final class Middleware
{
    public static function handle(string $name, Request $request): ?Response
    {
        [$type, $arg] = array_pad(explode(':', $name, 2), 2, null);

        return match ($type) {
            'auth' => Auth::check() ? null : self::toLogin($request),
            'guest' => Auth::check() ? Response::redirect('/painel') : null,
            'onboarded' => self::onboarded(),
            'perm' => self::permission((string) $arg, $request),
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

    /** Enquanto a configuração inicial não termina, o administrador é levado ao assistente. */
    private static function onboarded(): ?Response
    {
        $tenant = Auth::tenant();
        if ($tenant !== null && $tenant['onboarding_completed_at'] === null && Auth::role() === Role::Admin) {
            return Response::redirect('/configuracao-inicial');
        }
        return null;
    }

    private static function permission(string $permission, Request $request): ?Response
    {
        if (Auth::can($permission)) {
            return null;
        }
        AuditLogger::log('access.denied', 'route', null, null, ['rota' => $request->method . ' ' . $request->path, 'permissao' => $permission]);
        throw new HttpException(403);
    }
}
