<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Rest;

/**
 * Коды placement по документации placement.list.
 *
 * @see https://apidocs.bitrix24.ru/api-reference/widgets/placement-list.html
 */
final class PlacementRegistry
{
    public const DEAL_TOOLBAR = 'CRM_DEAL_DETAIL_TOOLBAR';
    public const SMART_INVOICE_TOOLBAR = 'CRM_SMART_INVOICE_DETAIL_TOOLBAR';
    public const LEFT_MENU = 'LEFT_MENU';

    /**
     * @return list<string>
     */
    public static function toolbarCandidates(?array $smartMeta): array
    {
        $codes = [self::DEAL_TOOLBAR];

        if ($smartMeta === null) {
            return $codes;
        }

        $codes[] = self::SMART_INVOICE_TOOLBAR;

        $spaId = (int)($smartMeta['spaId'] ?? 0);
        if ($spaId > 0) {
            $codes[] = 'CRM_DYNAMIC_' . $spaId . '_DETAIL_TOOLBAR';
        }

        $entityTypeId = (int)($smartMeta['entityTypeId'] ?? 0);
        if ($entityTypeId > 0 && $entityTypeId !== $spaId) {
            $codes[] = 'CRM_DYNAMIC_' . $entityTypeId . '_DETAIL_TOOLBAR';
        }

        return array_values(array_unique($codes));
    }

    /**
     * @return list<string>
     */
    public static function listAvailable(BitrixClient $client, string $scope = 'crm'): array
    {
        try {
            $result = $client->result('placement.list', ['SCOPE' => $scope]);
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($result)) {
            return [];
        }

        return array_values(array_filter($result, static fn ($code) => is_string($code) && $code !== ''));
    }

    /**
     * @return list<string>
     */
    public static function listAvailableAll(BitrixClient $client): array
    {
        try {
            $result = $client->result('placement.list', []);
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($result)) {
            return [];
        }

        return array_values(array_filter($result, static fn ($code) => is_string($code) && $code !== ''));
    }

    /**
     * @param list<string> $candidates
     * @param list<string> $available
     * @return list<string>
     */
    public static function filterAvailable(array $candidates, array $available): array
    {
        if ($available === []) {
            return $candidates;
        }

        $set = array_flip($available);

        return array_values(array_filter(
            $candidates,
            static fn (string $code) => isset($set[$code])
        ));
    }
}
