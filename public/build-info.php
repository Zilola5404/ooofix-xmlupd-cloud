<?php

declare(strict_types=1);

/**
 * Проверка деплоя: откройте в браузере после загрузки файлов на сервер.
 */
require __DIR__ . '/init.php';

use Ooofix\XmlupdCloud\App\AppBranding;
use Ooofix\XmlupdCloud\App\AppConfig;
use Ooofix\XmlupdCloud\App\AppTitle;
use Ooofix\XmlupdCloud\App\AssetVersion;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$root = dirname(__DIR__);
$checks = [];

foreach ([
    'PageRenderer' => 'src/App/PageRenderer.php',
    'PlacementRenderer' => 'src/App/PlacementRenderer.php',
    'MenuPlacement' => 'src/App/MenuPlacementService.php',
    'ui-forms.css' => 'public/frontend/css/ui-forms.css',
    'api-auth.js' => 'public/frontend/api-auth.js',
    'generate-button.js' => 'public/frontend/generate-button.js',
    'settings.js' => 'public/frontend/settings.js',
    'deal-button.php' => 'public/placements/deal-button.php',
    'xsd-5.03'        => 'src/Core/config/schemas/5.03/ON_NSCHFDOPPR_1_997_01_05_03_05.xsd',
    'download-api'    => 'public/api/download.php',
    'RestFileSaver'   => 'src/Rest/RestFileSaver.php',
] as $label => $rel) {
    $path = $root . '/' . $rel;
    $checks[$label] = [
        'path'   => $rel,
        'exists' => is_file($path),
        'mtime'  => is_file($path) ? date('c', (int)filemtime($path)) : null,
    ];
}

$restFileSaverPath = $root . '/src/Rest/RestFileSaver.php';
$restFileSaverSrc = is_file($restFileSaverPath) ? (string)file_get_contents($restFileSaverPath) : '';

echo json_encode([
    'success'    => true,
    'app_title'  => AppTitle::get(),
    'app_url'    => AppConfig::appUrl(),
    'branding'   => AppBranding::manifest(),
    'build'      => AssetVersion::get(),
    'php'        => PHP_VERSION,
    'checks'     => $checks,
    'deploy'     => [
        'rest_file_saver_marker' => class_exists(\Ooofix\XmlupdCloud\Rest\RestFileSaver::class)
            ? \Ooofix\XmlupdCloud\Rest\RestFileSaver::BUILD_MARKER
            : null,
        'has_disk_common_storage' => str_contains($restFileSaverSrc, 'disk.storage.getlist'),
        'has_local_fallback'     => str_contains($restFileSaverSrc, 'uploadToXmlFolder'),
        'has_xml_folder'         => class_exists(\Ooofix\XmlupdCloud\Rest\AppDiskService::class),
    ],
    'schema'     => class_exists(\Ooofix\XmlupdCloud\Storage\SchemaMigrator::class)
        ? \Ooofix\XmlupdCloud\Storage\SchemaMigrator::schemaReport()
        : [],
    'index_hint' => AppConfig::appUrl() . '/index.php?page=settings',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
