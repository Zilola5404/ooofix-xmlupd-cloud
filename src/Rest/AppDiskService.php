<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Rest;

use Ooofix\XmlupdCloud\Storage\SettingsRepository;

/** Папка XML на Диске приложения Bitrix24 (disk.storage.getforapp). */
final class AppDiskService
{
    public const FOLDER_NAME = 'XML';

    public const SETTING_FOLDER_ID = 'disk_xml_folder_id';

    public const SETTING_STORAGE_ID = 'disk_app_storage_id';

    public function __construct(
        private readonly BitrixClient $client,
        private readonly SettingsRepository $settings,
        private readonly int $portalId,
    ) {
    }

    /** Создать или найти папку /XML/ в хранилище приложения. */
    public function ensureXmlFolder(): int
    {
        $cached = (int)$this->settings->get($this->portalId, self::SETTING_FOLDER_ID, '0');
        if ($cached > 0) {
            return $cached;
        }

        $storage = $this->client->result('disk.storage.getforapp', []);
        if (!is_array($storage)) {
            throw new \RuntimeException('Не удалось получить хранилище приложения на Диске B24');
        }

        $storageId = (int)($storage['ID'] ?? 0);
        if ($storageId <= 0) {
            throw new \RuntimeException('Не найдено хранилище Диска приложения');
        }

        $folderId = $this->findOrCreateXmlFolder($storageId);
        if ($folderId <= 0) {
            throw new \RuntimeException('Не удалось создать папку ' . self::FOLDER_NAME . ' на Диске B24');
        }

        $this->settings->saveAll($this->portalId, [
            self::SETTING_FOLDER_ID   => (string)$folderId,
            self::SETTING_STORAGE_ID  => (string)$storageId,
        ]);

        return $folderId;
    }

    private function findOrCreateXmlFolder(int $storageId): int
    {
        $existing = $this->findXmlFolderInStorage($storageId);
        if ($existing > 0) {
            return $existing;
        }

        try {
            $created = $this->client->result('disk.storage.addfolder', [
                'id'   => $storageId,
                'data' => ['NAME' => self::FOLDER_NAME],
            ]);
            if (is_array($created) && !empty($created['ID'])) {
                return (int)$created['ID'];
            }
        } catch (\Throwable $e) {
            if (!$this->isFolderAlreadyExistsError($e)) {
                throw $e;
            }
        }

        return $this->findXmlFolderInStorage($storageId);
    }

    private function findXmlFolderInStorage(int $storageId): int
    {
        $children = $this->client->result('disk.storage.getchildren', ['id' => $storageId]);
        if (!is_array($children)) {
            return 0;
        }

        foreach ($children as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (($item['TYPE'] ?? '') === 'folder' && ($item['NAME'] ?? '') === self::FOLDER_NAME) {
                return (int)($item['ID'] ?? 0);
            }
        }

        return 0;
    }

    private function isFolderAlreadyExistsError(\Throwable $e): bool
    {
        $msg = mb_strtolower($e->getMessage());

        return str_contains($msg, '22000')
            || str_contains($msg, 'уже')
            || str_contains($msg, 'already');
    }
}
