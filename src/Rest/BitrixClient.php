<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Rest;

use Ooofix\XmlupdCloud\App\AppConfig;
use Ooofix\XmlupdCloud\App\AppScopes;
use Ooofix\XmlupdCloud\App\OAuthService;
use Ooofix\XmlupdCloud\Core\Tenant;
use Ooofix\XmlupdCloud\Storage\PortalRepository;
use Ooofix\XmlupdCloud\Storage\SettingsRepository;

/**
 * REST-клиент Bitrix24 с автообновлением OAuth-токена.
 */
final class BitrixClient
{
    private const CRM_DEAL_TYPE_ID = 2;
    private const CRM_COMPANY_TYPE_ID = 4;

    private ?string $sessionAccessToken = null;
    private ?string $sessionRefreshToken = null;

    public function __construct(
        private readonly string $domain,
        private readonly PortalRepository $portals = new PortalRepository(),
    ) {
    }

    /**
     * REST во время установки: токен из запроса B24 (надёжнее, чем только чтение из БД).
     *
     * @param array<string, mixed> $auth результат OAuthService::normalizeAuth()
     */
    public static function fromInstallAuth(string $domain, array $auth): self
    {
        $client = new self($domain);
        $client->sessionAccessToken = (string)($auth['access_token'] ?? '');
        $client->sessionRefreshToken = (string)($auth['refresh_token'] ?? '');

        return $client;
    }

    public function domain(): string
    {
        return $this->domain;
    }

    public function hasSessionToken(): bool
    {
        return $this->sessionAccessToken !== null && $this->sessionAccessToken !== '';
    }

    public function portalId(): int
    {
        $tenant = Tenant::tryCurrent();
        if ($tenant !== null) {
            return $tenant->portalId;
        }

        return $this->portals->findIdByDomain($this->domain);
    }

    /** @param array<string, mixed> $params */
    public function call(string $method, array $params = []): array
    {
        $token = $this->sessionAccessToken ?? '';
        $portal = null;

        if ($token === '') {
            $portal = $this->portals->findByDomain($this->domain);
            if ($portal === null) {
                throw new \RuntimeException('Портал не установлен: ' . $this->domain);
            }

            $token = (string)($portal['ACCESS_TOKEN'] ?? '');
        }

        if ($token === '') {
            throw new \RuntimeException('Отсутствует access_token для ' . $this->domain);
        }

        $response = $this->request($method, $params, $token);

        if (($response['error'] ?? '') === 'expired_token') {
            $portal ??= $this->portals->findByDomain($this->domain);
            if ($portal === null) {
                throw new \RuntimeException('Портал не установлен: ' . $this->domain);
            }
            $token = $this->refreshToken($portal);
            $this->sessionAccessToken = $token;
            $response = $this->request($method, $params, $token);
        }

        if (isset($response['error'])) {
            $desc = (string)($response['error_description'] ?? $response['error']);
            if (AppScopes::isPrivilegeError(new \RuntimeException($desc))) {
                $desc .= '. ' . AppScopes::vendorHint();
            }

            throw new \RuntimeException('REST ' . $method . ': ' . $desc);
        }

        return $response;
    }

    /**
     * Проверка метода без исключения и без длинной подсказки vendors (для диагностики).
     *
     * @param array<string, mixed> $params
     * @return array{ok: bool, error?: string, error_code?: string, result?: mixed}
     */
    public function probe(string $method, array $params = []): array
    {
        try {
            $response = $this->call($method, $params);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (str_starts_with($message, 'REST ' . $method . ': ')) {
                $message = substr($message, strlen('REST ' . $method . ': '));
            }
            $message = preg_replace('/\.\s*На vendors\.bitrix24\.ru.*$/su', '', $message) ?? $message;

            return [
                'ok'    => false,
                'error' => trim($message),
            ];
        }

        return [
            'ok'     => true,
            'result' => $response['result'] ?? null,
        ];
    }

    /** @return mixed */
    public function result(string $method, array $params = []): mixed
    {
        return $this->call($method, $params)['result'] ?? null;
    }

    public static function dealTypeId(): int
    {
        return self::CRM_DEAL_TYPE_ID;
    }

    public static function companyTypeId(): int
    {
        return self::CRM_COMPANY_TYPE_ID;
    }

    /** @param array<string, mixed> $params */
    private function request(string $method, array $params, string $token): array
    {
        $url = 'https://' . $this->domain . '/rest/' . $method . '.json';
        $params['auth'] = $token;

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl_init failed');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0 || !is_string($raw)) {
            throw new \RuntimeException('Ошибка HTTP при вызове ' . $method);
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : ['error' => 'invalid_response'];
    }

    /** @param array<string, mixed> $portal */
    private function refreshToken(array $portal): string
    {
        $refresh = (string)($portal['REFRESH_TOKEN'] ?? $this->sessionRefreshToken ?? '');
        if ($refresh === '') {
            throw new \RuntimeException('Нет refresh_token. Переустановите приложение.');
        }

        $query = http_build_query([
            'grant_type'    => 'refresh_token',
            'client_id'     => AppConfig::clientId(),
            'client_secret' => AppConfig::clientSecret(),
            'refresh_token' => $refresh,
        ]);

        $url = 'https://oauth.bitrix.info/oauth/token/?' . $query;
        $raw = file_get_contents($url);
        if ($raw === false) {
            throw new \RuntimeException('Не удалось обновить токен OAuth');
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['access_token'])) {
            throw new \RuntimeException('Некорректный ответ OAuth при обновлении токена');
        }

        $expiresAt = null;
        if (!empty($data['expires_in'])) {
            $expiresAt = date('Y-m-d H:i:s', time() + (int)$data['expires_in']);
        }

        $this->portals->upsert($this->domain, [
            'member_id'     => $portal['MEMBER_ID'] ?? null,
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $refresh,
            'expires_at'    => $expiresAt,
        ]);

        if (!empty($data['scope'])) {
            (new \Ooofix\XmlupdCloud\App\OAuthService())->persistOAuthScopeFromAuth(
                $this->portals->findIdByDomain($this->domain),
                ['scope' => (string)$data['scope']],
            );
        }

        $this->sessionAccessToken = (string)$data['access_token'];
        $this->sessionRefreshToken = (string)($data['refresh_token'] ?? $refresh);

        return (string)$data['access_token'];
    }

    /** Принудительно обновить OAuth-токен и вернуть scope из ответа oauth.bitrix.info */
    /** @return array{scope: string, token_hint: string} */
    public function forceRefreshToken(): array
    {
        $portal = $this->portals->findByDomain($this->domain);
        if ($portal === null) {
            throw new \RuntimeException('Портал не установлен: ' . $this->domain);
        }

        $token = $this->refreshToken($portal);

        return [
            'scope'      => (new SettingsRepository())->get(
                $this->portals->findIdByDomain($this->domain),
                OAuthService::SETTING_OAUTH_SCOPE,
                '',
            ),
            'token_hint' => substr($token, 0, 8) . '…',
        ];
    }
}
