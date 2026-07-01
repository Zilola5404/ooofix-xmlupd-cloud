<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Rest;

use Ooofix\XmlupdCloud\App\AppScopes;
use Ooofix\XmlupdCloud\Core\Config;
use Ooofix\XmlupdCloud\Core\Crm\ProductPriceNormalizer;
use Ooofix\XmlupdCloud\Core\DadataClient;

/**
 * Сбор данных CRM через REST API (аналог DataCollector модуля коробки).
 * Возвращает тот же формат массива, что ожидает UpdBuilder.
 */
final class RestDataCollector
{
    public const TYPE_DEAL          = 'deal';
    public const TYPE_SMART_INVOICE = 'smart_invoice';

    private const ADDRESS_REGISTERED = 6;

    public function __construct(
        private readonly BitrixClient $client,
        private readonly DadataClient $dadata = new DadataClient(),
        private readonly int $currentUserId = 0,
    ) {
    }

    /** @return array<string, mixed> */
    public function collect(string $entityType, int $entityId): array
    {
        $entity = $this->fetchEntity($entityType, $entityId);
        $companyId = (int)($entity['COMPANY_ID'] ?? 0);

        $buyer = $companyId > 0 ? $this->fetchBuyer($companyId) : [];
        if (!empty($buyer['REQUISITE_ID'])) {
            $buyer = $this->dadata->enrich($buyer);
        }

        $products = $this->fetchProducts(
            $entityType,
            $entityId,
            (int)($entity['ENTITY_TYPE_ID'] ?? BitrixClient::dealTypeId())
        );

        return [
            'entity'      => $entity,
            'buyer'       => $buyer,
            'seller'      => $this->fetchSeller(),
            'products'    => $products,
            'signatory'   => $this->fetchSignatory(),
            'user_fields' => $entity['USER_FIELDS'] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    private function fetchEntity(string $entityType, int $entityId): array
    {
        return match ($entityType) {
            self::TYPE_DEAL          => $this->fetchDeal($entityId),
            self::TYPE_SMART_INVOICE => $this->fetchSmartInvoice($entityId),
            default                  => throw new \InvalidArgumentException('Неизвестный тип: ' . $entityType),
        };
    }

    /** @return array<string, mixed> */
    private function fetchDeal(int $dealId): array
    {
        $row = $this->client->result('crm.deal.get', ['id' => $dealId]);
        if (!is_array($row)) {
            throw new \RuntimeException('Сделка не найдена: ' . $dealId);
        }

        return $this->buildDealEntityFromRow($row, $dealId);
    }

    /** @param array<string, mixed> $row */
    private function buildDealEntityFromRow(array $row, int $dealId): array
    {
        $opportunity = (float)($row['OPPORTUNITY'] ?? 0);
        $taxValue = round((float)($row['TAX_VALUE'] ?? 0), 2);

        return [
            'ID'             => $dealId,
            'ENTITY_TYPE_ID' => BitrixClient::dealTypeId(),
            'ENTITY_TYPE'    => self::TYPE_DEAL,
            'CATEGORY_ID'    => (int)($row['CATEGORY_ID'] ?? 0),
            'COMPANY_ID'     => (int)($row['COMPANY_ID'] ?? 0),
            'UF_UPD_NUMBER'  => (string)($row['UF_UPD_NUMBER'] ?? ''),
            'DOC_DATE'       => date('d.m.Y'),
            'OPPORTUNITY'    => $opportunity,
            'TAX_VALUE'      => $taxValue,
            'TOTAL_GROSS'    => round($opportunity, 2),
            'TOTAL_NET'      => round($opportunity - $taxValue, 2),
            'TOTAL_TAX'      => $taxValue,
            'USER_FIELDS'    => [
                'UF_UPD_NUMBER' => $row['UF_UPD_NUMBER'] ?? null,
                'UF_UPD_FILE'   => $row['UF_UPD_FILE'] ?? null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function fetchSmartInvoice(int $itemId): array
    {
        $typeId = Config::smartInvoiceTypeId();
        if ($typeId <= 0) {
            throw new \RuntimeException('Не задан entityTypeId СП «Счета» в настройках');
        }

        $data = $this->client->result('crm.item.get', [
            'entityTypeId' => $typeId,
            'id'           => $itemId,
        ]);

        if (!is_array($data) || !is_array($data['item'] ?? null)) {
            throw new \RuntimeException('Элемент СП не найден: ' . $itemId);
        }

        $item = $data['item'];
        $spaId = Config::smartInvoiceSpaId();
        $ufNumberKey = $spaId > 0
            ? UserFieldCodes::smartItemFieldKey($spaId, UserFieldCodes::SUFFIX_NUMBER)
            : 'ufCrm_UPD_NUMBER';

        return [
            'ID'              => $itemId,
            'ENTITY_TYPE_ID'  => $typeId,
            'ENTITY_TYPE'     => self::TYPE_SMART_INVOICE,
            'CATEGORY_ID'     => (int)($item['categoryId'] ?? 0),
            'COMPANY_ID'      => (int)($item['companyId'] ?? 0),
            'UF_UPD_NUMBER'   => (string)($item[$ufNumberKey] ?? $item['ufCrm_UPD_NUMBER'] ?? $item['UF_UPD_NUMBER'] ?? ''),
            'INVOICE_NUMBER'  => trim((string)($item['title'] ?? '')),
            'DOC_DATE'        => date('d.m.Y'),
            'USER_FIELDS'     => $item,
        ];
    }

    /** @return array<string, mixed> */
    private function fetchBuyer(int $companyId): array
    {
        $company = $this->client->result('crm.company.get', ['id' => $companyId]);
        $title = is_array($company) ? (string)($company['TITLE'] ?? '') : '';

        $requisite = $this->fetchRequisite(BitrixClient::companyTypeId(), $companyId);
        if ($requisite === []) {
            return ['COMPANY_ID' => $companyId, 'NAME' => $title];
        }

        $requisiteId = (int)($requisite['REQUISITE_ID'] ?? 0);

        return array_merge(
            $requisite,
            $this->fetchBankDetails($requisiteId),
            $this->fetchLegalAddress($requisiteId),
            [
                'COMPANY_ID' => $companyId,
                'NAME'       => $requisite['NAME'] ?: $title,
            ]
        );
    }

    /** @return array<string, mixed> */
    private function fetchSeller(): array
    {
        $requisiteId = $this->resolveSellerRequisiteId();
        if ($requisiteId <= 0) {
            return [];
        }

        $rows = $this->client->result('crm.requisite.list', [
            'filter' => ['ID' => $requisiteId],
        ]);

        $row = is_array($rows) ? ($rows[0] ?? null) : null;
        if (!is_array($row)) {
            return [];
        }

        return array_merge(
            $this->normalizeRequisite($row),
            $this->fetchLegalAddress($requisiteId),
            $this->fetchBankDetails($requisiteId)
        );
    }

    private function resolveSellerRequisiteId(): int
    {
        $configured = Config::sellerRequisiteId();
        if ($configured > 0) {
            return $configured;
        }

        $companies = $this->client->result('crm.company.list', [
            'filter' => ['IS_MY_COMPANY' => 'Y'],
            'select' => ['ID'],
            'order'  => ['ID' => 'ASC'],
        ]);

        $companyId = is_array($companies) && isset($companies[0]['ID'])
            ? (int)$companies[0]['ID']
            : 0;

        if ($companyId <= 0) {
            return 0;
        }

        $requisites = $this->client->result('crm.requisite.list', [
            'filter' => [
                'ENTITY_TYPE_ID' => BitrixClient::companyTypeId(),
                'ENTITY_ID'      => $companyId,
            ],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ]);

        if (!is_array($requisites) || !isset($requisites[0]['ID'])) {
            return 0;
        }

        return (int)$requisites[0]['ID'];
    }

    /** @return array<string, mixed> */
    private function fetchRequisite(int $entityTypeId, int $entityId): array
    {
        $rows = $this->client->result('crm.requisite.list', [
            'filter' => [
                'ENTITY_TYPE_ID' => $entityTypeId,
                'ENTITY_ID'      => $entityId,
            ],
            'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ]);

        if (!is_array($rows) || !isset($rows[0])) {
            return [];
        }

        return $this->normalizeRequisite($rows[0]);
    }

    /** @param array<string, mixed> $row */
    private function normalizeRequisite(array $row): array
    {
        $inn = trim((string)($row['RQ_INN'] ?? ''));
        $digits = preg_replace('/\D/', '', $inn);
        $isIp = strlen((string)$digits) === 12 || !empty($row['RQ_OGRNIP']);

        $lastName   = trim((string)($row['RQ_LAST_NAME'] ?? ''));
        $firstName  = trim((string)($row['RQ_FIRST_NAME'] ?? ''));
        $middleName = trim((string)($row['RQ_SECOND_NAME'] ?? ''));
        $companyName = trim((string)($row['RQ_COMPANY_NAME'] ?: $row['RQ_NAME'] ?: ''));

        $fio = trim(implode(' ', array_filter([$lastName, $firstName, $middleName])));
        $displayName = $isIp
            ? ('ИП ' . ($fio !== '' ? $fio : $companyName))
            : $companyName;

        return [
            'REQUISITE_ID' => (int)($row['ID'] ?? 0),
            'NAME'         => $companyName !== '' ? $companyName : $fio,
            'DISPLAY_NAME' => $displayName,
            'INN'          => $inn,
            'KPP'          => trim((string)($row['RQ_KPP'] ?? '')),
            'OGRN'         => trim((string)($row['RQ_OGRN'] ?? $row['RQ_OGRNIP'] ?? '')),
            'IS_IP'        => $isIp,
            'LAST_NAME'    => $lastName,
            'FIRST_NAME'   => $firstName,
            'MIDDLE_NAME'  => $middleName,
        ];
    }

    /** @return array<string, mixed> */
    private function fetchBankDetails(int $requisiteId): array
    {
        $rows = $this->client->result('crm.requisite.bankdetail.list', [
            'filter' => ['ENTITY_ID' => $requisiteId],
            'order'  => ['SORT' => 'ASC', 'ID' => 'ASC'],
        ]);

        if (!is_array($rows) || !isset($rows[0])) {
            return [];
        }

        $row = $rows[0];

        return [
            'BANK_NAME' => trim((string)($row['RQ_BANK_NAME'] ?? '')),
            'BANK_BIK'  => trim((string)($row['RQ_BIK'] ?? '')),
            'BANK_RS'   => trim((string)($row['RQ_ACC_NUM'] ?? '')),
            'BANK_KS'   => trim((string)($row['RQ_COR_ACC_NUM'] ?? '')),
        ];
    }

    /** @return array<string, mixed> */
    private function fetchLegalAddress(int $requisiteId): array
    {
        $rows = $this->client->result('crm.address.list', [
            'filter' => [
                'ENTITY_TYPE_ID' => 8,
                'ENTITY_ID'      => $requisiteId,
                'TYPE_ID'        => self::ADDRESS_REGISTERED,
            ],
        ]);

        if (!is_array($rows) || !isset($rows[0])) {
            return [];
        }

        $row = $rows[0];
        $parts = array_filter([
            $row['POSTAL_CODE'] ?? '',
            $row['PROVINCE'] ?? '',
            $row['CITY'] ?? '',
            $row['ADDRESS_1'] ?? '',
            $row['ADDRESS_2'] ?? '',
        ]);

        $full = trim(implode(', ', array_map('strval', $parts)));

        return [
            'ADDRESS_FULL'  => $full,
            'ADDRESS_LINE'  => $full,
            'ADDRESS_REGION'=> (string)($row['PROVINCE'] ?? ''),
            'ADDRESS_CITY'  => (string)($row['CITY'] ?? ''),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function fetchProducts(string $entityType, int $entityId, int $entityTypeId): array
    {
        if ($entityType === self::TYPE_DEAL) {
            $rows = $this->client->result('crm.deal.productrows.get', ['id' => $entityId]);
        } else {
            $rows = $this->client->result('crm.item.productrow.list', [
                'filter' => [
                    '=ownerType' => 'T' . dechex($entityTypeId),
                    '=ownerId'   => $entityId,
                ],
            ]);
            if (is_array($rows) && isset($rows['productRows'])) {
                $rows = $rows['productRows'];
            }
        }

        if (!is_array($rows)) {
            return [];
        }

        $products = [];
        $line = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $line++;
            $amounts = ProductPriceNormalizer::normalize($row);

            $products[] = [
                'LINE'         => $line,
                'NAME'         => (string)($row['PRODUCT_NAME'] ?? $row['productName'] ?? ''),
                'QUANTITY'     => (float)($row['QUANTITY'] ?? $row['quantity'] ?? 0),
                'PRICE'        => $amounts['PRICE'],
                'SUM_NET'      => $amounts['SUM_NET'],
                'SUM_GROSS'    => $amounts['SUM_GROSS'],
                'TAX_RATE'     => $amounts['TAX_RATE'],
                'TAX_SUM'      => $amounts['TAX_SUM'],
                'MEASURE'      => (string)($row['MEASURE_NAME'] ?? $row['measureName'] ?? 'шт'),
                'MEASURE_CODE' => (string)($row['MEASURE_CODE'] ?? $row['measureCode'] ?? '796'),
            ];
        }

        return $products;
    }

    /** @return array<string, mixed> */
    private function fetchSignatory(): array
    {
        $position = Config::signatoryPosition();
        $stored = $this->signatoryFromStoredName($position);
        if (($stored['NAME'] ?? '') !== '') {
            return $stored;
        }

        $mode = Config::signatoryMode();
        $userId = match ($mode) {
            'current_user' => $this->currentUserId,
            default        => Config::signatoryUserId(),
        };

        if ($userId <= 0) {
            return $stored;
        }

        try {
            $user = $this->client->result('user.get', ['ID' => $userId]);
        } catch (\Throwable $e) {
            if (AppScopes::isPrivilegeError($e)) {
                return $stored;
            }

            throw $e;
        }

        if (!is_array($user)) {
            return $stored;
        }

        return [
            'ID'         => $userId,
            'NAME'       => trim(implode(' ', array_filter([
                $user['LAST_NAME'] ?? '',
                $user['NAME'] ?? '',
                $user['SECOND_NAME'] ?? '',
            ]))),
            'POSITION'   => (string)($user['WORK_POSITION'] ?? $position),
            'LAST_NAME'  => (string)($user['LAST_NAME'] ?? ''),
            'FIRST_NAME' => (string)($user['NAME'] ?? ''),
            'MIDDLE_NAME'=> (string)($user['SECOND_NAME'] ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    private function signatoryFromStoredName(string $position): array
    {
        $fullName = Config::signatoryUserName();
        if ($fullName === '') {
            return [
                'NAME'     => '',
                'POSITION' => $position,
            ];
        }

        $parts = preg_split('/\s+/u', $fullName) ?: [];

        return [
            'NAME'        => $fullName,
            'POSITION'    => $position,
            'LAST_NAME'   => (string)($parts[0] ?? ''),
            'FIRST_NAME'  => (string)($parts[1] ?? ''),
            'MIDDLE_NAME' => (string)($parts[2] ?? ''),
        ];
    }
}
