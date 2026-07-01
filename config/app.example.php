<?php

/**
 * Пример конфигурации. На сервере используйте .env + config/app.php.
 */
return [
    'client_id'     => getenv('B24_CLIENT_ID') ?: 'local.xxxxxxxx.xxxxxxxx',
    'client_secret' => getenv('B24_CLIENT_SECRET') ?: 'your_secret_here',
    'app_url'       => rtrim((string)(getenv('APP_URL') ?: 'https://your-domain.ru'), '/'),
    'app_title'     => (string)(getenv('APP_TITLE') ?: 'Генерация XML (УПД)'),

    'database' => [
        'host'    => getenv('DB_HOST') ?: 'localhost',
        'port'    => (int)(getenv('DB_PORT') ?: 3306),
        'name'    => getenv('DB_NAME') ?: 'btops_app',
        'user'    => getenv('DB_USER') ?: 'btops_app',
        'pass'    => getenv('DB_PASS') ?: 'db_password',
        'charset' => 'utf8mb4',
    ],

    'debug' => false,
];
