<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Documents\Upd;

use Ooofix\XmlupdCloud\Core\Address\RegionCodeResolver;
use Ooofix\XmlupdCloud\Core\Address\AddressComponentParser;
use Ooofix\XmlupdCloud\Core\Config;
use Ooofix\XmlupdCloud\Core\Crm\DocumentTotalsCalculator;
use Ooofix\XmlupdCloud\Core\Crm\ProductAmountCalculator;

/** Преобразует собранные данные CRM в плоскую структуру для XmlWriter */
class UpdMapper
{
    /** @var array<string, array<string, mixed>> */
    private array $map;

    public function __construct()
    {
        $path = Config::mappingPath();
        $this->map = is_file($path) ? (array)include $path : [];
    }

    /** @param array<string, mixed> $data */
    public function map(array $data): array
    {
        $entity = $data['entity'] ?? [];
        $buyer = $data['buyer'] ?? [];
        $seller = $data['seller'] ?? [];
        $products = $data['products'] ?? [];
        $signatory = $data['signatory'] ?? [];

        $docNumber = $this->resolveDocNumber($entity);
        $docDate = (string)($entity['DOC_DATE'] ?? date('d.m.Y'));

        // Оба режима: итоги из сумм строк (без OPPORTUNITY/TAX_VALUE).
        // 1C: DocumentTotalsCalculator — корректировка копеек в шапке как в ЭДО.
        $totalsFromDeal = false;

        $mapped = [
            'doc_number'   => $docNumber,
            'doc_date'     => $docDate,
            'doc_function' => Config::updFunction(),
            'products'     => $products,
            'products_text'=> $this->buildProductsText($products),
            'totals'       => $this->calcTotals($products, $entity),
            'totals_from_deal' => $totalsFromDeal,
            'calculation_mode' => Config::calculationMode(),
            '_raw'         => $data,
        ];

        return array_merge(
            $mapped,
            $this->mapParty($buyer, 'buyer'),
            $this->mapParty($seller, 'seller'),
            $this->mapSignatory($signatory)
        );
    }

    /** @param array<string, mixed> $entity */
    private function resolveDocNumber(array $entity): string
    {
        $uf = trim((string)($entity['UF_UPD_NUMBER'] ?? ''));
        if ($uf !== '') {
            return $uf;
        }

        // Номер счёта из СП (если заполнен)
        $invoiceNumber = trim((string)($entity['INVOICE_NUMBER'] ?? ''));
        if ($invoiceNumber !== '') {
            return $invoiceNumber;
        }

        return (string)($entity['ID'] ?? '');
    }

