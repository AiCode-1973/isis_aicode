<?php

declare(strict_types=1);

/*
 * Testes: unidade rodam sem banco. Integração usa um banco PRÓPRIO de testes, que é apagado e recriado:
 *   ISIS_TEST_DB_HOST, ISIS_TEST_DB_PORT, ISIS_TEST_DB_DATABASE (padrão isis_test), ISIS_TEST_DB_USERNAME, ISIS_TEST_DB_PASSWORD
 * Sem conexão, os testes de integração são marcados como ignorados.
 */

require dirname(__DIR__) . '/app/bootstrap.php';
require APP_ROOT . '/database/seeds/DemoSeeder.php';

use Isis\Core\Config;
use Isis\Core\Crypto;

Config::set('app.key', 'base64:' . base64_encode(str_repeat('k', 32)));
Config::set('app.url', 'https://isis.test');
Config::set('mail.driver', 'array');
Crypto::setKey(null);

$env = static fn (string $key, string $default): string => getenv($key) !== false && getenv($key) !== '' ? (string) getenv($key) : $default;
Config::set('db', [
    'host' => $env('ISIS_TEST_DB_HOST', (string) Config::get('db.host', '127.0.0.1')),
    'port' => (int) $env('ISIS_TEST_DB_PORT', (string) Config::get('db.port', 3306)),
    'database' => $env('ISIS_TEST_DB_DATABASE', 'isis_test'),
    'username' => $env('ISIS_TEST_DB_USERNAME', (string) Config::get('db.username', 'root')),
    'password' => $env('ISIS_TEST_DB_PASSWORD', (string) Config::get('db.password', '')),
]);
