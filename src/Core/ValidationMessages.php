<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core;

/** Понятные сообщения пользователю при незаполненных данных */
final class ValidationMessages
{
    /** @var array<string, string> */
    private const MESSAGES = [
        'deal_company'       => 'Укажите компанию-покупателя в поле «Компания» в карточке сделки.',
        'invoice_company'    => 'Укажите компанию-покупателя в карточке счёта.',
        'buyer_requisite'    => 'Добавьте реквизиты компании-покупателя: CRM - Компании - вкладка «Реквизиты».',
        'buyer_name'         => 'Заполните наименование покупателя: компания - «Реквизиты» - полное наименование.',
        'buyer_inn'          => 'Заполните ИНН покупателя: компания - «Реквизиты» - ИНН.',
        'buyer_kpp'          => 'Заполните КПП покупателя: компания - «Реквизиты» - КПП (обязательно для юрлица).',
        'buyer_address'      => 'Заполните юридический адрес покупателя: компания - «Реквизиты» - адрес.',
        'seller_requisites'  => 'Заполните реквизиты продавца: CRM - Настройки - «Мои реквизиты» (ИНН и адрес). Либо укажите ID реквизита в настройках модуля.',
        'seller_name'        => 'Заполните наименование продавца в «Мои реквизиты».',
        'seller_inn'         => 'Заполните ИНН продавца в «Мои реквизиты».',
        'seller_address'     => 'Заполните юридический адрес продавца в «Мои реквизиты».',
        'buyer_region_code'  => 'Укажите регион в адресе покупателя (поле «Регион» / код субъекта) или включите DaData для автозаполнения КодРегион.',
        'seller_region_code' => 'Укажите регион в адресе продавца (поле «Регион» / код субъекта) или включите DaData для автозаполнения КодРегион.',
        'signatory_name'     => 'Заполните ФИО подписанта: у пользователя в профиле или в настройках модуля.',
        'signatory_position' => 'Заполните должность подписанта в настройках модуля «Генерация XML (УПД)».',
        'products'           => 'Добавьте товарные позиции в блок «Товары» в карточке сделки или счёта.',
        'doc_number'         => 'Укажите номер документа или поле «Номер УПД (1С)» в карточке.',
        'doc_date'           => 'Не удалось определить дату документа.',
    ];

    /** @var array<string, string> label из mapping -> ключ сообщения */
    private const LABEL_TO_KEY = [
        'Наименование покупателя'       => 'buyer_name',
        'ИНН покупателя'                => 'buyer_inn',
        'КПП покупателя'                => 'buyer_kpp',
        'Юридический адрес покупателя'  => 'buyer_address',
        'Наименование продавца'         => 'seller_name',
        'ИНН продавца'                  => 'seller_inn',
        'Юридический адрес продавца'    => 'seller_address',
        'ФИО подписанта'                => 'signatory_name',
        'Должность подписанта'          => 'signatory_position',
        'Товарные позиции'              => 'products',
        'Номер документа'               => 'doc_number',
        'Дата документа'                => 'doc_date',
    ];

    public static function get(string $key): string
    {
        return self::MESSAGES[$key]
            ?? ('Заполните поле: ' . $key);
    }

    public static function fromMapKey(string $mapKey): string
    {
        return self::get($mapKey);
    }

    public static function fromLabel(string $label): string
    {
        $key = self::LABEL_TO_KEY[$label] ?? null;

        return $key !== null ? self::get($key) : ('Заполните: ' . $label);
    }

    /**
     * @param array<string, mixed> $crmData
     * @return string[]
     */
    public static function preValidate(array $crmData, string $entityType): array
    {
        $errors = [];
        $entity = $crmData['entity'] ?? [];
        $companyId = (int)($entity['COMPANY_ID'] ?? 0);

        if ($companyId <= 0) {
            $errors[] = self::get(
                $entityType === 'smart_invoice' ? 'invoice_company' : 'deal_company'
            );
        }

        $buyer = $crmData['buyer'] ?? [];
        if ($companyId > 0 && empty($buyer['REQUISITE_ID']) && empty($buyer['INN'])) {
            $errors[] = self::get('buyer_requisite');
        }

        $seller = $crmData['seller'] ?? [];
        if ($seller === [] || empty($seller['INN'])) {
            $errors[] = self::get('seller_requisites');
        }

        $products = $crmData['products'] ?? [];
        if (!is_array($products) || $products === []) {
            $errors[] = self::get('products');
        }

        return $errors;
    }

    public static function productQuantity(int $line, string $productName = ''): string
    {
        $name = $productName !== '' ? (' «' . $productName . '»') : (' (строка ' . $line . ')');

        return 'Заполните количество в блоке «Товары» для позиции' . $name . '.';
    }

    public static function productPrice(int $line, string $productName = ''): string
    {
        $name = $productName !== '' ? (' «' . $productName . '»') : (' (строка ' . $line . ')');

        return 'Заполните цену в блоке «Товары» для позиции' . $name . '.';
    }

    public static function productSumMismatch(int $line, string $productName = ''): string
    {
        $name = $productName !== '' ? (' «' . $productName . '»') : (' (строка ' . $line . ')');

        return 'Проверьте суммы и НДС в блоке «Товары» для позиции' . $name . ' (количество × цена).';
    }

    public static function productTotalsMismatch(): string
    {
        return 'Итоговая сумма по товарам не сходится с суммой строк. Проверьте цены и количество в блоке «Товары».';
    }

    /**
     * @param string[] $errors
     */
    public static function formatList(array $errors): string
    {
        $errors = array_values(array_filter(array_unique($errors)));
        if ($errors === []) {
            return 'Не удалось сформировать УПД.';
        }

        if (count($errors) === 1) {
            return $errors[0];
        }

        return 'Не удалось сформировать УПД. Заполните или исправьте:' . "\n• "
            . implode("\n• ", $errors);
    }
}
