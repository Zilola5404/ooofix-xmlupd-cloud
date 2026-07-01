<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Crm;

/**
 * ����� ��������� ��� (����� ��������).
 *
 * � ������ 1C/��� ����� � ����� ����� ����� ���������� �� 1�2 ���. �� net/tax
 * (Diadoc ������������ ��� ��� ����������� net �� gross/(1+���%)).
 */
final class DocumentTotalsCalculator
{
    /**
     * @param array{SUM_NET: float, TAX_SUM: float, SUM_GROSS: float} $lineTotals
     * @return array{SUM_NET: float, TAX_SUM: float, SUM_GROSS: float}
     */
    public static function finalize1C(array $lineTotals, float $taxRate = 22.0): array
    {
        $gross = self::roundMoney((float)$lineTotals['SUM_GROSS']);
        $lineNet = self::roundMoney((float)$lineTotals['SUM_NET']);
        $lineTax = self::roundMoney((float)$lineTotals['TAX_SUM']);

        if ($gross <= 0) {
            return [
                'SUM_NET' => 0.0,
                'TAX_SUM' => 0.0,
                'SUM_GROSS' => 0.0,
            ];
        }

        $netByGross = self::roundMoney($gross / (1 + $taxRate / 100));
        $netGap = self::roundMoney($lineNet - $netByGross);

        // ������ ����������� (net + tax = gross), �� net/tax ���������� � net �� gross/1.22.
        if ($netGap > 0 && $netGap <= 0.02 && self::moneyEquals($lineNet + $lineTax, $gross)) {
            $tax = self::roundMoney($lineTax + $netGap + 0.01);
            $net = self::roundMoney($gross - $tax);

            return [
                'SUM_NET' => $net,
                'TAX_SUM' => $tax,
                'SUM_GROSS' => $gross,
            ];
        }

        return [
            'SUM_NET' => $lineNet,
            'TAX_SUM' => $lineTax,
            'SUM_GROSS' => $gross,
        ];
    }

    /**
     * @param array{SUM_NET: float, TAX_SUM: float, SUM_GROSS: float} $lineTotals
     * @return array{SUM_NET: float, TAX_SUM: float, SUM_GROSS: float}
     */
    public static function finalizeBitrix24(array $lineTotals): array
    {
        return [
            'SUM_NET' => self::roundMoney((float)$lineTotals['SUM_NET']),
            'TAX_SUM' => self::roundMoney((float)$lineTotals['TAX_SUM']),
            'SUM_GROSS' => self::roundMoney((float)$lineTotals['SUM_GROSS']),
        ];
    }

    private static function moneyEquals(float $a, float $b): bool
    {
        return abs($a - $b) < 0.001;
    }

    private static function roundMoney(float $value): float
    {
        return round($value, 2);
    }
}
