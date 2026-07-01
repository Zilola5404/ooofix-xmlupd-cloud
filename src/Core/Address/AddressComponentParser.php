<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Address;

/** Разбор однострочного адреса CRM и приведение полей к лимитам XSD АдрРФ */
final class AddressComponentParser
{
    /** @var array<string, int> */
    private const XSD_MAX = [
        'ADDRESS_POSTAL_CODE' => 6,
        'ADDRESS_REGION'      => 51,
        'ADDRESS_DISTRICT'    => 255,
        'ADDRESS_CITY'        => 255,
        'ADDRESS_STREET'      => 255,
        'ADDRESS_HOUSE'       => 50,
        'ADDRESS_BUILDING'    => 50,
        'ADDRESS_FLAT'        => 50,
    ];

    /**
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    public static function normalize(array $parts): array
    {
        if (self::needsParsing($parts)) {
            $source = self::resolveParseSource($parts);
            if ($source !== '') {
                $parsed = self::parseFromText($source);
                foreach ($parsed as $key => $value) {
                    if ($value === '') {
                        continue;
                    }
                    if (empty($parts[$key]) || self::isFieldInvalid($key, $parts[$key])) {
                        $parts[$key] = $value;
                    }
                }
            }
        }

        return self::sanitizeForXsd(self::formatFlatLabel($parts));
    }

    /**
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    private static function formatFlatLabel(array $parts): array
    {
        $flat = trim($parts['ADDRESS_FLAT'] ?? '');
        if ($flat === '') {
            return $parts;
        }

        $flat = preg_replace('/^(?:кв\.?|квартира)\s*/ui', '', $flat) ?? $flat;
        $flat = trim($flat);
        if ($flat !== '') {
            $parts['ADDRESS_FLAT'] = 'кв. ' . $flat;
        }

