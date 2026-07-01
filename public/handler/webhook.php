<?php

declare(strict_types=1);

/**
 * Входящие события Bitrix24 (ONAPPUPDATE, ONAPPINSTALL, ONCRMDEAL*, и т.д.).
 * URL для vendors: обработчик событий приложения.
 */
require_once dirname(__DIR__) . '/_url_probe.php';
ooofix_cloud_url_probe_exit_if_needed();

require_once dirname(__DIR__) . '/init.php';

use Ooofix\XmlupdCloud\App\Http;
use Ooofix\XmlupdCloud\App\OAuthEventHandler;
use Ooofix\XmlupdCloud\App\OAuthService;
use Ooofix\XmlupdCloud\App\PortalBootstrap;
use Ooofix\XmlupdCloud\Core\Logger;
use Ooofix\XmlupdCloud\Core\Tenant;

try {
    $request = Http::request();
    $event = OAuthService::resolveInstallEvent($request);

    if ($event !== '') {
        $request['event'] = $event;
    }

    if (in_array($event, ['ONAPPUPDATE', 'ONAPPINSTALL', 'ONAPPUNINSTALL'], true)) {
        $result = (new OAuthEventHandler())->dispatch($request);
        Http::jsonResponse([
            'success' => true,
            'event'   => $event,
            'message' => $result['message'] ?? 'OK',
            'result'  => $result,
        ]);
    }

    if (OAuthService::isServerOAuthEvent($request)) {
        $result = (new OAuthEventHandler())->dispatch($request);
        Http::jsonResponse([
            'success' => true,
            'event'   => OAuthService::resolveInstallEvent($request),
            'message' => $result['message'] ?? 'OK',
            'result'  => $result,
        ]);
    }

    if (!ooofix_cloud_has_bitrix_context()) {
        Http::jsonResponse([
            'success' => true,
            'handler' => 'ooofix-xmlupd-cloud/webhook',
            'message' => 'Обработчик событий доступен. Ожидается POST от Bitrix24 (ONAPPUPDATE, ONAPPINSTALL и др.).',
        ]);
    }

    [$request, , $requestId] = handlerBootstrapFromWebhook($request);

    $event = strtoupper((string)($request['event'] ?? $request['EVENT'] ?? 'unknown'));
    $entityId = (int)($request['data']['FIELDS']['ID'] ?? $request['ID'] ?? 0);

    Logger::info('Webhook: ' . $event, 'webhook', $entityId);

    Http::jsonResponse([
        'success'    => true,
        'event'      => $event,
        'request_id' => $requestId,
        'message'    => 'Событие принято',
    ]);
} catch (Throwable $e) {
    Http::jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
} finally {
    Tenant::clear();
}

/** @return array{0: array<string, mixed>, 1: \Ooofix\XmlupdCloud\Rest\BitrixClient, 2: string} */
function handlerBootstrapFromWebhook(array $request): array
{
    [$request, $client, , , $requestId] = PortalBootstrap::boot();

    return [$request, $client, $requestId];
}
