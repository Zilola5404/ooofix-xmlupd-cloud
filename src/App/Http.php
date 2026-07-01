<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\App;

use Ooofix\XmlupdCloud\Rest\BitrixClient;

/** Вспомогательные функции для public/*.php */
final class Http
{
    /** @var array<string, mixed>|null */
    private static ?array $jsonInputCache = null;

    /** @var array<string, mixed>|null */
    private static ?array $requestCache = null;

    /** @return array<string, mixed> */
    public static function jsonInput(): array
    {
        if (self::$jsonInputCache !== null) {
            return self::$jsonInputCache;
        }

        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '') {
            self::$jsonInputCache = $_POST;

            return self::$jsonInputCache;
        }

        $data = json_decode($raw, true);
        self::$jsonInputCache = is_array($data) ? $data : [];

        return self::$jsonInputCache;
    }

    /** @return array<string, mixed> */
    public static function request(): array
    {
        if (self::$requestCache !== null) {
            return self::$requestCache;
        }

        self::$requestCache = array_merge($_GET, $_POST, self::jsonInput());

        if (isset(self::$requestCache['auth']) && is_string(self::$requestCache['auth'])) {
            $decoded = json_decode(self::$requestCache['auth'], true);
            if (is_array($decoded)) {
                self::$requestCache['auth'] = $decoded;
            }
        }

        return self::$requestCache;
    }

    public static function jsonResponse(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @return array<string, mixed> */
    public static function installRequest(): array
    {
        return self::request();
    }

    /** @param array<string, mixed>|null $request */
    public static function domainFromRequest(?array $request = null): string
    {
        $request = $request ?? self::request();
        $auth = OAuthService::normalizeAuth($request);
        $domain = (string)($auth['domain'] ?? $request['DOMAIN'] ?? $request['domain'] ?? '');
        if ($domain === '' && isset($request['auth']) && is_array($request['auth'])) {
            $domain = (string)($request['auth']['domain'] ?? '');
        }

        return self::normalizeDomain($domain);
    }

    private static function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;

        return rtrim($domain, '/');
    }

    public static function clientFromRequest(?array $request = null): BitrixClient
    {
        $request = $request ?? self::request();
        $domain = self::domainFromRequest($request);
        if ($domain === '') {
            throw new \RuntimeException('Не передан DOMAIN портала');
        }

        return BitrixClient::fromInstallAuth($domain, OAuthService::normalizeAuth($request));
    }

    public static function currentUserIdFromRequest(): int
    {
        return (int)($_REQUEST['USER_ID'] ?? $_REQUEST['user_id'] ?? 0);
    }
}
