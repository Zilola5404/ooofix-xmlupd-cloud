<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Xml;

use Ooofix\XmlupdCloud\Core\Config;

/** Локальные XSD-схемы ФНС/Диадок по версии формата УПД (без обращений в интернет). */
final class XsdSchemaRegistry
{
    public const TITLE_SELLER = 'seller';

    public const TITLE_BUYER = 'buyer';

    /** @var array<string, array<string, list<string>>> */
    private const VERSION_MAP = [
        '5.03' => [
            self::TITLE_SELLER => [
                'ON_NSCHFDOPPR_1_997_01_05_03_05.xsd',
                'ON_NSCHFDOPPR_1_997_01_05_03_04.xsd',
            ],
            self::TITLE_BUYER => [
                'ON_NSCHFDOPPOK_1_997_02_05_03_01.xsd',
            ],
        ],
        '5.02' => [
            self::TITLE_SELLER => [
                'ON_NSCHFDOPPR_1_997_01_05_02_02.xsd',
                'ON_NSCHFDOPPR_1_997_01_05_02_01.xsd',
            ],
            self::TITLE_BUYER => [
                'ON_NSCHFDOPPOK_1_997_02_05_02_01.xsd',
            ],
        ],
    ];

    /** @return list<string> */
    public static function supportedVersions(): array
    {
        return array_keys(self::VERSION_MAP);
    }

    public static function resolveSellerSchema(?string $formatVersion = null, ?string $revision = null): string
    {
        $override = Config::xsdPath();
        if ($override !== '') {
            return $override;
        }

        $version = self::normalizeVersion($formatVersion ?? Config::xmlFormatVersion());
        $rev = $revision ?? Config::xsdSchemaRevision();

        return self::resolveSchemaPath($version, self::TITLE_SELLER, $rev);
    }

    public static function resolveSchemaPath(string $formatVersion, string $title, string $revision = 'auto'): string
    {
        $version = self::normalizeVersion($formatVersion);
        $files = self::VERSION_MAP[$version][$title] ?? [];

        if ($files === []) {
            throw new \RuntimeException(
                'XSD-схема не найдена для версии формата «' . $version . '» (титул: ' . $title . ').'
            );
        }

        if ($revision !== 'auto') {
            $needle = '_' . ltrim($revision, '_') . '.xsd';
            foreach ($files as $file) {
                if (str_ends_with($file, $needle)) {
                    return self::absolutePath($version, $file);
                }
            }
        }

        foreach ($files as $file) {
            $path = self::schemasRoot() . '/' . $version . '/' . $file;
            if (is_file($path)) {
                $resolved = realpath($path);

                return $resolved !== false ? $resolved : $path;
            }
        }

        throw new \RuntimeException(
            'Файлы XSD для версии «' . $version . '» отсутствуют в ' . self::schemasRoot() . '/'
        );
    }

    public static function extractFormatVersion(string $xml): ?string
    {
        if (preg_match('/\sВерсФорм=["\']([0-9]+\.[0-9]+)["\']/u', $xml, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function extractFileId(string $xml): ?string
    {
        if (preg_match('/\sИдФайл=["\']([^"\']+)["\']/u', $xml, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private static function absolutePath(string $version, string $file): string
    {
        $path = self::schemasRoot() . '/' . $version . '/' . $file;
        if (!is_file($path)) {
            throw new \RuntimeException('XSD не найден: ' . $path);
        }

        $resolved = realpath($path);

        return $resolved !== false ? $resolved : $path;
    }

    private static function schemasRoot(): string
    {
        if (defined('OOOFIX_CLOUD_ROOT')) {
            return OOOFIX_CLOUD_ROOT . '/src/Core/config/schemas';
        }

        return dirname(__DIR__) . '/config/schemas';
    }

    private static function normalizeVersion(string $version): string
    {
        $version = trim($version);
        if ($version === '') {
            return '5.03';
        }

        if (!isset(self::VERSION_MAP[$version])) {
            throw new \RuntimeException(
                'Неподдерживаемая версия формата XML: ' . $version
                . '. Доступны: ' . implode(', ', self::supportedVersions())
            );
        }

        return $version;
    }
}
