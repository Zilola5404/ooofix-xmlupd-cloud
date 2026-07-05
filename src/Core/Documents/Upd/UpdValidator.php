<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Documents\Upd;

use Ooofix\XmlupdCloud\Core\Address\RegionCodeResolver;
use Ooofix\XmlupdCloud\Core\ValidationMessages;

/** Проверка обязательных полей и арифметики сумм перед генерацией XML */
class UpdValidator
{
    private const MONEY_EPS = 0.02;

    public function __construct(
        private readonly UpdMapper $mapper = new UpdMapper(),
    ) {
    }

    /**
     * @param array<string, mixed> $mapped
     * @return string[] сообщения для пользователя
     */
    public function validate(array $mapped): array
    {
        $errors = [];
        $map = $this->mapper->getMap();

        foreach ($map as $key => $rule) {
            if (empty($rule['required'])) {
                continue;
            }

            $value = $mapped[$key] ?? null;

            if ($key === 'products') {
                if (!is_array($value) || $value === []) {
                    $errors[] = ValidationMessages::fromMapKey('products');
                }
                continue;
            }

            if ($value === null || $value === '' || $value === []) {
                $label = (string)($rule['label'] ?? $key);
                $errors[] = ValidationMessages::fromLabel($label);
            }
        }

        $inn = preg_replace('/\D/', '', (string)($mapped['buyer_inn'] ?? ''));
        if (strlen($inn) === 10 && empty($mapped['buyer_kpp'])) {
            $errors[] = ValidationMessages::fromMapKey('buyer_kpp');
        }

        if (is_array($mapped['products'] ?? null) && ($mapped['products'] ?? []) !== []) {
            $errors = array_merge($errors, $this->validateProductMeasures($mapped));
            $errors = array_merge($errors, $this->validateProductSums($mapped));
        }

        $errors = array_merge($errors, $this->validateRegionCodes($mapped));

        return array_values(array_unique($errors));
    }

    /**
     * @param array<string, mixed> $mapped
     * @return string[]
     */
    private function validateRegionCodes(array $mapped): array
    {
        $errors = [];

        foreach (['buyer' => 'buyer_region_code', 'seller' => 'seller_region_code'] as $role => $messageKey) {
            if (!$this->hasAddressBlock($mapped, $role)) {
                continue;
            }

            $prefix = $role . '_addr_';
            $code = RegionCodeResolver::resolve(
                (string)($mapped[$prefix . 'region_code'] ?? ''),
                (string)($mapped[$prefix . 'region'] ?? ''),
                (string)($mapped[$prefix . 'city'] ?? ''),
                (string)($mapped[$prefix . 'index'] ?? ''),
                (string)($mapped[$role . '_address'] ?? '')
            );

            if ($code === '') {
                $errors[] = ValidationMessages::get($messageKey);
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $mapped */
    private function hasAddressBlock(array $mapped, string $role): bool
    {
        $prefix = $role . '_addr_';
        foreach (['index', 'region', 'city', 'street', 'house', 'flat'] as $part) {
            if (!empty($mapped[$prefix . $part])) {
                return true;
            }
        }

        return !empty($mapped[$role . '_address']);
    }

    /**
     * @param array<string, mixed> $mapped
     * @return string[]
     */
    private function validateProductMeasures(array $mapped): array
    {
        $invalid = OkeiMeasureValidator::findInvalidProduct($mapped['products'] ?? []);
        if ($invalid === null) {
            return [];
        }

        return [
            ValidationMessages::productMeasureInvalid(
                $invalid['line'],
                $invalid['name'],
                $invalid['measure'] !== '' ? $invalid['measure'] : $invalid['code'],
            ),
        ];
    }

    /**
     * @param array<string, mixed> $mapped
     * @return string[]
     */
    private function validateProductSums(array $mapped): array
    {
        $errors = [];
        $products = $mapped['products'] ?? [];
        $sumNet = 0.0;
        $sumTax = 0.0;
        $sumGross = 0.0;
        $hasSumError = false;

        foreach ($products as $row) {
            $line = (int)($row['LINE'] ?? 0);
            $productName = trim((string)($row['NAME'] ?? ''));

            $qty = (float)($row['QUANTITY'] ?? 0);
            $price = (float)($row['PRICE'] ?? 0);
            $taxRate = (float)($row['TAX_RATE'] ?? 0);

            if ($qty <= 0) {
                $errors[] = ValidationMessages::productQuantity($line, $productName);
            }
            if ($price < 0) {
                $errors[] = ValidationMessages::productPrice($line, $productName);
            }

            $actualNet = round((float)($row['SUM_NET'] ?? 0), 2);
            $actualTax = round((float)($row['TAX_SUM'] ?? 0), 2);
            $actualGross = round((float)($row['SUM_GROSS'] ?? 0), 2);

            if (!$this->isProductSumConsistent($qty, $price, $taxRate, $actualNet, $actualTax, $actualGross)) {
                $hasSumError = true;
                $errors[] = ValidationMessages::productSumMismatch($line, $productName);
            }

            $sumNet += $actualNet;
            $sumTax += $actualTax;
            $sumGross += $actualGross;
        }

        $totals = $mapped['totals'] ?? [];
        $totalNet = round((float)($totals['SUM_NET'] ?? 0), 2);
        $totalTax = round((float)($totals['TAX_SUM'] ?? 0), 2);
        $totalGross = round((float)($totals['SUM_GROSS'] ?? 0), 2);

        $sumNet = round($sumNet, 2);
        $sumTax = round($sumTax, 2);
        $sumGross = round($sumGross, 2);

        if (
            !$hasSumError
            && empty($mapped['totals_from_deal'])
            && !$this->totalsConsistent($sumNet, $sumTax, $sumGross, $totalNet, $totalTax, $totalGross)
        ) {
            $errors[] = ValidationMessages::productTotalsMismatch();
        }

        return $errors;
    }

    private function isProductSumConsistent(
        float $qty,
        float $price,
        float $taxRate,
        float $net,
        float $tax,
        float $gross,
    ): bool {
        if (!$this->moneyEquals($net + $tax, $gross)) {
            return false;
        }

        if ($taxRate <= 0) {
            return $this->moneyEquals(round($qty * $price, 2), $net);
        }

        if (!$this->moneyEquals(round($gross - $net, 2), $tax)) {
            return false;
        }

        if ($qty <= 0) {
            return true;
        }

        $lineDrift = abs(round($qty * $price, 2) - $net);
        $maxDrift = max(self::MONEY_EPS, 0.02 * $qty) + self::MONEY_EPS;

        return $lineDrift <= $maxDrift;
    }

    private function totalsConsistent(
        float $sumNet,
        float $sumTax,
        float $sumGross,
        float $totalNet,
        float $totalTax,
        float $totalGross,
    ): bool {
        $netOk = $this->moneyEquals($sumNet, $totalNet)
            || abs($sumNet - $totalNet) <= max(1.0, 0.01 * max($totalNet, 1.0));
        $taxOk = $this->moneyEquals($sumTax, $totalTax)
            || abs($sumTax - $totalTax) <= max(1.0, 0.01 * max($totalTax, 1.0));
        $grossOk = $this->moneyEquals($sumGross, $totalGross);

        return $netOk && $taxOk && $grossOk;
    }

    private function moneyEquals(float $a, float $b): bool
    {
        return abs($a - $b) <= self::MONEY_EPS;
    }
}
