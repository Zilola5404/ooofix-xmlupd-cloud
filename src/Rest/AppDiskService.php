<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Rest;

use Ooofix\XmlupdCloud\Storage\SettingsRepository;

/** Папка XML на общем Диске портала Bitrix24 (disk.storage.getlist, ENTITY_TYPE=common). */
final class AppDiskService
{
    public const FOLDER_NAME = 'XML';

    public const SETTING_FOLDER_ID = 'disk_xml_folder_id';

    public const SETTING_STORAGE_ID = 'disk_common_storage_id';

    private const STORAGE_ENTITY_TYPE = 'common';

    public function __construct(
        private readonly BitrixClient $client,
        private readonly SettingsRepository $settings,
        private readonly int $portalId,
    ) {
    }

    /** Создать или найти папку /XML/ в корне общего хранилища Диска. */
    public function ensureXmlFolder(): int
    {
        if ($this->portalId <= 0) {
            throw new \RuntimeException('Не определён portal_id для настройки папки /XML/');
        }

        $storageId = $this->resolveCommonStorageId();

        $cached = (int)$this->settings->get($this->portalId, self::SETTING_FOLDER_ID, '0');
        if ($cached > 0 && $this->isXmlFolder($cached, $storageId)) {
            return $cached;
        }

        if ($cached > 0) {
            $this->settings->saveAll($this->portalId, [
                self::SETTING_FOLDER_ID  => '0',
                self::SETTING_STORAGE_ID => '0',
            ]);
        }

        $folderId = $this->findOrCreateXmlFolder($storageId);
        if ($folderId <= 0) {
            throw new \RuntimeException('Не удалось создать папку ' . self::FOLDER_NAME . ' на общем Диске B24');
        }

        $this->settings->saveAll($this->portalId, [
            self::SETTING_FOLDER_ID  => (string)$folderId,
            self::SETTING_STORAGE_ID => (string)$storageId,
        ]);

        return $folderId;
    }

    private function resolveCommonStorageId(): int
    {
        $cached = (int)$this->settings->get($this->portalId, self::SETTING_STORAGE_ID, '0');
        if ($cached > 0 && $this->isCommonStorage($cached)) {
            return $cached;
        }

        $storages = $this->client->result('disk.storage.getlist', [
            'filter' => ['ENTITY_TYPE' => self::STORAGE_ENTITY_TYPE],
            'order'  => ['ID' => 'ASC'],
        ]);

        if (!is_array($storages)) {
            throw new \RuntimeException('Не удалось получить список хранилищ Диска B24');
        }

        foreach ($storages as $storage) {
            if (!is_array($storage)) {
                continue;
            }
            if (($storage['ENTITY_TYPE'] ?? '') !== self::STORAGE_ENTITY_TYPE) {
                continue;
            }

            $storageId = (int)($storage['ID'] ?? 0);
            if ($storageId > 0) {
                return $storageId;
            }
        }

        throw new \RuntimeException('Не найдено общее хранилище Диска на портале (ENTITY_TYPE=common)');
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

    private function isCommonStorage(int $storageId): bool
    {
        if ($storageId <= 0) {
            return false;
        }

        try {
            $storage = $this->client->result('disk.storage.get', ['id' => $storageId]);

            return is_array($storage)
                && ($storage['ENTITY_TYPE'] ?? '') === self::STORAGE_ENTITY_TYPE;
        } catch (\Throwable) {
            return false;
        }
    }

    private function isFolderAlreadyExistsError(\Throwable $e): bool
    {
        $msg = mb_strtolower($e->getMessage());

        return str_contains($msg, '22000')
            || str_contains($msg, 'уже')
            || str_contains($msg, 'already');
    }

    private function isXmlFolder(int $folderId, int $storageId): bool
    {
        if ($folderId <= 0) {
            return false;
        }

        try {
            $folder = $this->client->result('disk.folder.get', ['id' => $folderId]);

            return is_array($folder)
                && ($folder['TYPE'] ?? '') === 'folder'
                && ($folder['NAME'] ?? '') === self::FOLDER_NAME
                && (int)($folder['STORAGE_ID'] ?? 0) === $storageId;
        } catch (\Throwable) {
            return false;
        }
    }
}
