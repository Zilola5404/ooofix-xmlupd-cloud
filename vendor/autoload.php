<?php

/**
 * Минимальный PSR-4 autoloader (без Composer на сервере разработки).
 * На продакшене выполните: composer install --no-dev
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Ooofix\\XmlupdCloud\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
