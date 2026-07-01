<?php

declare(strict_types=1);

/**
 * Главная страница приложения (настройки) — URL в левом меню B24.
 */
require __DIR__ . '/_url_probe.php';
ooofix_cloud_url_probe_exit_if_needed(bareGetOkPage: false);

require __DIR__ . '/init.php';

use Ooofix\XmlupdCloud\App\Http;
use Ooofix\XmlupdCloud\App\OAuthService;
use Ooofix\XmlupdCloud\App\PageRenderer;

try {
    (new OAuthService())->ensurePortal(Http::request());
} catch (Throwable) {
}

$page = (string)($_GET['page'] ?? 'settings');
if (!in_array($page, ['settings', 'documents', 'logs'], true)) {
    $page = 'settings';
}

PageRenderer::render($page);
