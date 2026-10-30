<?php

declare(strict_types=1);

/*
 * Roteador do servidor embutido do PHP, só para desenvolvimento:
 *   php -S localhost:8090 -t public bin/dev-router.php
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (is_string($path) && $path !== '/' && is_file(__DIR__ . '/../public' . $path)) {
    return false;
}
require __DIR__ . '/../public/index.php';
