<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Address;

use Bitrix\Crm\AddressTable;
use Bitrix\Main\Loader;

/** Юридический адрес реквизита CRM для блока АдрРФ в УПД */
final class RequisiteAddressResolver
{
    /** Тип «Юридический адрес» в Bitrix CRM (EntityAddressType::Registered) */
    public const TYPE_LEGAL = 6;

    /**
     * @return array<string, mixed>
     */
    public static function fetchLegalAddress(int $entityTypeId, int $entityId): array
    {
        Loader::includeModule('crm');

        $row = self::loadLegalAddressRow($entityTypeId, $entityId);
        if ($row === null) {
            return self::emptyResult(false);
        }

        return array_merge(self::normalizeRow($row), [
            'ADDRESS_FOUND'    => true,
            'ADDRESS_IS_LEGAL' => true,
            'ADDRESS_TYPE_ID'  => (int)($row['TYPE_ID'] ?? self::legalTypeId()),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeRow(array $row): array
    {
        $parts = [
            'ADDRESS_POSTAL_CODE' => trim((string)($row['POSTAL_CODE'] ?? '')),
            'ADDRESS_REGION_CODE' => self::resolveRegionCode($row),
            'ADDRESS_REGION'      => trim((string)($row['PROVINCE'] ?? $row['REGION'] ?? '')),
            'ADDRESS_DISTRICT'    => trim((string)($row['REGION'] ?? '')),
            'ADDRESS_CITY'        => trim((string)($row['CITY'] ?? '')),
            'ADDRESS_STREET'      => trim((string)($row['ADDRESS_1'] ?? '')),
            'ADDRESS_HOUSE'       => trim((string)($row['ADDRESS_2'] ?? '')),
            'ADDRESS_BUILDING'    => trim((string)($row['BUILDING'] ?? '')),
            'ADDRESS_FLAT'        => trim((string)($row['APARTMENT'] ?? '')),
        ];

        $parts = AddressComponentParser::normalize($parts);
        $parts['ADDRESS_FULL'] = self::buildAddressString($parts, trim((string)($row['ADDRESS_FULL'] ?? '')));

        return $parts;
    }

    /** @return list<string> */
    public static function missingRequiredFields(array $address): array
    {
        $missing = [];

        if (trim((string)($address['ADDRESS_POSTAL_CODE'] ?? '')) === '') {
            $missing[] = 'индекс';
        }
        if (trim((string)($address['ADDRESS_REGION'] ?? '')) === '') {
            $missing[] = 'регион';
        }
        if (trim((string)($address['ADDRESS_CITY'] ?? '')) === '') {
            $missing[] = 'город';
        }
        if (trim((string)($address['ADDRESS_STREET'] ?? '')) === '') {
            $missing[] = 'улица';
        }
        if (trim((string)($address['ADDRESS_HOUSE'] ?? '')) === '') {
            $missing[] = 'дом';
        }

        $regionCode = RegionCodeResolver::resolve(
            (string)($address['ADDRESS_REGION_CODE'] ?? ''),
            (string)($address['ADDRESS_REGION'] ?? ''),
            (string)($address['ADDRESS_CITY'] ?? ''),
            (string)($address['ADDRESS_POSTAL_CODE'] ?? ''),
            (string)($address['ADDRESS_FULL'] ?? '')
        );
        if ($regionCode === '') {
            $missing[] = 'код региона (КодРегион)';
        }

        return $missing;
    }

    private static function legalTypeId(): int
    {
        if (class_exists(\Bitrix\Crm\EntityAddressType::class)) {
            $registered = \Bitrix\Crm\EntityAddressType::Registered;
            if (is_numeric($registered)) {
                return (int)$registered;
            }
        }

        return self::TYPE_LEGAL;
    }

    /** @return array<string, mixed>|null */
    private static function loadLegalAddressRow(int $entityTypeId, int $entityId): ?array
    {
        $row = AddressTable::getList([
            'filter' => [
                '=ENTITY_TYPE_ID' => $entityTypeId,
                '=ENTITY_ID'      => $entityId,
                '=TYPE_ID'        => self::legalTypeId(),
            ],
            'limit'  => 1,
        ])->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    private static function emptyResult(bool $found): array
    {
        return [
            'ADDRESS_FOUND'        => $found,
            'ADDRESS_IS_LEGAL'     => false,
            'ADDRESS_TYPE_ID'      => 0,
            'ADDRESS_FULL'         => '',
            'ADDRESS_POSTAL_CODE'  => '',
            'ADDRESS_REGION_CODE'  => '',
            'ADDRESS_REGION'       => '',
            'ADDRESS_DISTRICT'     => '',
            'ADDRESS_CITY'         => '',
            'ADDRESS_STREET'       => '',
            'ADDRESS_HOUSE'        => '',
            'ADDRESS_BUILDING'     => '',
            'ADDRESS_FLAT'         => '',
        ];
    }

    /** @param array<string, mixed> $row */
    private static function resolveRegionCode(array $row): string
    {
        return RegionCodeResolver::resolve(
            (string)($row['PROVINCE_CODE'] ?? ''),
            (string)($row['PROVINCE'] ?? $row['REGION'] ?? ''),
            (string)($row['CITY'] ?? ''),
            (string)($row['POSTAL_CODE'] ?? ''),
            (string)($row['ADDRESS_FULL'] ?? '')
        );
    }

    /** @param array<string, string> $parts */
    private static function buildAddressString(array $parts, string $fallback): string
    {
        if ($fallback !== '') {
            return $fallback;
        }

        $chunks = array_filter([
            $parts['ADDRESS_POSTAL_CODE'] ?? '',
            $parts['ADDRESS_REGION'] ?? '',
            $parts['ADDRESS_CITY'] ?? '',
            $parts['ADDRESS_STREET'] ?? '',
            $parts['ADDRESS_HOUSE'] ?? '',
            $parts['ADDRESS_FLAT'] ?? '',
        ], static fn(string $value): bool => $value !== '');

        return implode(', ', $chunks);
    }
}
