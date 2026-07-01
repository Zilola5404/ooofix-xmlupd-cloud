<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Rest;

use Ooofix\XmlupdCloud\Core\Config;

/** Запись файла УПД в UF_UPD_FILE через crm.item.update (рекомендация Bitrix24 для файловых полей). */
final class CrmUserFieldAttacher
{
    public function attachUpdFile(
        BitrixClient $client,
        string $entityType,
        int $entityId,
        int $entityTypeId,
        string $fileName,
        string $fileContent,
        string $docNumber = '',
    ): void {
        if ($entityId <= 0) {
            throw new \RuntimeException('Не указан ID сущности CRM для записи UF_UPD_FILE');
        }

        $entityTypeId = $this->resolveEntityTypeId($entityType, $entityTypeId);
        [$fileKey, $numberKey, $useOriginalNames] = $this->resolveFieldKeys($entityType, $entityTypeId);

        $fields = [
            $fileKey => [$fileName, base64_encode($fileContent)],
        ];
        if ($docNumber !== '') {
            $fields[$numberKey] = $docNumber;
        }

        $params = [
            'entityTypeId' => $entityTypeId,
            'id'           => $entityId,
            'fields'       => $fields,
        ];
        if ($useOriginalNames) {
            $params['useOriginalUfNames'] = 'Y';
        }

        $client->call('crm.item.update', $params);
    }

    private function resolveEntityTypeId(string $entityType, int $entityTypeId): int
    {
        if ($entityTypeId > 0) {
            return $entityTypeId;
        }

        if ($entityType === RestDataCollector::TYPE_DEAL) {
            return BitrixClient::dealTypeId();
        }

        $typeId = Config::smartInvoiceTypeId();

        return $typeId > 0 ? $typeId : $entityTypeId;
    }

    /**
     * @return array{0: string, 1: string, 2: bool} [fileKey, numberKey, useOriginalUfNames]
     */
    private function resolveFieldKeys(string $entityType, int $entityTypeId): array
    {
        if ($entityType === RestDataCollector::TYPE_DEAL) {
            return [UserFieldCodes::DEAL_FILE, UserFieldCodes::DEAL_NUMBER, true];
        }

        $spaId = Config::smartInvoiceSpaId();
        if ($spaId <= 0) {
            throw new \RuntimeException(
                'Не задан smart_invoice_spa_id в настройках для записи UF_UPD_FILE в смарт-процессе'
            );
        }

        return [
            UserFieldCodes::smartItemFieldKey($spaId, UserFieldCodes::SUFFIX_FILE),
            UserFieldCodes::smartItemFieldKey($spaId, UserFieldCodes::SUFFIX_NUMBER),
            false,
        ];
    }
}
