<?php

declare(strict_types=1);

/**
 * Проверка подключения к MySQL и наличия таблиц.
 * php public/cli/db_check.php
 */
require dirname(__DIR__) . '/init.php';

use Ooofix\XmlupdCloud\App\AppConfig;
use Ooofix\XmlupdCloud\Storage\Database;

$requiredTables = [
    'portals',
    'portal_settings',
    'b_xmldoc_log',
    'b_xmldoc_document',
    'queue_jobs',
];

$db = AppConfig::database();
echo 'DB host: ' . ($db['host'] ?? '?') . PHP_EOL;
echo 'DB name: ' . ($db['name'] ?? '?') . PHP_EOL;
echo 'DB user: ' . ($db['user'] ?? '?') . PHP_EOL;

try {
    $pdo = Database::pdo();
    $pdo->query('SELECT 1');
    echo "Connection: OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Connection: FAIL - {$e->getMessage()}\n");
    exit(1);
}

$missing = [];
foreach ($requiredTables as $table) {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = ? AND table_name = ?'
    );
    $stmt->execute([(string)($db['name'] ?? ''), $table]);
    $exists = (int)$stmt->fetchColumn() > 0;
    echo 'Table ' . $table . ': ' . ($exists ? 'OK' : 'MISSING') . PHP_EOL;
    if (!$exists) {
        $missing[] = $table;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "\nСоздайте таблицы:\n");
    fwrite(STDERR, "  mysql -u {$db['user']} -p {$db['name']} < database/schema.sql\n");
    exit(2);
}

echo "\nDatabase ready.\n";
