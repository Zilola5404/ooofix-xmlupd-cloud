<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\App;

use Ooofix\XmlupdCloud\Core\Config;
use Ooofix\XmlupdCloud\Core\Logger;
use Ooofix\XmlupdCloud\Core\Tenant;
use Ooofix\XmlupdCloud\App\Http;
use Ooofix\XmlupdCloud\App\OAuthService;
use Ooofix\XmlupdCloud\Rest\BitrixClient;
use Ooofix\XmlupdCloud\Storage\PortalRepository;

/** OAuth + Tenant для API и handler/*.php */
final class PortalBootstrap
{
    /**
     * @return array{
     *   0: array<string, mixed>,
     *   1: BitrixClient,
     *   2: int,
     *   3: string,
     *   4: string
     * }
     */
    public static function boot(): array
    {
        $requestId = Logger::generateRequestId();
        $request = Http::request();
        $oauth = new OAuthService();
        $portals = new PortalRepository();
        $portalId = $oauth->ensurePortal($request);

        $portal = $portals->findById($portalId);
        $domain = is_array($portal) && ($portal['DOMAIN'] ?? '') !== ''
            ? (string)$portal['DOMAIN']
            : Http::domainFromRequest($request);

        $client = BitrixClient::fromInstallAuth($domain, OAuthService::normalizeAuth($request));

        Tenant::bind($portalId, $domain, $requestId);
        Config::bindPortal($portalId);

        return [$request, $client, $portalId, $domain, $requestId];
    }
}
