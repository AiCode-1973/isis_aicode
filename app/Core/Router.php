<?php

declare(strict_types=1);

namespace Isis\Core;

/**
 * Roteador com parâmetros nomeados: /usuarios/{id} (somente dígitos) e /convite/{token}.
 */
final class Router
{
    /** @var list<array{method: string, path: string, regex: string, handler: array{0: class-string, 1: string}, middleware: list<string>}> */
    private array $routes = [];

    /** Padrões aceitos por nome de parâmetro; demais aceitam qualquer segmento. */
    private const array PARAM_PATTERNS = [
        'id' => '\d+',
        'token' => '[A-Za-z0-9_-]{20,128}',
        'slug' => '[a-z0-9-]{2,80}',
        'type' => '[a-z]{2,20}',
    ];

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $regex = preg_replace_callback(
            '/\{([a-z_]+)\}/',
            fn (array $m): string => '(?P<' . $m[1] . '>' . (self::PARAM_PATTERNS[$m[1]] ?? '[^/]+') . ')',
            $path,
        );
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    /** @return array{route: array, params: array<string, string>}|null */
    public function match(string $method, string $path): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method || !preg_match($route['regex'], $path, $m)) {
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return ['route' => $route, 'params' => $params];
        }
        return null;
    }

    public function dispatch(Request $request): Response
    {
        $match = $this->match($request->method, $request->path);
        if ($match === null) {
            throw new HttpException(404);
        }

        if ($request->isPost() && !Csrf::validate($request->input('_token'))) {
            throw new HttpException(419);
        }

        foreach ($match['route']['middleware'] as $name) {
            $response = Middleware::handle($name, $request);
            if ($response !== null) {
                return $response;
            }
        }

        [$class, $method] = $match['route']['handler'];
        $args = [];
        foreach ($match['params'] as $name => $value) {
            $args[] = $name === 'id' ? (int) $value : $value;
        }
        return (new $class())->{$method}($request, ...$args);
    }
}
