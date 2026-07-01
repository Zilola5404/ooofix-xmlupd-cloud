<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core;

/** Версия и метаданные модуля */
final class ModuleInfo
{
    public const MODULE_ID = 'ooofix-xmlupd-cloud';
    public const MODULE_TITLE = 'Генерация XML (УПД)';
    public const MODULE_DESCRIPTION = 'Формирование XML УПД из CRM: автоопределение коробка / облако Bitrix24';
    public const PARTNER_NAME = 'ООО "РЕШЕНИЕ"';
    public const PARTNER_URI = 'https://ooofix.ru';

    public static function version(): string
    {
        static $version = null;
        if ($version !== null) {
            return $version;
        }

        $root = defined('OOOFIX_CLOUD_ROOT') ? OOOFIX_CLOUD_ROOT : dirname(__DIR__, 2);
        $versionFile = $root . '/VERSION';
        if (is_file($versionFile)) {
            $lines = file($versionFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $version = is_array($lines) && isset($lines[0]) ? trim($lines[0]) : '1.0.0';
        } else {
            $version = '1.0.0';
        }

        return $version;
    }

    public static function programName(): string
    {
        return self::MODULE_ID . ' ' . self::version();
    }
}
