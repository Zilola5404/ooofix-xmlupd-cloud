<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\App;

use Ooofix\XmlupdCloud\Rest\BitrixClient;
use Ooofix\XmlupdCloud\Rest\RestPermissionsDiagnostic;
use Ooofix\XmlupdCloud\Storage\PortalRepository;
use Ooofix\XmlupdCloud\Storage\SettingsRepository;

/** Серверные события Bitrix24: ONAPPINSTALL / ONAPPUPDATE / ONAPPUNINSTALL */
final class OAuthEventHandler
{
    public function __construct(
        private readonly OAuthService $oauth = new OAuthService(),
        private readonly PortalRepository $portals = new PortalRepository(),
        private readonly SettingsRepository $settings = new SettingsRepository(),
        private readonly InstallService $install = new InstallService(),
        private readonly UninstallService $uninstall = new UninstallService(),
    ) {
    }

    /** @param array<string, mixed> $request */
    public function dispatch(array $request): ?array
    {
        $event = OAuthService::resolveInstallEvent($request);
        if ($event === '') {
            return null;
        }

        $request['event'] = $event;

        return match ($event) {
            'ONAPPUPDATE'    => $this->onAppUpdate($request),
            'ONAPPINSTALL'   => $this->onAppInstall($request),
            'ONAPPUNINSTALL' => $this->onAppUninstall($request),
            default          => null,
        };
    }

    /** @return array<string, mixed> */
    public function onAppUpdate(array $request): array
    {
        $auth = OAuthService::normalizeAuth($request);
        $domain = OAuthService::extractDomainPublic($auth, $request);
        $access = (string)($auth['access_token'] ?? '');

        if ($domain === '' || $access === '') {
            throw new \RuntimeException('ONAPPUPDATE: нет domain или access_token в auth');
        }

        $portalId = $this->oauth->savePortalTokens($domain, $auth, true);
        $this->oauth->storeApplicationToken($portalId, $auth);

        $client = BitrixClient::fromInstallAuth($domain, $auth);
        $warnings = $this->install->install($client);
        $report = (new RestPermissionsDiagnostic($client, $portalId))->run();

        $version = '';
        if (isset($request['data']) && is_array($request['data'])) {
            $version = (string)($request['data']['VERSION'] ?? '');
        }

        return [
            'event'      => 'ONAPPUPDATE',
            'portal_id'  => $portalId,
            'domain'     => $domain,
            'version'    => $version,
            'warnings'   => $warnings,
            'diagnostic' => $report,
            'message'    => $this->buildMessage('Приложение обновлено', $report, $version),
        ];
    }

    /** @return array<string, mixed> */
    public function onAppInstall(array $request): array
    {
        $auth = OAuthService::normalizeAuth($request);
        $domain = OAuthService::extractDomainPublic($auth, $request);
        $access = (string)($auth['access_token'] ?? '');

        if ($domain === '') {
            throw new \RuntimeException('ONAPPINSTALL: не удалось определить домен портала');
        }

        if ($access === '') {
            $portalId = $this->portals->findIdByDomain($domain);
            if ($portalId > 0) {
                $this->oauth->storeApplicationToken($portalId, $auth);
            }

            return [
                'event'     => 'ONAPPINSTALL',
                'portal_id' => $portalId,
                'domain'    => $domain,
                'message'   => 'Сохранён application_token (OAuth-токены придут из BX24.getAuth или ONAPPUPDATE)',
            ];
        }

        $wasInstalled = $this->portals->isInstalled($domain);
        $portalId = $this->oauth->savePortalTokens($domain, $auth, $wasInstalled);
        $this->oauth->storeApplicationToken($portalId, $auth);

        $client = BitrixClient::fromInstallAuth($domain, $auth);

        if ($wasInstalled) {
            try {
                $this->install->uninstall($client);
            } catch (\Throwable) {
            }
        }

        $warnings = $this->install->install($client);
        $report = (new RestPermissionsDiagnostic($client, $portalId))->run();

        return [
            'event'      => 'ONAPPINSTALL',
            'portal_id'  => $portalId,
            'domain'     => $domain,
            'reinstall'  => $wasInstalled,
            'warnings'   => $warnings,
            'diagnostic' => $report,
            'message'    => $this->buildMessage(
                $wasInstalled ? 'Приложение переустановлено' : 'Приложение установлено',
                $report,
            ),
        ];
    }

    /** @return array<string, mixed> */
    public function onAppUninstall(array $request): array
    {
        $auth = OAuthService::normalizeAuth($request);
        $domain = OAuthService::extractDomainPublic($auth, $request);

        if ($domain === '') {
            throw new \RuntimeException('ONAPPUNINSTALL: не удалось определить домен портала');
        }

        $result = $this->uninstall->uninstall($domain, purgeLocalData: true);
        $this->oauth->handleInstallEvent($request);

        return array_merge($result, [
            'event'   => 'ONAPPUNINSTALL',
            'domain'  => $domain,
            'message' => 'Приложение удалено',
        ]);
    }

    /** @param array<string, mixed> $report */
    private function buildMessage(string $prefix, array $report, string $version = ''): string
    {
        $message = $prefix;
        if ($version !== '') {
            $message .= ' (версия ' . $version . ')';
        }

        if (!($report['ready_for_generation'] ?? false)) {
            $message .= "\n\n" . RestPermissionsDiagnostic::formatInstallText($report);
        }

        return $message;
    }
}
