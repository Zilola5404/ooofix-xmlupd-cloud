<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\App;

/** HTML страниц портала: абсолютные URL ресурсов (без base href) для iframe Bitrix24 */
final class PageRenderer
{
    /** @var array<string, string> */
    private const PAGES = [
        'settings'  => 'settings.html',
        'documents' => 'documents.html',
        'logs'      => 'logs.html',
    ];

    /** @var array<string, string> */
    private const PAGE_SUFFIX = [
        'settings'  => 'настройки',
        'documents' => 'документы',
        'logs'      => 'логи',
    ];

    public static function render(string $pageKey): never
    {
        $fileName = self::PAGES[$pageKey] ?? self::PAGES['settings'];
        $root = defined('OOOFIX_CLOUD_ROOT') ? OOOFIX_CLOUD_ROOT : dirname(__DIR__, 2);
        $path = $root . '/public/frontend/' . $fileName;

        if (!is_file($path)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Страница не найдена';
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo self::prepare((string)file_get_contents($path), $pageKey);
        exit;
    }

    private static function prepare(string $html, string $pageKey): string
    {
        $appUrl = rtrim(AppConfig::appUrl(), '/');
        $ver = AssetVersion::get();
        $title = AppTitle::get();
        $suffix = self::PAGE_SUFFIX[$pageKey] ?? 'настройки';

        $html = preg_replace('#<base[^>]*>\s*#i', '', $html) ?? $html;

        $html = preg_replace(
            '/<title>[^<]*<\/title>/i',
            '<title>' . htmlspecialchars($title . ' — ' . $suffix, ENT_QUOTES, 'UTF-8') . '</title>',
            $html,
            1
        ) ?? $html;

        $html = str_replace(
            "window.OX_CLOUD_APP_TITLE = 'Генерация XML (УПД)';",
            'window.OX_CLOUD_APP_TITLE = ' . json_encode($title, JSON_UNESCAPED_UNICODE) . ';',
            $html
        );

        $html = preg_replace_callback(
            '#(href|src)="frontend/([^"]+)"#',
            static fn (array $m): string => $m[1] . '="' . $appUrl . '/frontend/' . $m[2]
                . '?v=' . rawurlencode($ver) . '"',
            $html
        ) ?? $html;

        $html = str_replace('href="index.php', 'href="' . $appUrl . '/index.php', $html);

        $meta = '<meta name="ox-cloud-build" content="' . htmlspecialchars($ver, ENT_QUOTES, 'UTF-8') . '">'
            . '<meta name="ox-cloud-title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">'
            . '<script>window.OX_CLOUD_API_BASE=' . json_encode($appUrl, JSON_UNESCAPED_UNICODE) . ';</script>';

        if (AppBranding::logoExists()) {
            $icon = htmlspecialchars(AppBranding::logoUrl(), ENT_QUOTES, 'UTF-8');
            $meta .= '<link rel="icon" type="image/png" href="' . $icon . '">';
        }

        $html = preg_replace('/<head>/i', '<head>' . $meta, $html, 1) ?? $html;

        return $html;
    }
}
