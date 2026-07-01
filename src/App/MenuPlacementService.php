<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\App;

use Ooofix\XmlupdCloud\Rest\BitrixClient;
use Ooofix\XmlupdCloud\Rest\PlacementRegistry;

/** Пункт левого меню Bitrix24 (placement LEFT_MENU) */
final class MenuPlacementService
{
    /** @return list<string> предупреждения */
    public function bindLeftMenu(BitrixClient $client): array
    {
        $available = PlacementRegistry::listAvailableAll($client);
        if ($available !== [] && !in_array(PlacementRegistry::LEFT_MENU, $available, true)) {
            return [];
        }

        $title = AppTitle::get();
        $handler = AppConfig::appUrl() . '/index.php';

        try {
            $client->call('placement.bind', [
                'PLACEMENT' => PlacementRegistry::LEFT_MENU,
                'HANDLER'   => $handler,
                'TITLE'     => $title,
                'LANG_ALL'  => AppTitle::langAll(),
            ]);
        } catch (\Throwable $e) {
            if ($this->isAlreadyRegisteredError($e)) {
                try {
                    $client->call('placement.unbind', ['PLACEMENT' => PlacementRegistry::LEFT_MENU]);
                    $client->call('placement.bind', [
                        'PLACEMENT' => PlacementRegistry::LEFT_MENU,
                        'HANDLER'   => $handler,
                        'TITLE'     => $title,
                        'LANG_ALL'  => AppTitle::langAll(),
                    ]);
                } catch (\Throwable $retry) {
                    return ['LEFT_MENU: ' . $retry->getMessage()];
                }

                return [];
            }

            return ['LEFT_MENU: ' . $e->getMessage()];
        }

        return [];
    }

    public function unbindLeftMenu(BitrixClient $client): void
    {
        try {
            $client->call('placement.unbind', ['PLACEMENT' => PlacementRegistry::LEFT_MENU]);
        } catch (\Throwable) {
        }
    }

    private function isAlreadyRegisteredError(\Throwable $e): bool
    {
        $msg = mb_strtolower($e->getMessage());

        return str_contains($msg, 'already')
            || str_contains($msg, 'уже')
            || str_contains($msg, 'installed');
    }
}
