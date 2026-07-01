<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\App;

use Ooofix\XmlupdCloud\Core\Logger;
use Ooofix\XmlupdCloud\Rest\BitrixClient;
use Ooofix\XmlupdCloud\Storage\PortalRepository;

/** Полный цикл удаления приложения с портала */
final class UninstallService
{
    public function __construct(
        private readonly InstallService $install = new InstallService(),
        private readonly PortalRepository $portals = new PortalRepository(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function uninstall(string $domain, bool $purgeLocalData = true): array
    {
        $portal = $this->portals->findByDomain($domain);
        $portalId = $portal ? (int)$portal['ID'] : 0;

        $bitrixCleanup = false;
        if ($portal !== null && !empty($portal['ACCESS_TOKEN'])) {
            try {
                $this->install->uninstall(new BitrixClient($domain));
                $bitrixCleanup = true;
            } catch (\Throwable $e) {
                Logger::error('Uninstall Bitrix: ' . $e->getMessage());
            }
        }

        if ($purgeLocalData && $domain !== '') {
            $this->portals->purgeByDomain($domain);
        }

        return [
            'portal_id'      => $portalId,
            'bitrix_cleanup' => $bitrixCleanup,
            'data_purged'    => $purgeLocalData,
        ];
    }
}
