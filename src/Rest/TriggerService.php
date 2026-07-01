<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Rest;

use Ooofix\XmlupdCloud\App\TriggerRegistry;

/** Запуск CRM-триггеров через REST */
final class TriggerService
{
    public function __construct(
        private readonly BitrixClient $client,
    ) {
    }

    public function fireUpdGenerated(string $entityType, int $entityId, int $entityTypeId): void
    {
        $this->execute(TriggerRegistry::CODE_UPD_GENERATED, $entityTypeId, $entityId);
    }

    public function execute(string $code, int $ownerTypeId, int $ownerId): void
    {
        if ($ownerTypeId <= 0 || $ownerId <= 0) {
            return;
        }

        try {
            $this->client->call('crm.automation.trigger', [
                'code'          => $code,
                'ownerTypeId'   => $ownerTypeId,
                'ownerId'       => $ownerId,
            ]);
        } catch (\Throwable) {
            try {
                $this->client->call('crm.automation.trigger.execute', [
                    'CODE'          => $code,
                    'OWNER_TYPE_ID' => $ownerTypeId,
                    'OWNER_ID'      => $ownerId,
                ]);
            } catch (\Throwable) {
            }
        }
    }
}
