<?php

declare(strict_types=1);

/**
 * Диагностика деплоя (без OAuth). Откройте в браузере после загрузки на сервер.
 * Удалите или закройте доступ после проверки.
 */
header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
$lines = [];

$lines[] = '=== ooofix-xmlupd-cloud health ===';
$lines[] = 'PHP: ' . PHP_VERSION . (PHP_VERSION_ID >= 80100 ? ' OK' : ' FAIL (need 8.1+)');
$lines[] = 'Root: ' . $root;

$checks = [
    'vendor/autoload.php' => $root . '/vendor/autoload.php',
    'config/app.php'      => $root . '/config/app.php',
    '.env'                => $root . '/.env',
    'bootstrap.php'       => $root . '/bootstrap.php',
    'public/index.php'    => $root . '/public/index.php',
    'xsd-5.03'            => $root . '/src/Core/config/schemas/5.03/ON_NSCHFDOPPR_1_997_01_05_03_05.xsd',
];

foreach ($checks as $label => $path) {
    $lines[] = $label . ': ' . (is_file($path) ? 'OK' : 'MISSING');
}

$ext = ['pdo', 'pdo_mysql', 'curl', 'dom', 'iconv', 'json'];
foreach ($ext as $name) {
    $lines[] = 'ext-' . $name . ': ' . (extension_loaded($name) ? 'OK' : 'MISSING');
}

try {
    require $root . '/bootstrap.php';
    $lines[] = 'bootstrap: OK';
    $lines[] = 'APP_URL: ' . Ooofix\XmlupdCloud\App\AppConfig::appUrl();

    $db = Ooofix\XmlupdCloud\Storage\Database::ping();
    $lines[] = 'database: ' . ($db['ok'] ? 'OK' : 'FAIL - ' . $db['message']);

    if ($db['ok']) {
        $pdo = Ooofix\XmlupdCloud\Storage\Database::pdo();
        foreach (['portals', 'portal_settings', 'queue_jobs', 'b_xmldoc_log', 'b_xmldoc_document'] as $table) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
            );
            $dbName = (string)(Ooofix\XmlupdCloud\App\AppConfig::database()['name'] ?? '');
            $stmt->execute([$dbName, $table]);
            $exists = (int)$stmt->fetchColumn() > 0;
            $lines[] = 'table ' . $table . ': ' . ($exists ? 'OK' : 'MISSING');
        }

        $schema = Ooofix\XmlupdCloud\Storage\SchemaMigrator::schemaReport();
        foreach ($schema as $table => $missing) {
            if ($missing === []) {
                $lines[] = 'schema ' . $table . ': OK';
                continue;
            }
            if ($missing === ['__table__']) {
                $lines[] = 'schema ' . $table . ': TABLE MISSING';
                continue;
            }
            $lines[] = 'schema ' . $table . ': MISSING ' . implode(', ', $missing);
        }
    }
} catch (Throwable $e) {
    $lines[] = 'bootstrap: FAIL - ' . $e->getMessage();
}

$lines[] = 'REST scopes (vendors): ' . implode(', ', Ooofix\XmlupdCloud\App\AppScopes::REST);
$lines[] = 'vendors: «Умные сценарии / шаблоны» = Да (bizproc.robot.add)';

$lines[] = '';
$lines[] = 'Если database FAIL — проверьте DB_HOST/DB_NAME/DB_USER/DB_PASS в .env';
$lines[] = 'Если table MISSING — mysql -u USER -p DB < database/install_btops_app.sql';

echo implode("\n", $lines) . "\n";
