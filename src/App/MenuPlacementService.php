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

        $bindParams = $this->buildBindParams();

        try {
            $this->placementBind($client, $bindParams);
        } catch (\Throwable $e) {
            if ($this->isAlreadyRegisteredError($e)) {
                try {
                    $client->call('placement.unbind', ['PLACEMENT' => PlacementRegistry::LEFT_MENU]);
                    $this->placementBind($client, $bindParams);
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

    /** @return array<string, mixed> */
    private function buildBindParams(): array
    {
        $bindParams = [
            'PLACEMENT' => PlacementRegistry::LEFT_MENU,
            'HANDLER'   => AppConfig::appUrl() . '/index.php',
            'TITLE'     => AppTitle::get(),
            'LANG_ALL'  => AppTitle::langAll(),
        ];

        if (AppBranding::logoExists()) {
            $iconUrl = AppBranding::logoUrl();
            $bindParams['OPTIONS'] = [
                'icon'    => $iconUrl,
                'iconUrl' => $iconUrl,
            ];
        }

        return $bindParams;
    }

    /** @param array<string, mixed> $bindParams */
    private function placementBind(BitrixClient $client, array $bindParams): void
    {
        try {
            $client->call('placement.bind', $bindParams);
        } catch (\Throwable $e) {
            if (!isset($bindParams['OPTIONS'])) {
                throw $e;
            }

            $fallback = $bindParams;
            unset($fallback['OPTIONS']);
            $client->call('placement.bind', $fallback);
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
