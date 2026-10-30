<?php

declare(strict_types=1);

/*
 * Inicialização comum (web, CLI e testes): autoload, .env, configuração e fuso horário.
 */

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.2.0');

if (is_file(APP_ROOT . '/vendor/autoload.php')) {
    require APP_ROOT . '/vendor/autoload.php';
} else {
    // Funciona sem Composer: autoload PSR-4 mínimo para o namespace Isis\.
    spl_autoload_register(static function (string $class): void {
        if (str_starts_with($class, 'Isis\\')) {
            $file = APP_ROOT . '/app/' . str_replace('\\', '/', substr($class, 5)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
    require APP_ROOT . '/app/helpers.php';
}

Isis\Core\Env::load(APP_ROOT . '/.env');
Isis\Core\Config::load(require APP_ROOT . '/config/config.php');
date_default_timezone_set((string) Isis\Core\Config::get('app.timezone', 'America/Sao_Paulo'));
mb_internal_encoding('UTF-8');
