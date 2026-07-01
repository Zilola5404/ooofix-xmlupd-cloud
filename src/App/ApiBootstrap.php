<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\App;

use Ooofix\XmlupdCloud\Core\Config;
use Ooofix\XmlupdCloud\Core\Logger;
use Ooofix\XmlupdCloud\Core\Tenant;
use Ooofix\XmlupdCloud\Rest\BitrixClient;

/**
 * Общая инициализация HTTP-запросов: OAuth, Tenant, Config.
 */
final class ApiBootstrap
{
    /**
     * @param callable(array<string, mixed>, BitrixClient, string): void $handler
     */
    public static function run(callable $handler): void
    {
        try {
            [$request, $client, , , $requestId] = PortalBootstrap::boot();
            $handler($request, $client, $requestId);
        } catch (\Throwable $e) {
            Http::jsonResponse(array_merge(
                ['success' => false, 'message' => $e->getMessage()],
                Logger::contextExtra()
            ), 500);
        } finally {
            Tenant::clear();
        }
    }
}
