<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\App;

/** Единое название приложения в UI Bitrix24 */
final class AppTitle
{
    public const DEFAULT = 'Генерация XML (УПД)';

    public static function get(): string
    {
        return AppConfig::appTitle();
    }

    /** @return array<string, array{TITLE: string}> */
    public static function langAll(): array
    {
        $title = self::get();

        return [
            'ru' => ['TITLE' => $title],
            'en' => ['TITLE' => 'XML UPD Generation'],
        ];
    }
}
