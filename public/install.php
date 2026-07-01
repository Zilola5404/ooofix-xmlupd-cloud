<?php

declare(strict_types=1);

/**
 * Callback установки / удаления / переустановки приложения.
 * Кабинет vendors.bitrix24.ru проверяет URL через HEAD (ожидает 200).
 * После установки обязателен BX24.installFinish() — см. документацию B24.
 */
require __DIR__ . '/_url_probe.php';
ooofix_cloud_url_probe_exit_if_needed();

require __DIR__ . '/init.php';

use Ooofix\XmlupdCloud\App\Http;
use Ooofix\XmlupdCloud\App\OAuthEventHandler;
use Ooofix\XmlupdCloud\App\OAuthService;

try {
    $request = Http::installRequest();
    $event = OAuthService::resolveInstallEvent($request);
    if ($event !== '') {
        $request['event'] = $event;
    }

    if (OAuthService::isServerOAuthEvent($request)) {
        $result = (new OAuthEventHandler())->dispatch($request);
        Http::jsonResponse([
            'success' => true,
            'event'   => $result['event'] ?? $event,
            'message' => $result['message'] ?? 'OK',
            'result'  => $result,
        ]);
    }

    if (!OAuthService::hasInstallCredentials($request)) {
        ooofix_cloud_render_install_client();
    }

    $result = (new OAuthEventHandler())->dispatch($request);
    if ($result === null) {
        throw new RuntimeException(
            'Неизвестное событие установки'
            . ($event !== '' ? ': ' . $event : ' (нет event и AUTH_ID в запросе)')
        );
    }

    ooofix_cloud_render_install_finish(
        (string)($result['message'] ?? 'Установка завершена')
            . (!empty($result['portal_id']) ? ' (portal #' . $result['portal_id'] . ')' : '')
    );
} catch (Throwable $e) {
    ooofix_cloud_render_install_error($e->getMessage());
}
