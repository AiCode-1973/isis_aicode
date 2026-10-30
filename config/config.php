<?php

declare(strict_types=1);

use Isis\Core\Env;

return [
    'app' => [
        'name' => Env::get('APP_NAME', 'ÍSIS'),
        'tagline' => 'Gestão da Privacidade e Proteção de Dados',
        'pillars' => 'LGPD • Governança • Segurança da Informação • Conformidade',
        'env' => Env::get('APP_ENV', 'production'),
        'debug' => Env::bool('APP_DEBUG'),
        'url' => rtrim(Env::get('APP_URL', 'https://isis.aicode.dev.br'), '/'),
        'timezone' => Env::get('APP_TIMEZONE', 'America/Sao_Paulo'),
        'key' => Env::get('APP_KEY'),
        'trust_proxy' => Env::bool('TRUST_PROXY'),
        'install_token' => Env::get('INSTALL_TOKEN'),
    ],
    'db' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::int('DB_PORT', 3306),
        'database' => Env::get('DB_DATABASE', 'isis'),
        'username' => Env::get('DB_USERNAME', 'root'),
        'password' => Env::get('DB_PASSWORD', ''),
    ],
    'session' => [
        'idle_minutes' => Env::int('SESSION_IDLE_MINUTES', 30),
        'max_hours' => Env::int('SESSION_MAX_HOURS', 8),
        'secure' => Env::bool('SESSION_SECURE_COOKIE', true),
    ],
    'security' => [
        'login_max_per_account' => 5,
        'login_max_per_ip' => 20,
        'login_window_minutes' => 15,
        'reset_token_minutes' => 60,
        'verify_token_hours' => 48,
        'invite_days' => 7,
    ],
    'backup' => [
        'keep_daily' => Env::int('BACKUP_KEEP_DAILY', 7),
        'keep_weekly' => Env::int('BACKUP_KEEP_WEEKLY', 4),
    ],
    'mail' => [
        'driver' => Env::get('MAIL_DRIVER', 'log'),
        'host' => Env::get('MAIL_HOST'),
        'port' => Env::int('MAIL_PORT', 587),
        'username' => Env::get('MAIL_USERNAME'),
        'password' => Env::get('MAIL_PASSWORD'),
        'encryption' => Env::get('MAIL_ENCRYPTION', 'tls'),
        'from_address' => Env::get('MAIL_FROM_ADDRESS', 'nao-responda@isis.aicode.dev.br'),
        'from_name' => Env::get('MAIL_FROM_NAME', 'ÍSIS'),
    ],
];
