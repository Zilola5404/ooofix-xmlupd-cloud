<?php

namespace Ooofix\XmlupdCloud\App;

/** Схема полей настроек — единый источник для UI и валидации. */
final class SettingsFieldRegistry
{
    /** @return array<string, array{title: string, sort: int}> */
    public static function sections(): array
    {
        return [
            'seller'     => ['title' => 'Организация и подписант', 'sort' => 100],
            'crm'        => ['title' => 'CRM и смарт-процессы', 'sort' => 200],
            'document'   => ['title' => 'Параметры генерации', 'sort' => 300],
            'xml'        => ['title' => 'XML и XSD', 'sort' => 400],
        ];
    }

    /**
     * @return array<string, array{
     *   section: string,
     *   type: string,
     *   label: string,
     *   hint?: string,
     *   default?: string,
     *   options?: array<string, string>,
     *   validate?: array<string, mixed>
     * }>
     */
    public static function fields(): array
    {
        return [
            'seller_requisite_id' => [
                'section' => 'seller',
                'type'    => 'integer',
                'label'   => 'ID реквизита продавца',
                'hint'    => 'Пусто — автоматически первый реквизит из «Мои реквизиты» CRM.',
                'placeholder' => 'ID реквизита',
                'default' => '',
                'validate'=> ['integer' => true, 'min' => 0],
            ],
            'signatory_mode' => [
                'section' => 'seller',
                'type'    => 'select',
                'label'   => 'Режим выбора подписанта',
                'options' => [
                    'settings'     => 'Указанный пользователь',
                    'current_user' => 'Текущий пользователь',
                ],
                'default' => 'settings',
                'validate'=> ['enum' => ['settings', 'by_position', 'current_user']],
            ],
            'signatory_user_id' => [
                'section' => 'seller',
                'type'    => 'user',
                'label'   => 'Подписант документов',
                'hint'    => 'Нажмите «Изменить» и выберите пользователя в списке',
                'default' => '',
                'validate'=> ['integer' => true, 'min' => 0],
            ],
            'signatory_user_name' => [
                'section' => 'seller',
                'type'    => 'string',
                'label'   => 'ФИО подписанта',
                'hint'    => 'Для XML-файла. Заполняется при выборе пользователя или вручную (если нет REST-права user).',
                'placeholder' => 'Иванов Иван Иванович',
                'default' => '',
                'validate'=> ['maxLength' => 255],
            ],
            'signatory_position' => [
                'section' => 'seller',
                'type'    => 'string',
                'label'   => 'Должность подписанта',
                'default' => 'Сотрудник',
                'validate'=> ['maxLength' => 128],
            ],
            'dadata_api_key' => [
                'section' => 'seller',
                'type'    => 'password',
                'label'   => 'API-ключ DaData',
                'hint'    => 'Для нормализации адресов при формировании XML',
                'default' => '',
                'validate'=> ['maxLength' => 255],
            ],
            'address_source' => [
                'section' => 'seller',
                'type'    => 'select',
                'label'   => 'Тип адреса для документов',
                'hint'    => 'Источник адреса контрагента в XML',
                'options' => [
                    'requisite' => 'Реквизиты CRM',
                    'dadata'    => 'DaData (при наличии ключа)',
                    'text'      => 'Текстовое поле реквизита',
                ],
                'default' => 'requisite',
                'validate'=> ['enum' => ['requisite', 'dadata', 'text']],
            ],
            'smart_invoice_type_id' => [
                'section' => 'crm',
                'type'    => 'crm_dynamic_type',
                'label'   => 'Смарт-процесс «Счета»',
                'hint'    => 'Выберите из списка CRM или нажмите «Определить СП»',
                'default' => '31',
                'validate'=> ['integer' => true, 'min' => 1],
            ],
            'publish_timeline' => [
                'section' => 'crm',
                'type'    => 'checkbox',
                'label'   => 'Публиковать в таймлайн CRM',
                'default' => 'Y',
            ],
            'upd_function' => [
                'section' => 'document',
                'type'    => 'string',
                'label'   => 'Функция документа (Функция)',
                'default' => 'СЧФДОП',
                'validate'=> ['maxLength' => 32],
            ],
            'calculation_mode' => [
                'section' => 'document',
                'type'    => 'select',
                'label'   => 'Режим расчёта сумм УПД',
                'options' => [
                    '1C'       => '1С / ЭДО — итоги из сделки',
                    'BITRIX24' => 'Битрикс24 — сумма строк товаров',
                ],
                'default' => '1C',
                'validate'=> ['enum' => ['1C', 'BITRIX24']],
            ],
            'xml_format_version' => [
                'section' => 'xml',
                'type'    => 'select',
                'label'   => 'Версия XML УПД (ВерсФорм)',
                'options' => [
                    '5.03' => '5.03 (ФНС №970)',
                    '5.02' => '5.02',
                ],
                'default' => '5.03',
                'validate'=> ['enum' => ['5.03', '5.02']],
            ],
            'xsd_schema_revision' => [
                'section' => 'xml',
                'type'    => 'select',
                'label'   => 'Ревизия XSD (5.03)',
                'options' => [
                    'auto'  => 'Авто',
                    '03_05' => '03_05',
                    '03_04' => '03_04',
                ],
                'default' => 'auto',
                'validate'=> ['enum' => ['auto', '03_05', '03_04']],
            ],
            'file_encoding' => [
                'section' => 'xml',
                'type'    => 'select',
                'label'   => 'Кодировка XML',
                'options' => [
                    'windows-1251' => 'windows-1251 (Диадок)',
                    'UTF-8'        => 'UTF-8',
                ],
                'default' => 'windows-1251',
                'validate'=> ['enum' => ['windows-1251', 'UTF-8']],
            ],
            'crm_adapter' => [
                'section' => 'environment',
                'type'    => 'select',
                'label'   => 'Режим CRM-адаптера',
                'options' => [
                    'auto'   => 'Авто',
                    'cloud'  => 'Облако',
                    'onprem' => 'Локальный сервер',
                ],
                'default' => 'auto',
                'validate'=> ['enum' => ['auto', 'cloud', 'onprem']],
            ],
            'cloud_rest_webhook' => [
                'section' => 'environment',
                'type'    => 'string',
                'label'   => 'REST webhook (облако)',
                'hint'    => 'https://portal.bitrix24.ru/rest/1/xxx/',
                'default' => '',
                'validate'=> ['maxLength' => 512],
            ],
        ];
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::fields());
    }
}
