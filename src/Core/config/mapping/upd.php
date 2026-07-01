<?php

/**
 * Карта соответствия полей B24 → узлы XML УПД.
 * label — текст ошибки для пользователя.
 * fallback — ключ в секции данных, если field пуст.
 */
return [
    'doc_number' => [
        'source'   => 'entity',
        'field'    => 'UF_UPD_NUMBER',
        'fallback' => 'ID',
        'required' => true,
        'label'    => 'Номер документа',
    ],
    'doc_date' => [
        'source'   => 'entity',
        'field'    => 'DOC_DATE',
        'required' => true,
        'label'    => 'Дата документа',
    ],
    'buyer_name' => [
        'source'   => 'buyer',
        'field'    => 'NAME',
        'required' => true,
        'label'    => 'Наименование покупателя',
    ],
    'buyer_inn' => [
        'source'   => 'buyer',
        'field'    => 'INN',
        'required' => true,
        'label'    => 'ИНН покупателя',
    ],
    'buyer_kpp' => [
        'source'   => 'buyer',
        'field'    => 'KPP',
        'required' => false,
        'label'    => 'КПП покупателя',
    ],
    'buyer_address' => [
        'source'   => 'buyer',
        'field'    => 'ADDRESS_FULL',
        'required' => true,
        'label'    => 'Юридический адрес покупателя',
    ],
    'buyer_bank_rs' => [
        'source'   => 'buyer',
        'field'    => 'BANK_RS',
        'required' => false,
        'label'    => 'Расчётный счёт покупателя',
    ],
    'seller_name' => [
        'source'   => 'seller',
        'field'    => 'NAME',
        'required' => true,
        'label'    => 'Наименование продавца',
    ],
    'seller_inn' => [
        'source'   => 'seller',
        'field'    => 'INN',
        'required' => true,
        'label'    => 'ИНН продавца',
    ],
    'seller_kpp' => [
        'source'   => 'seller',
        'field'    => 'KPP',
        'required' => false,
        'label'    => 'КПП продавца',
    ],
    'seller_address' => [
        'source'   => 'seller',
        'field'    => 'ADDRESS_FULL',
        'required' => true,
        'label'    => 'Юридический адрес продавца',
    ],
    'signatory_name' => [
        'source'   => 'signatory',
        'field'    => 'NAME',
        'required' => true,
        'label'    => 'ФИО подписанта',
    ],
    'signatory_position' => [
        'source'   => 'signatory',
        'field'    => 'POSITION',
        'required' => true,
        'label'    => 'Должность подписанта',
    ],
    'products' => [
        'source'   => 'products',
        'field'    => 'LIST',
        'required' => true,
        'label'    => 'Товарные позиции',
    ],
];