        return $parts;
    }

    /**
     * @param array<string, string> $parts
     */
    private static function needsParsing(array $parts): bool
    {
        $house = trim($parts['ADDRESS_HOUSE'] ?? '');
        $street = trim($parts['ADDRESS_STREET'] ?? '');
        $full = trim($parts['ADDRESS_FULL'] ?? '');

        if ($house !== '' && (mb_strlen($house) > self::XSD_MAX['ADDRESS_HOUSE'] || str_contains($house, ','))) {
            return true;
        }

        if ($street !== '' && (mb_strlen($street) > self::XSD_MAX['ADDRESS_STREET'] || self::looksLikeFullAddress($street))) {
            return true;
        }

        if ($street === '' && $house === '' && $full !== '' && self::looksLikeFullAddress($full)) {
            return true;
        }

        return false;
    }

    private static function looksLikeFullAddress(string $text): bool
    {
        return str_contains($text, ',') && preg_match('/\d{6}/', $text) === 1;
    }

    /**
     * @param array<string, string> $parts
     */
    private static function resolveParseSource(array $parts): string
    {
        foreach (['ADDRESS_HOUSE', 'ADDRESS_STREET', 'ADDRESS_FULL'] as $key) {
            $value = trim($parts[$key] ?? '');
            if ($value !== '' && self::looksLikeFullAddress($value)) {
                return $value;
            }
        }

        $full = trim($parts['ADDRESS_FULL'] ?? '');
        if ($full !== '') {
            return $full;
        }

        return trim(implode(', ', array_filter([
            $parts['ADDRESS_POSTAL_CODE'] ?? '',
            $parts['ADDRESS_REGION'] ?? '',
            $parts['ADDRESS_DISTRICT'] ?? '',
            $parts['ADDRESS_CITY'] ?? '',
            $parts['ADDRESS_STREET'] ?? '',
            $parts['ADDRESS_HOUSE'] ?? '',
            $parts['ADDRESS_FLAT'] ?? '',
        ], static fn(string $v): bool => $v !== '')));
    }

    /**
     * @return array<string, string>
     */
    public static function parseFromText(string $text): array
    {
        $result = [
            'ADDRESS_POSTAL_CODE' => '',
            'ADDRESS_REGION'      => '',
            'ADDRESS_DISTRICT'    => '',
            'ADDRESS_CITY'        => '',
            'ADDRESS_STREET'      => '',
            'ADDRESS_HOUSE'       => '',
            'ADDRESS_BUILDING'    => '',
            'ADDRESS_FLAT'        => '',
        ];

        $chunks = array_values(array_filter(
            array_map('trim', preg_split('/\s*,\s*/u', $text) ?: []),
            static fn(string $part): bool => $part !== ''
        ));

        foreach ($chunks as $part) {
            if ($result['ADDRESS_POSTAL_CODE'] === '' && preg_match('/^(\d{6})$/', $part, $m)) {
                $result['ADDRESS_POSTAL_CODE'] = $m[1];
                continue;
            }

            if ($result['ADDRESS_FLAT'] === '' && preg_match('/^квартира\s+(.+)$/ui', $part, $m)) {
                $result['ADDRESS_FLAT'] = trim($m[1]);
                continue;
            }

            if ($result['ADDRESS_FLAT'] === '' && preg_match('/^кв\.?\s+(.+)$/ui', $part, $m)) {
                $result['ADDRESS_FLAT'] = trim($m[1]);
                continue;
            }

            if ($result['ADDRESS_BUILDING'] === '' && preg_match('/^(?:корп\.?|корпус|стр\.?|строение)\s*(.+)$/ui', $part, $m)) {
                $result['ADDRESS_BUILDING'] = trim($m[1]);
                continue;
            }

            if ($result['ADDRESS_HOUSE'] === '' && preg_match('/^(?:дом|д\.?)\s*(.+)$/ui', $part, $m)) {
                $result['ADDRESS_HOUSE'] = trim($m[1]);
                continue;
            }

            if ($result['ADDRESS_STREET'] === '' && preg_match('/^(?:ул\.?|улица|пр\.?|просп\.?|проспект|пер\.?|переулок|ш\.?|шоссе|б-р|бульвар|наб\.?|набережная)\b/ui', $part)) {
                $result['ADDRESS_STREET'] = $part;
                continue;
            }

            if ($result['ADDRESS_CITY'] === '' && preg_match('/^г\.?\s*(.+)$/ui', $part, $m)) {
                $result['ADDRESS_CITY'] = trim($m[1]);
                continue;
            }

            if ($result['ADDRESS_DISTRICT'] === '' && preg_match('/^(?:г\.о\.|р-н|район)\b/ui', $part)) {
                $result['ADDRESS_DISTRICT'] = $part;
                continue;
            }

            if ($result['ADDRESS_REGION'] === '' && preg_match('/(край|область|обл\.|республик|АО|округ)/ui', $part)) {
                $result['ADDRESS_REGION'] = $part;
                continue;
            }

            if ($result['ADDRESS_HOUSE'] === '' && preg_match('/^\d+[а-яА-Яa-zA-Z]?(?:\/\d+)?$/u', $part)) {
                $result['ADDRESS_HOUSE'] = $part;
            }
        }

        return $result;
    }

    private static function isFieldInvalid(string $key, string $value): bool
    {
        $max = self::XSD_MAX[$key] ?? null;
        if ($max !== null && mb_strlen($value) > $max) {
            return true;
        }

        return $key === 'ADDRESS_HOUSE' && str_contains($value, ',');
    }

    /**
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    public static function sanitizeForXsd(array $parts): array
    {
        foreach (self::XSD_MAX as $key => $max) {
            if (!isset($parts[$key]) || $parts[$key] === '') {
                continue;
            }

            $value = trim((string)$parts[$key]);
            if ($key === 'ADDRESS_POSTAL_CODE') {
                $digits = preg_replace('/\D/', '', $value);
                $parts[$key] = strlen((string)$digits) >= 6 ? substr((string)$digits, 0, 6) : $value;
                continue;
            }

            if (mb_strlen($value) > $max) {
                $parts[$key] = mb_substr($value, 0, $max);
            } else {
                $parts[$key] = $value;
            }
        }

        return $parts;
    }

    public static function truncate(string $value, int $max): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max);
    }
}
