<?php

declare(strict_types=1);

require __DIR__ . '/../init.php';

use Ooofix\XmlupdCloud\App\ApiBootstrap;
use Ooofix\XmlupdCloud\App\Http;
use Ooofix\XmlupdCloud\Core\Tenant;
use Ooofix\XmlupdCloud\Rest\BitrixClient;
use Ooofix\XmlupdCloud\Storage\DocumentRepository;
use Ooofix\XmlupdCloud\Storage\LocalXmlStorage;

ApiBootstrap::run(static function (array $request, BitrixClient $client): void {
    $documentId = (int)($request['documentId'] ?? $request['id'] ?? 0);
    if ($documentId <= 0) {
        Http::jsonResponse(['success' => false, 'message' => 'Некорректный documentId'], 400);

        return;
    }

    $portalId = Tenant::getPortalId();
    $repo = new DocumentRepository($portalId);
    $doc = $repo->findById($documentId);
    if ($doc === null) {
        Http::jsonResponse(['success' => false, 'message' => 'Документ не найден'], 404);

        return;
    }

    $fileName = (string)($doc['FILE_NAME'] ?? 'upd.xml');
    $diskFileId = (int)($doc['FILE_ID'] ?? 0);

    if ($diskFileId > 0) {
        try {
            $file = $client->result('disk.file.get', ['id' => $diskFileId]);
            $url = is_array($file) ? (string)($file['DOWNLOAD_URL'] ?? '') : '';
            if ($url !== '') {
                header('Location: ' . $url, true, 302);
                exit;
            }
        } catch (\Throwable) {
            // fallback to local copy below
        }
    }

    $content = LocalXmlStorage::read($portalId, $documentId);
    if ($content === null || $content === '') {
        Http::jsonResponse(['success' => false, 'message' => 'Файл XML не найден'], 404);

        return;
    }

    $encoding = (string)($doc['ENCODING'] ?? 'windows-1251');
    header('Content-Type: application/xml; charset=' . $encoding);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $fileName) . '"');
    header('Cache-Control: no-store');
    echo $content;
    exit;
});
