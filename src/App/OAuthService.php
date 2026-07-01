<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\App;

use Ooofix\XmlupdCloud\Storage\PortalRepository;
use Ooofix\XmlupdCloud\Storage\SettingsRepository;

/** OAuth: установка и удаление приложения */
final class OAuthService
{
    public const SETTING_OAUTH_SCOPE = 'oauth_scope';
    public const SETTING_APPLICATION_TOKEN = 'application_token';

    public function __construct(
        private readonly PortalRepository $portals = new PortalRepository(),
        private readonly SettingsRepository $settings = new SettingsRepository(),
    ) {
    }

    /** @param array<string, mixed> $request */
    public static function isServerOAuthEvent(array $request): bool
    {
        $event = self::resolveInstallEvent($request);

        return in_array($event, ['ONAPPUPDATE', 'ONAPPUNINSTALL'], true)
            && is_array($request['auth'] ?? null);
    }

    /**
     * Имя события из запроса B24. Если event не передан, но есть AUTH_ID — это установка.
     *
     * @param array<string, mixed> $request
     */
    public static function resolveInstallEvent(array $request): string
    {
        $event = strtoupper(trim((string)($request['event'] ?? $request['EVENT'] ?? '')));
        if ($event !== '') {
            return $event;
        }

        $auth = self::normalizeAuth($request);
        if ((string)($auth['access_token'] ?? '') !== '') {
            return 'ONAPPINSTALL';
        }

        return '';
    }

    /** @param array<string, mixed> $request */
    public static function hasInstallCredentials(array $request): bool
    {
        $event = self::resolveInstallEvent($request);
        if ($event === '') {
            $event = 'ONAPPINSTALL';
        }

        if ($event === 'ONAPPUNINSTALL') {
            return self::extractDomain(self::normalizeAuth($request), $request) !== '';
        }

        if ($event === 'ONAPPUPDATE') {
            $auth = self::normalizeAuth($request);

            return (string)($auth['access_token'] ?? '') !== '';
        }

        $auth = self::normalizeAuth($request);

        return (string)($auth['access_token'] ?? '') !== '';
    }

    /** @param array<string, mixed> $request */
    public function handleInstallEvent(array $request): int
    {
        $event = self::resolveInstallEvent($request);
        if ($event === '') {
            $event = 'ONAPPINSTALL';
        }

        $auth = self::normalizeAuth($request);
        $domain = self::extractDomain($auth, $request);

        if ($domain === '') {
            throw new \RuntimeException('Не удалось определить домен портала');
        }

        if ($event === 'ONAPPUNINSTALL') {
            $this->portals->deleteByDomain($domain);

            return 0;
        }

        $access = (string)($auth['access_token'] ?? '');
        if ($access === '') {
            throw new \RuntimeException('Пустой access_token при установке');
        }

        $wasInstalled = $this->portals->isInstalled($domain);

        return $this->savePortalTokens($domain, $auth, $wasInstalled);
    }

    /**
     * Сохранить OAuth-токены портала.
     *
     * @param array<string, mixed> $auth
     */
    public function savePortalTokens(string $domain, array $auth, bool $forceReplace = false): int
    {
        $access = (string)($auth['access_token'] ?? '');
        if ($access === '') {
            throw new \RuntimeException('Пустой access_token');
        }

        $existingId = $this->portals->findIdByDomain($domain);
        if ($existingId > 0 && !$forceReplace && !$this->shouldAcceptToken($existingId, $auth)) {
            return $existingId;
        }

        $expiresAt = null;
        $expiresIn = (int)($auth['expires_in'] ?? 0);
        if ($expiresIn > 0) {
            $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
        }

        $data = [
            'member_id'     => $auth['member_id'] ?? null,
            'access_token'  => $access,
            'refresh_token' => (string)($auth['refresh_token'] ?? ''),
            'expires_at'    => $expiresAt,
        ];

        $portalId = $forceReplace
            ? $this->portals->replaceTokens($domain, $data)
            : $this->portals->upsert($domain, $data);

        $this->persistOAuthScope($portalId, $auth);

        return $portalId;
    }

    /** @param array<string, mixed> $auth */
    public function storeApplicationToken(int $portalId, array $auth): void
    {
        if ($portalId <= 0) {
            return;
        }

        $token = trim((string)($auth['application_token'] ?? ''));
        if ($token === '') {
            return;
        }

        $this->settings->saveAll($portalId, [self::SETTING_APPLICATION_TOKEN => $token]);
    }

    /** @param array<string, mixed> $request */
    public function syncFromRequest(array $request): ?int
    {
        $auth = self::normalizeAuth($request);
        $domain = self::extractDomain($auth, $request);

        if ($domain === '' || ($auth['access_token'] ?? '') === '') {
            return null;
        }

        $existingId = $this->portals->findIdByDomain($domain);
        if ($existingId > 0 && !$this->shouldAcceptToken($existingId, $auth)) {
            return $existingId;
        }

        return $this->savePortalTokens($domain, $auth, false);
    }

