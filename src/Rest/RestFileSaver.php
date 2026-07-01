<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Rest;

use Ooofix\XmlupdCloud\Core\Config;
use Ooofix\XmlupdCloud\Core\DocumentStatus;
use Ooofix\XmlupdCloud\Core\XmlEncoder;
use Ooofix\XmlupdCloud\Storage\DocumentRepository;
use Ooofix\XmlupdCloud\Storage\SettingsRepository;

/** Сохранение XML в папку /XML/ на общем Диске B24 и запись в UF_UPD_FILE. */
final class RestFileSaver
{
    public const BUILD_MARKER = 'crm-item-uf-file-v1';

    public function __construct(
        private readonly BitrixClient $client,
        private readonly DocumentRepository $documents,
    ) {
    }

    /** @return array<string, mixed> */
    public function save(
        string $entityType,
        int $entityId,
        int $entityTypeId,
        string $utf8Xml,
        string $docNumber,
    ): array {
        $version = $this->documents->getNextVersion($entityType, $entityId);
        $fileName = sprintf('УПД_%d.xml', $entityId);
        $storageName = sprintf('УПД_%d_v%d.xml', $entityId, $version);
        $encoding = Config::fileEncoding();
        $xmlContent = XmlEncoder::forStorage($utf8Xml);

        $upload = $this->uploadToXmlFolder($storageName, $xmlContent);
        $fileId = $upload['fileId'];
        if ($fileId <= 0) {
            throw new \RuntimeException('Не удалось сохранить файл в папку /XML/ на общем Диске B24');
        }

        (new CrmUserFieldAttacher())->attachUpdFile(
            $this->client,
            $entityType,
            $entityId,
            $entityTypeId,
            $fileName,
            $xmlContent,
            $docNumber,
        );

        $this->documents->add(
            $entityType,
            $entityId,
            $fileName,
            $fileId,
            $docNumber,
            $version,
            $encoding,
            hash('sha256', $xmlContent),
            DocumentStatus::GENERATED,
        );

        if (Config::publishTimeline()) {
            $this->publishTimeline($entityTypeId, $entityId, $fileName);
        }

        $urls = $this->resolveFileUrls($fileId, $upload);

        return [
            'fileId'      => $fileId,
            'fileName'    => $fileName,
            'version'     => $version,
            'encoding'    => $encoding,
            'docStatus'   => DocumentStatus::GENERATED,
            'downloadUrl' => $urls['downloadUrl'],
            'detailUrl'   => $urls['detailUrl'],
            'storageMode' => 'disk',
        ];
    }

    /**
     * @param array{downloadUrl?: string, detailUrl?: string}|null $upload
     * @return array{downloadUrl: string, detailUrl: string}
     */
    private function resolveFileUrls(int $diskFileId, ?array $upload = null): array
    {
        if ($upload !== null) {
            $downloadUrl = (string)($upload['downloadUrl'] ?? '');
            $detailUrl = (string)($upload['detailUrl'] ?? '');
            if ($downloadUrl !== '' || $detailUrl !== '') {
                return ['downloadUrl' => $downloadUrl, 'detailUrl' => $detailUrl];
            }
        }

        try {
            $file = $this->client->result('disk.file.get', ['id' => $diskFileId]);
            if (!is_array($file)) {
                return ['downloadUrl' => '', 'detailUrl' => ''];
            }

            return [
                'downloadUrl' => (string)($file['DOWNLOAD_URL'] ?? ''),
                'detailUrl'   => (string)($file['DETAIL_URL'] ?? ''),
            ];
        } catch (\Throwable) {
            return ['downloadUrl' => '', 'detailUrl' => ''];
        }
    }

    /**
     * @return array{fileId: int, downloadUrl: string, detailUrl: string}
     */
    private function uploadToXmlFolder(string $fileName, string $content): array
    {
        $disk = new AppDiskService(
            $this->client,
            new SettingsRepository(),
            $this->documents->portalId(),
        );
        $folderId = $disk->ensureXmlFolder();

        $result = $this->client->call('disk.folder.uploadfile', [
            'id'                 => $folderId,
            'data'               => ['NAME' => $fileName],
            'fileContent'        => [$fileName, base64_encode($content)],
            'generateUniqueName' => true,
        ]);

        $uploaded = $result['result'] ?? null;
        if (!is_array($uploaded)) {
            return ['fileId' => 0, 'downloadUrl' => '', 'detailUrl' => ''];
        }

        $fileId = (int)($uploaded['ID'] ?? $uploaded['FILE']['ID'] ?? 0);

        return [
            'fileId'      => $fileId,
            'downloadUrl' => (string)($uploaded['DOWNLOAD_URL'] ?? ''),
            'detailUrl'   => (string)($uploaded['DETAIL_URL'] ?? ''),
        ];
    }

    private function publishTimeline(int $entityTypeId, int $entityId, string $fileName): void
    {
        try {
            $this->client->call('crm.timeline.comment.add', [
                'fields' => [
                    'ENTITY_ID'   => $entityId,
                    'ENTITY_TYPE' => 'deal',
                    'COMMENT'     => 'Сформирован XML УПД: ' . $fileName,
                ],
            ]);
        } catch (\Throwable) {
        }
    }
}
