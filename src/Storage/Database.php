<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Storage;

use PDO;
use PDOException;

/** PDO-подключение к MySQL (ленивое — при первом запросе) */
final class Database
{
    private static ?PDO $pdo = null;

    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /** @param array<string, mixed> $config */
    public static function init(array $config): void
    {
        self::$config = $config;
        self::$pdo = null;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::connect();
        }

        return self::$pdo;
    }

    /** Проверка подключения без выброса исключения наружу bootstrap. */
    public static function ping(): array
    {
        try {
            self::pdo()->query('SELECT 1');

            return ['ok' => true, 'message' => 'connected'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private static function connect(): void
    {
        if (self::$config === null) {
            throw new \RuntimeException('БД не настроена. Вызовите Database::init()');
        }

        $config = self::$config;
        $host = (string)($config['host'] ?? 'localhost');
        $port = (int)($config['port'] ?? 3306);
        $name = (string)($config['name'] ?? '');
        $user = (string)($config['user'] ?? '');
        $pass = (string)($config['pass'] ?? '');
        $charset = (string)($config['charset'] ?? 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            SchemaMigrator::ensure();
        } catch (PDOException $e) {
            throw new \RuntimeException('Ошибка подключения к БД: ' . $e->getMessage(), 0, $e);
        }
    }
}
