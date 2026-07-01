<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Crm;

use Ooofix\XmlupdCloud\Core\Config;

/**
 * Нормализация цен товарной строки CRM -> суммы для УПД.
 * Режим расчёта задаётся в настройках модуля (calculation_mode).
 */
final class ProductPriceNormalizer
{
    /**
     * @param array<string, mixed> $row строка ProductRowTable
     * @return array{PRICE: float, SUM_NET: float, TAX_SUM: float, SUM_GROSS: float, TAX_RATE: float}
     */
    public static function normalize(array $row, ?string $mode = null): array
    {
        return ProductAmountCalculator::calculate($row, $mode ?? Config::calculationMode());
    }

    /**
     * Цена за единицу без НДС — для атрибута ЦенаТов.
     *
     * @param array<string, mixed> $row
     */
    public static function resolveUnitNetPrice(array $row, float $taxRate): float
    {
        return ProductAmountCalculator::resolveUnitNetPrice($row, $taxRate);
    }
}
