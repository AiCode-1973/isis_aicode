<?php

declare(strict_types=1);

namespace Isis\Core;

/**
 * Templates PHP simples em app/Views. Toda saída de dados deve passar por e().
 */
final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = 'app'): string
    {
        $content = self::capture(self::path($template), $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture(self::path('layouts/' . $layout), $data + ['content' => $content]);
    }

    /** @param array<string, mixed> $data */
    public static function partial(string $template, array $data = []): string
    {
        return self::capture(self::path($template), $data);
    }

    private static function path(string $template): string
    {
        if (!preg_match('#^[a-z0-9_/-]+$#', $template) || str_contains($template, '..')) {
            throw new \InvalidArgumentException('Template inválido.');
        }
        return APP_ROOT . '/app/Views/' . $template . '.php';
    }

    /** @param array<string, mixed> $__data */
    private static function capture(string $__file, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            require $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
