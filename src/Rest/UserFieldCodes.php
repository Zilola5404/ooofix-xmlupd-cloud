<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Rest;

/**
 * Коды UF по документации Bitrix24.
 *
 * Сделки: crm.deal.userfield.add (scope crm), FIELD_NAME = UF_UPD_*.
 * Смарт-процессы: userfieldconfig.add (scope userfieldconfig),
 * entityId = CRM_{id}, fieldName = UF_CRM_{id}_*.
 *
 * @see https://apidocs.bitrix24.ru/api-reference/crm/deals/user-defined-fields/crm-deal-userfield-add.html
 * @see https://apidocs.bitrix24.ru/api-reference/crm/universal/userfieldconfig/userfieldconfig/userfieldconfig-add.html
 */
final class UserFieldCodes
{
    public const DEAL_NUMBER = 'UF_UPD_NUMBER';
    public const DEAL_FILE   = 'UF_UPD_FILE';

    public const SUFFIX_NUMBER = 'UPD_NUMBER';
    public const SUFFIX_FILE   = 'UPD_FILE';

    public static function smartFieldName(int $spaId, string $suffix): string
    {
        return 'UF_CRM_' . $spaId . '_' . $suffix;
    }

    /** Ключ поля в crm.item.get / crm.item.update */
    public static function smartItemFieldKey(int $spaId, string $suffix): string
    {
        return 'ufCrm' . $spaId . '_' . $suffix;
    }

    public static function smartEntityId(int $spaId): string
    {
        return 'CRM_' . $spaId;
    }

    /**
     * @return array{
     *   entityTypeId: int,
     *   spaId: int,
     *   entityId: string,
     *   fieldNumber: string,
     *   fieldFile: string,
     *   itemFieldNumber: string,
     *   itemFieldFile: string
     * }|null
     */
    public static function resolveSmartType(BitrixClient $client, int $entityTypeId): ?array
    {
        if ($entityTypeId <= 0) {
            return null;
        }

        foreach (self::listTypes($client) as $type) {
            if ((int)($type['entityTypeId'] ?? 0) !== $entityTypeId) {
                continue;
            }

            $spaId = (int)($type['id'] ?? 0);
            if ($spaId <= 0) {
                return null;
            }

            return [
                'entityTypeId'    => $entityTypeId,
                'spaId'           => $spaId,
                'entityId'        => self::smartEntityId($spaId),
                'fieldNumber'     => self::smartFieldName($spaId, self::SUFFIX_NUMBER),
                'fieldFile'       => self::smartFieldName($spaId, self::SUFFIX_FILE),
                'itemFieldNumber' => self::smartItemFieldKey($spaId, self::SUFFIX_NUMBER),
                'itemFieldFile'   => self::smartItemFieldKey($spaId, self::SUFFIX_FILE),
            ];
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    public static function listTypes(BitrixClient $client): array
    {
        try {
            $result = $client->result('crm.type.list', []);
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($result)) {
            return [];
        }

        $types = $result['types'] ?? $result;

        return is_array($types) ? array_values(array_filter($types, 'is_array')) : [];
    }
}
