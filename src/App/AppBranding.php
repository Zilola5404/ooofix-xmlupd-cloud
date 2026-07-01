<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\App;

use Ooofix\XmlupdCloud\Rest\BitrixClient;

/** Название и иконка приложения в интерфейсе Bitrix24 (меню, диск приложения). */
final class AppBranding
{
    public const LOGO_FILE = 'ooofix_xmlupd_logo.png';

    public static function logoUrl(): string
    {
        return rtrim(AppConfig::appUrl(), '/') . '/assets/' . self::LOGO_FILE;
    }

    public static function logoPath(): string
    {
        $root = defined('OOOFIX_CLOUD_ROOT') ? OOOFIX_CLOUD_ROOT : dirname(__DIR__, 2);
        $public = $root . '/public/assets/' . self::LOGO_FILE;
        if (is_file($public)) {
            return $public;
        }

        return $root . '/assets/' . self::LOGO_FILE;
    }

    public static function logoExists(): bool
    {
        return is_file(self::logoPath());
    }

    /** @return array<string, mixed> */
    public static function manifest(): array
    {
        return [
            'title'    => AppTitle::get(),
            'logo_url' => self::logoUrl(),
            'logo_ok'  => self::logoExists(),
            'vendors'  => [
                'app_name_ru' => AppTitle::DEFAULT,
                'menu_name_ru'=> AppTitle::DEFAULT,
                'icon_url'    => self::logoUrl(),
            ],
        ];
    }

    /** @return list<string> */
    public function apply(BitrixClient $client): array
    {
        $warnings = (new MenuPlacementService())->bindLeftMenu($client);
        $this->renameAppStorage($client);

        return $warnings;
    }

    private function renameAppStorage(BitrixClient $client): void
    {
        try {
            $storage = $client->result('disk.storage.getforapp', []);
            if (!is_array($storage)) {
                return;
            }

            $storageId = (int)($storage['ID'] ?? 0);
            $currentName = (string)($storage['NAME'] ?? '');
            $targetName = AppTitle::get();

            if ($storageId <= 0 || $currentName === $targetName) {
                return;
            }

            $client->call('disk.storage.rename', [
                'id'      => $storageId,
                'newName' => $targetName,
            ]);
        } catch (\Throwable) {
        }
    }
}
