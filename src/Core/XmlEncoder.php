<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core;

/**
 * Конвертация XML: UTF-8 (генерация) -> windows-1251 (файл / Диадок).
 */
class XmlEncoder
{
    public const UTF8         = 'UTF-8';
    public const WINDOWS_1251 = 'windows-1251';

    /** Убирает декларацию, конвертирует тело, ставит encoding="windows-1251" */
    public static function toWindows1251(string $utf8Xml): string
    {
        if (!function_exists('iconv')) {
            throw new \RuntimeException('Расширение iconv не доступно для конвертации в windows-1251');
        }

        $body = preg_replace('/^\xEF\xBB\xBF/', '', $utf8Xml);
        $body = preg_replace('/<\?xml[^?]*\?>\s*/i', '', $body, 1);

        $converted = iconv(self::UTF8, 'Windows-1251//IGNORE', $body);
        if ($converted === false) {
            throw new \RuntimeException('Ошибка конвертации XML в windows-1251');
        }

        return '<?xml version="1.0" encoding="windows-1251"?>' . "\n" . $converted;
    }

    public static function forStorage(string $utf8Xml): string
    {
        if (Config::fileEncoding() === self::WINDOWS_1251) {
            return self::toWindows1251($utf8Xml);
        }

        return $utf8Xml;
    }
}
