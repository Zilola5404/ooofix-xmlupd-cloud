<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\App;

/** Страницы placement (кнопка в карточке CRM) — без кэша, абсолютные URL статики */
final class PlacementRenderer
{
    public static function renderDealButton(string $entityType): never
    {
        $entityType = in_array($entityType, ['deal', 'smart_invoice'], true) ? $entityType : 'deal';
        $appUrl = rtrim(AppConfig::appUrl(), '/');
        $ver = AssetVersion::get();
        $handlerUrl = $appUrl . '/handler/button.php';

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        $vEsc = htmlspecialchars($ver, ENT_QUOTES, 'UTF-8');
        $apiAuth = $appUrl . '/frontend/api-auth.js?v=' . rawurlencode($ver);
        $genJs = $appUrl . '/frontend/generate-button.js?v=' . rawurlencode($ver);
        $jsonApp = self::json($appUrl);
        $jsonHandler = self::json($handlerUrl);
        $jsonEntity = self::json($entityType);
        $jsonVer = self::json($ver);

        echo <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="ox-cloud-build" content="{$vEsc}">
    <title>Сформировать УПД</title>
    <script src="https://api.bitrix24.com/api/v1/"></script>
    <script>
        window.OX_CLOUD_API_BASE = {$jsonApp};
        window.OX_GEN_HANDLER_URL = {$jsonHandler};
        window.OX_GEN_ENTITY = {$jsonEntity};
        window.OX_GEN_BUILD = {$jsonVer};
    </script>
    <style>
        body.ox-gen-page {
            margin: 0;
            padding: 14px 16px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #fff;
            color: #333;
        }
        .ox-gen-msg { font-size: 14px; line-height: 1.45; }
        .ox-gen-msg--error { color: #c0392b; }
        .ox-gen-msg--ok { color: #27ae60; }
    </style>
</head>
<body class="ox-gen-page">
<div class="ox-gen-msg" id="msg">Загрузка…</div>
<script src="{$apiAuth}"></script>
<script src="{$genJs}"></script>
</body>
</html>
HTML;
        exit;
    }

    private static function json(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '""';
    }
}
