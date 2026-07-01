<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Storage;

/** Локальное хранение XML, если REST disk недоступен (нет scope в OAuth-токене). */
final class LocalXmlStorage
{
    public static function root(): string
    {
        $root = defined('OOOFIX_CLOUD_ROOT') ? OOOFIX_CLOUD_ROOT : dirname(__DIR__, 2);

        return $root . '/var/xml';
    }

    public static function path(int $portalId, int $documentId): string
    {
        return self::root() . '/' . $portalId . '/' . $documentId . '.xml';
    }

    public static function write(int $portalId, int $documentId, string $content): void
    {
        $dir = self::root() . '/' . $portalId;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Не удалось создать каталог var/xml на сервере приложения');
        }

        $path = self::path($portalId, $documentId);
        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException('Не удалось сохранить XML на сервере приложения');
        }
    }

    public static function read(int $portalId, int $documentId): ?string
    {
        $path = self::path($portalId, $documentId);

        return is_file($path) ? (string)file_get_contents($path) : null;
    }
}
