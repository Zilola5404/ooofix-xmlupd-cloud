<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Documents\Upd;

/** Проверка и нормализация кода ОКЕИ единицы измерения для XML УПД (ФНС 5.03). */
final class OkeiMeasureValidator
{
    /** Код ОКЕИ в XML: 3–4 цифры (короткие ID каталога B24 вроде «6» не допускаются). */
    public static function isValidCode(string $code): bool
    {
        $code = trim($code);
        if ($code === '' || !ctype_digit($code)) {
            return false;
        }

        $len = strlen($code);

        return $len >= 3 && $len <= 4;
    }

    /** Приведение к формату XSD: дополнение нулями слева до 4 цифр. */
    public static function formatForXml(string $code): string
    {
        $code = trim($code);
        if (!self::isValidCode($code)) {
            return $code;
        }

        return str_pad($code, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param list<array<string, mixed>> $products
     * @return array{line: int, name: string, measure: string, code: string}|null
     */
    public static function findInvalidProduct(array $products): ?array
    {
        foreach ($products as $row) {
            $code = (string)($row['MEASURE_CODE'] ?? '');
            if (self::isValidCode($code)) {
                continue;
            }

            return [
                'line'    => (int)($row['LINE'] ?? 0),
                'name'    => trim((string)($row['NAME'] ?? '')),
                'measure' => trim((string)($row['MEASURE'] ?? '')),
                'code'    => $code,
            ];
        }

        return null;
    }
}
