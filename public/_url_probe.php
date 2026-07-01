<?php

declare(strict_types=1);

/**
 * Ответ на проверку URL кабинетом vendors.bitrix24.ru (HEAD / GET без контекста B24).
 */
function ooofix_cloud_has_bitrix_context(): bool
{
    foreach (['AUTH_ID', 'APP_SID', 'DOMAIN', 'member_id', 'event', 'application_token'] as $key) {
        if (!empty($_REQUEST[$key])) {
            return true;
        }
    }

    if (isset($_REQUEST['auth']) && is_array($_REQUEST['auth']) && $_REQUEST['auth'] !== []) {
        return true;
    }

    $raw = file_get_contents('php://input');
    if (is_string($raw) && $raw !== '' && str_contains($raw, '"auth"')) {
        return true;
    }

    return false;
}

function ooofix_cloud_app_title(): string
{
    static $title = null;
    if ($title !== null) {
        return $title;
    }

    $init = dirname(__DIR__) . '/init.php';
    if (is_file($init)) {
        require_once $init;
        $title = \Ooofix\XmlupdCloud\App\AppConfig::appTitle();
    } else {
        $title = 'Генерация XML (УПД)';
    }

    return $title;
}

function ooofix_cloud_install_set_title_js(): string
{
    $title = htmlspecialchars(ooofix_cloud_app_title(), ENT_QUOTES, 'UTF-8');

    return <<<JS
    if (typeof BX24.setTitle === 'function') {
        BX24.setTitle('{$title}');
    }
JS;
}

function ooofix_cloud_url_probe_exit_if_needed(bool $bareGetOkPage = true): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'HEAD') {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Ooofix-Probe: head-ok');
        exit;
    }

    if ($bareGetOkPage && $method === 'GET' && !ooofix_cloud_has_bitrix_context()) {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>'
            . htmlspecialchars(ooofix_cloud_app_title(), ENT_QUOTES, 'UTF-8')
            . '</title></head>'
            . '<body>OK — REST-приложение «' . htmlspecialchars(ooofix_cloud_app_title(), ENT_QUOTES, 'UTF-8') . '» доступно.</body></html>';
        exit;
    }
}

function ooofix_cloud_render_install_client(): never
{
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    $title = htmlspecialchars(ooofix_cloud_app_title(), ENT_QUOTES, 'UTF-8');
    $setTitleJs = ooofix_cloud_install_set_title_js();
    echo <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <script src="https://api.bitrix24.com/api/v1/"></script>
</head>
<body>
<p id="msg">Установка «{$title}»…</p>
<script>
BX24.init(function () {
    {$setTitleJs}
    var auth = BX24.getAuth();
    if (!auth || !auth.access_token) {
        document.getElementById('msg').textContent = 'Не удалось получить токен авторизации (BX24.getAuth).';
        return;
    }
    var body = new URLSearchParams({
        event: 'ONAPPINSTALL',
        AUTH_ID: auth.access_token,
        REFRESH_ID: auth.refresh_token || '',
        AUTH_EXPIRES: String(auth.expires_in || ''),
        DOMAIN: auth.domain || '',
        member_id: auth.member_id || ''
    });
    if (auth.scope) {
        body.set('scope', auth.scope);
    }
    fetch(location.href.split('?')[0], {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(function (r) {
        if (!r.ok) {
            throw new Error('HTTP ' + r.status);
        }
        return r.text();
    })
    .then(function (html) {
        if (html.indexOf('Ошибка установки') !== -1) {
            document.open();
            document.write(html);
            document.close();
            return;
        }
        document.getElementById('msg').textContent = '«{$title}» установлено';
        {$setTitleJs}
        BX24.installFinish();
    })
    .catch(function (e) {
        document.getElementById('msg').textContent = 'Ошибка: ' + e.message;
    });
});
</script>
</body>
</html>
HTML;
    exit;
}

function ooofix_cloud_render_install_finish(string $message = ''): never
{
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    $appTitle = ooofix_cloud_app_title();
    $text = htmlspecialchars($message !== '' ? $message : 'Установка завершена', ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars($appTitle, ENT_QUOTES, 'UTF-8');
    $setTitleJs = ooofix_cloud_install_set_title_js();
    echo <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <script src="https://api.bitrix24.com/api/v1/"></script>
</head>
<body>
<pre id="msg" style="white-space:pre-wrap;font-family:inherit;margin:12px">{$text}</pre>
<script>
BX24.init(function () {
    {$setTitleJs}
    BX24.installFinish();
});
</script>
</body>
</html>
HTML;
    exit;
}

function ooofix_cloud_render_install_error(string $message): never
{
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    $text = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars(ooofix_cloud_app_title(), ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>{$title} — ошибка установки</title>
</head>
<body>
<h1>{$title}</h1>
<p>Ошибка установки: {$text}</p>
</body>
</html>
HTML;
    exit;
}
