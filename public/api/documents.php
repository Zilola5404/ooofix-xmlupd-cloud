<?php

declare(strict_types=1);

require __DIR__ . '/../init.php';

use Ooofix\XmlupdCloud\App\ApiBootstrap;
use Ooofix\XmlupdCloud\App\Http;
use Ooofix\XmlupdCloud\Core\Tenant;
use Ooofix\XmlupdCloud\Rest\BitrixClient;
use Ooofix\XmlupdCloud\Storage\DocumentRegisterService;
use Ooofix\XmlupdCloud\Storage\DocumentRepository;
use Ooofix\XmlupdCloud\Storage\RowMapper;
use Ooofix\XmlupdCloud\Storage\SettingsRepository;

ApiBootstrap::run(static function (array $request, BitrixClient $client): void {
    $portalId = Tenant::getPortalId();
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'POST') {
        handleRegister($portalId, $request);

        return;
    }

    handleList($portalId, $client, $request);
});

/** @param array<string, mixed> $request */
function handleRegister(int $portalId, array $request): void
{
    $service = new DocumentRegisterService($portalId);
    $document = $service->registerFromRequest($request);

    Http::jsonResponse([
        'success'    => true,
        'document'   => $document,
        'request_id' => Tenant::getRequestId(),
    ]);
}

/** @param array<string, mixed> $request */
function handleList(int $portalId, BitrixClient $client, array $request): void
{
    $repo = new DocumentRepository($portalId);
    $settings = new SettingsRepository();

    $smartTypeId = (int)$settings->get($portalId, 'smart_invoice_type_id', '0');
    if ($smartTypeId <= 0) {
        $smartTypeId = (int)$settings->get($portalId, 'smart_invoice_spa_id', '0');
    }

    $documents = [];
    $fileUrlCache = [];

    foreach ($repo->fetchList((int)($request['limit'] ?? 100)) as $row) {
        $fileId = (int)($row['FILE_ID'] ?? 0);
        $row['CRM_PATH'] = RowMapper::crmPath(
            (string)($row['ENTITY_TYPE'] ?? ''),
            (int)($row['ENTITY_ID'] ?? 0),
            $smartTypeId,
        );
        $row['FILE_URL'] = resolveDiskFileUrl($client, $fileId, $fileUrlCache);
        $documents[] = $row;
    }

    Http::jsonResponse([
        'success'    => true,
        'documents'  => $documents,
        'request_id' => Tenant::getRequestId(),
    ]);
}

/** @param array<int, string> $cache */
function resolveDiskFileUrl(BitrixClient $client, int $fileId, array &$cache): string
{
    if ($fileId <= 0) {
        return '';
    }

    if (array_key_exists($fileId, $cache)) {
        return $cache[$fileId];
    }

    try {
        $file = $client->result('disk.file.get', ['id' => $fileId]);
        if (!is_array($file)) {
            $url = '';
        } else {
            $detailUrl = (string)($file['DETAIL_URL'] ?? '');
            $downloadUrl = (string)($file['DOWNLOAD_URL'] ?? '');
            $url = $detailUrl !== '' ? $detailUrl : $downloadUrl;
        }
    } catch (\Throwable) {
        $url = '';
    }

    $cache[$fileId] = $url;

    return $url;
}
