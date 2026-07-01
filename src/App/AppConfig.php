<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\App;

/** Загрузка config/app.php и опционально .env */
final class AppConfig
{
    /** @var array<string, mixed> */
    private static array $config = [];

    public static function load(string $path): void
    {
        $root = dirname($path, 2);
        self::loadDotEnv($root . '/.env');

        if (!is_file($path)) {
            $path = $root . '/config/app.example.php';
        }

        if (!is_file($path)) {
            throw new \RuntimeException('Файл конфигурации не найден: config/app.php');
        }

        $data = require $path;
        if (!is_array($data)) {
            throw new \RuntimeException('config/app.php должен возвращать массив');
        }

        self::$config = $data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$config[$key] ?? $default;
    }

    public static function clientId(): string
    {
        return (string)self::get('client_id', '');
    }

    public static function clientSecret(): string
    {
        return (string)self::get('client_secret', '');
    }

    public static function appUrl(): string
    {
        return rtrim((string)self::get('app_url', ''), '/');
    }

    public static function appTitle(): string
    {
        return rtrim((string)self::get('app_title', AppTitle::DEFAULT), '') ?: AppTitle::DEFAULT;
    }

    /** @return array<string, mixed> */
    public static function database(): array
    {
        $db = self::get('database', []);

        return is_array($db) ? $db : [];
    }

    public static function isDebug(): bool
    {
        return (bool)self::get('debug', false);
    }

    private static function loadDotEnv(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");

            if ($name === '' || getenv($name) !== false) {
                continue;
            }

            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
}
