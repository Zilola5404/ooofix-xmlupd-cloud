<?php

declare(strict_types=1);

/**
 * Точка инициализации приложения (подключается из public/*.php).
 */
define('OOOFIX_CLOUD_ROOT', __DIR__);

require_once OOOFIX_CLOUD_ROOT . '/vendor/autoload.php';

use Ooofix\XmlupdCloud\App\AppConfig;
use Ooofix\XmlupdCloud\Storage\Database;

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Требуется PHP 8.1+. Текущая версия: ' . PHP_VERSION . "\n";
    echo 'На Beget: Панель → Сайты → up-fix.ru → PHP → выберите 8.1 или 8.2 для каталога market/ooofix-xmlupd-cloud';
    exit(1);
}

try {
    AppConfig::load(OOOFIX_CLOUD_ROOT . '/config/app.php');
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Ошибка конфигурации: ' . $e->getMessage();
    exit(1);
}

if (AppConfig::isDebug()) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

Database::init(AppConfig::database());