    /** @param array<string, mixed> $request */
    public function ensurePortal(array $request): int
    {
        $portalId = $this->syncFromRequest($request);
        if ($portalId !== null && $portalId > 0) {
            return $portalId;
        }

        $auth = self::normalizeAuth($request);
        $domain = self::extractDomain($auth, $request);
        $token = (string)($auth['access_token'] ?? '');

        if ($domain !== '' && $token !== '') {
            return $this->savePortalTokens($domain, $auth, false);
        }

        $memberId = (string)($auth['member_id'] ?? $request['member_id'] ?? '');
        if ($memberId !== '') {
            $existing = $this->portals->findByMemberId($memberId);
            if ($existing !== null) {
                if ($token !== '' && $this->shouldAcceptToken(PortalRepository::rowId($existing), $auth)) {
                    $this->savePortalTokens((string)$existing['DOMAIN'], $auth, false);
                }

                return $this->portals->assertPositivePortalId(
                    PortalRepository::rowId($existing),
                    (string)($existing['DOMAIN'] ?? $domain)
                );
            }
        }

        if ($domain !== '') {
            $existingId = $this->portals->findIdByDomain($domain);
            if ($existingId > 0) {
                return $existingId;
            }
        }

        if ($domain === '') {
            throw new \RuntimeException('Не передан DOMAIN портала');
        }

        if ($token === '') {
            throw new \RuntimeException(
                'Не передан AUTH_ID. Откройте приложение из меню Bitrix24 или переустановите приложение.'
            );
        }

        throw new \RuntimeException('Портал не установлен. Установите приложение в Bitrix24.');
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public static function normalizeAuth(array $request): array
    {
        $auth = $request['auth'] ?? [];
        if (!is_array($auth)) {
            $auth = [];
        }

        $flatMap = [
            'AUTH_ID'      => 'access_token',
            'REFRESH_ID'   => 'refresh_token',
            'AUTH_EXPIRES' => 'expires_in',
            'DOMAIN'       => 'domain',
            'MEMBER_ID'    => 'member_id',
            'member_id'    => 'member_id',
            'scope'        => 'scope',
        ];

        foreach ($flatMap as $flatKey => $authKey) {
            if (isset($request[$flatKey]) && $request[$flatKey] !== '' && empty($auth[$authKey])) {
                $auth[$authKey] = $request[$flatKey];
            }
        }

        if (empty($auth['access_token']) && !empty($auth['AUTH_ID'])) {
            $auth['access_token'] = $auth['AUTH_ID'];
        }
        if (empty($auth['refresh_token']) && !empty($auth['REFRESH_ID'])) {
            $auth['refresh_token'] = $auth['REFRESH_ID'];
        }
        if (empty($auth['expires_in']) && isset($auth['AUTH_EXPIRES']) && $auth['AUTH_EXPIRES'] !== '') {
            $auth['expires_in'] = $auth['AUTH_EXPIRES'];
        }
        if (empty($auth['domain']) && !empty($request['DOMAIN'])) {
            $auth['domain'] = $request['DOMAIN'];
        }

        return $auth;
    }

    /** @param array<string, mixed> $auth */
    /** @param array<string, mixed> $request */
    public static function extractDomainPublic(array $auth, array $request): string
    {
        return self::extractDomain($auth, $request);
    }

    /** @param array<string, mixed> $auth */
    /** @param array<string, mixed> $request */
    private static function extractDomain(array $auth, array $request): string
    {
        $domain = (string)($auth['domain'] ?? $auth['DOMAIN'] ?? $request['DOMAIN'] ?? $request['domain'] ?? '');
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;

        return rtrim($domain, '/');
    }

    /** @param array<string, mixed> $auth */
    private function shouldAcceptToken(int $portalId, array $auth): bool
    {
        $newScopes = self::parseScopeString((string)($auth['scope'] ?? ''));
        if ($newScopes === []) {
            return true;
        }

        $stored = $this->settings->get($portalId, self::SETTING_OAUTH_SCOPE, '');
        $existingScopes = self::parseScopeString($stored);
        if ($existingScopes === []) {
            return true;
        }

        $required = AppScopes::REST;
        $newScore = self::scopeCoverageScore($newScopes, $required);
        $oldScore = self::scopeCoverageScore($existingScopes, $required);

        return $newScore >= $oldScore;
    }

    /** @param list<string> $scopes */
    /** @param list<string> $required */
    private static function scopeCoverageScore(array $scopes, array $required): int
    {
        $map = array_fill_keys($scopes, true);
        $score = 0;
        foreach ($required as $scope) {
            if (isset($map[strtolower($scope)])) {
                $score++;
            }
        }

        return $score;
    }

    /** @param array<string, mixed> $auth */
    public function persistOAuthScopeFromAuth(int $portalId, array $auth): void
    {
        $this->persistOAuthScope($portalId, $auth);
    }

    /** @param array<string, mixed> $auth */
    private function persistOAuthScope(int $portalId, array $auth): void
    {
        if ($portalId <= 0) {
            return;
        }

        $scope = trim((string)($auth['scope'] ?? ''));
        if ($scope === '') {
            return;
        }

        $this->settings->saveAll($portalId, [self::SETTING_OAUTH_SCOPE => $scope]);
    }

    /** @return list<string> */
    public static function parseScopeString(string $scope): array
    {
        $parts = preg_split('/[\s,]+/', strtolower(trim($scope))) ?: [];
        $scopes = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $scopes[] = $part;
            }
        }

        sort($scopes);

        return array_values(array_unique($scopes));
    }
}
