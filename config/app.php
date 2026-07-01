<?php

/**
 * Настройки приложения.
 * Секреты БД и OAuth — в .env (см. .env.example).
 */
return [
    'client_id'     => getenv('B24_CLIENT_ID') ?: '',
    'client_secret' => getenv('B24_CLIENT_SECRET') ?: '',
    'app_url'       => rtrim((string)(getenv('APP_URL') ?: 'https://your-domain.ru'), '/'),
    'app_title'     => (string)(getenv('APP_TITLE') ?: 'Генерация XML (УПД)'),

    'database' => [
        'host'    => getenv('DB_HOST') ?: 'localhost',
        'port'    => (int)(getenv('DB_PORT') ?: 3306),
        'name'    => getenv('DB_NAME') ?: 'btops_app',
        'user'    => getenv('DB_USER') ?: 'btops_app',
        'pass'    => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],

    'debug' => filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN),
];
