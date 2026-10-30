<?php

declare(strict_types=1);

namespace Isis\Core;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     * @param array<string, mixed> $files
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query = [],
        private readonly array $post = [],
        private readonly array $server = [],
        private readonly array $files = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = '/' . trim(rawurldecode(is_string($path) ? $path : '/'), '/');

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $_POST,
            $_SERVER,
            $_FILES,
        );
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /** Valor textual do corpo (POST). Arrays são ignorados aqui; use inputArray(). */
    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->post[$key] ?? null;
        return is_string($value) ? $value : $default;
    }

    /** @return array<int|string, string> */
    public function inputArray(string $key): array
    {
        $value = $this->post[$key] ?? [];
        return is_array($value) ? array_filter($value, 'is_string') : [];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->post;
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? null;
        return is_string($value) ? $value : $default;
    }

    /** @return array<string, mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        return is_array($file) ? $file : null;
    }

    public function ip(): string
    {
        if (Config::get('app.trust_proxy')) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $header) {
                $raw = $this->server[$header] ?? '';
                $candidate = trim(explode(',', (string) $raw)[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }
        $ip = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isHttps(): bool
    {
        if (($this->server['HTTPS'] ?? '') !== '' && ($this->server['HTTPS'] ?? '') !== 'off') {
            return true;
        }
        return Config::get('app.trust_proxy') && ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