    /**
     * @param array<string, mixed> $party
     * @return array<string, mixed>
     */
    private function mapParty(array $party, string $prefix): array
    {
        $party = array_merge($party, AddressComponentParser::normalize([
            'ADDRESS_POSTAL_CODE' => (string)($party['ADDRESS_POSTAL_CODE'] ?? ''),
            'ADDRESS_REGION'      => (string)($party['ADDRESS_REGION'] ?? ''),
            'ADDRESS_DISTRICT'    => (string)($party['ADDRESS_DISTRICT'] ?? ''),
            'ADDRESS_CITY'        => (string)($party['ADDRESS_CITY'] ?? ''),
            'ADDRESS_STREET'      => (string)($party['ADDRESS_STREET'] ?? ''),
            'ADDRESS_HOUSE'       => (string)($party['ADDRESS_HOUSE'] ?? ''),
            'ADDRESS_BUILDING'    => (string)($party['ADDRESS_BUILDING'] ?? ''),
            'ADDRESS_FLAT'        => (string)($party['ADDRESS_FLAT'] ?? ''),
            'ADDRESS_FULL'        => (string)($party['ADDRESS_FULL'] ?? ''),
        ]));

        $inn = (string)($party['INN'] ?? '');
        $isIp = !empty($party['IS_IP']) || strlen(preg_replace('/\D/', '', $inn)) === 12;

        $regionCode = RegionCodeResolver::resolve(
            (string)($party['ADDRESS_REGION_CODE'] ?? ''),
            (string)($party['ADDRESS_REGION'] ?? ''),
            (string)($party['ADDRESS_CITY'] ?? ''),
            (string)($party['ADDRESS_POSTAL_CODE'] ?? ''),
            (string)($party['ADDRESS_FULL'] ?? '')
        );

        return [
            $prefix . '_name'          => (string)($party['NAME'] ?? ''),
            $prefix . '_display_name'  => (string)($party['DISPLAY_NAME'] ?? $party['NAME'] ?? ''),
            $prefix . '_inn'           => $inn,
            $prefix . '_kpp'           => (string)($party['KPP'] ?? ''),
            $prefix . '_ogrn'           => (string)($party['OGRN'] ?? ''),
            $prefix . '_is_ip'         => $isIp ? 1 : 0,
            $prefix . '_last_name'     => (string)($party['LAST_NAME'] ?? ''),
            $prefix . '_first_name'    => (string)($party['FIRST_NAME'] ?? ''),
            $prefix . '_middle_name'   => (string)($party['MIDDLE_NAME'] ?? ''),
            $prefix . '_address'       => (string)($party['ADDRESS_FULL'] ?? ''),
            $prefix . '_addr_index'     => (string)($party['ADDRESS_POSTAL_CODE'] ?? ''),
            $prefix . '_addr_region_code' => $regionCode,
            $prefix . '_addr_region'    => (string)($party['ADDRESS_REGION'] ?? ''),
            $prefix . '_addr_district'  => (string)($party['ADDRESS_DISTRICT'] ?? ''),
            $prefix . '_addr_city'      => (string)($party['ADDRESS_CITY'] ?? ''),
            $prefix . '_addr_street'    => (string)($party['ADDRESS_STREET'] ?? ''),
            $prefix . '_addr_house'     => (string)($party['ADDRESS_HOUSE'] ?? ''),
            $prefix . '_addr_building'  => (string)($party['ADDRESS_BUILDING'] ?? ''),
            $prefix . '_addr_flat'      => (string)($party['ADDRESS_FLAT'] ?? ''),
            $prefix . '_bank_name'      => (string)($party['BANK_NAME'] ?? ''),
            $prefix . '_bank_bik'       => (string)($party['BANK_BIK'] ?? ''),
            $prefix . '_bank_rs'        => (string)($party['BANK_RS'] ?? ''),
            $prefix . '_bank_ks'        => (string)($party['BANK_KS'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $signatory
     * @return array<string, mixed>
     */
    private function mapSignatory(array $signatory): array
    {
        return [
            'signatory_name'         => (string)($signatory['NAME'] ?? ''),
            'signatory_position'     => (string)($signatory['POSITION'] ?? 'Сотрудник'),
            'signatory_last_name'    => (string)($signatory['LAST_NAME'] ?? ''),
            'signatory_first_name'   => (string)($signatory['FIRST_NAME'] ?? ''),
            'signatory_middle_name'  => (string)($signatory['MIDDLE_NAME'] ?? ''),
        ];
    }

    /** @param list<array<string, mixed>> $products */
    private function buildProductsText(array $products): string
    {
        $parts = [];
        foreach ($products as $p) {
            $name = trim((string)($p['NAME'] ?? ''));
            if ($name === '') {
                continue;
            }
            $parts[] = sprintf('%s, %s %s', $name, $this->formatQty((float)($p['QUANTITY'] ?? 0)), $p['MEASURE'] ?? 'шт');
        }

        return implode('; ', $parts);
    }

    private function formatQty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
    }

    /** @param list<array<string, mixed>> $products */
    private function calcTotals(array $products, array $entity = []): array
    {
        $net = 0.0;
        $tax = 0.0;
        $gross = 0.0;
        $taxRate = 0.0;

        foreach ($products as $p) {
            $net += (float)($p['SUM_NET'] ?? 0);
            $tax += (float)($p['TAX_SUM'] ?? 0);
            $gross += (float)($p['SUM_GROSS'] ?? 0);
            if ($taxRate <= 0 && (float)($p['TAX_RATE'] ?? 0) > 0) {
                $taxRate = (float)$p['TAX_RATE'];
            }
        }

        $lineTotals = [
            'SUM_NET'   => round($net, 2),
            'TAX_SUM'   => round($tax, 2),
            'SUM_GROSS' => round($gross, 2),
        ];

        if (Config::calculationMode() === ProductAmountCalculator::MODE_BITRIX24) {
            return DocumentTotalsCalculator::finalizeBitrix24($lineTotals);
        }

        return DocumentTotalsCalculator::finalize1C($lineTotals, $taxRate > 0 ? $taxRate : 22.0);
    }

    /** @return array<string, array<string, mixed>> */
    public function getMap(): array
    {
        return $this->map;
    }
}
