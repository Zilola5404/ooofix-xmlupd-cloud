<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\App;

/** Значения настроек по умолчанию (аналог default_option.php модуля коробки). */
final class DefaultOptions
{
    /** @return array<string, string> */
    public static function all(): array
    {
        return [
            'dadata_api_key'        => '',
            'seller_requisite_id'   => '',
            'signatory_mode'        => 'settings',
            'signatory_user_id'     => '',
            'signatory_user_name'   => '',
            'signatory_position'    => 'Сотрудник',
            'smart_invoice_type_id' => '31',
            'publish_timeline'      => 'Y',
            'xsd_path'              => '',
            'upd_function'          => 'СЧФДОП',
            'file_encoding'         => 'windows-1251',
            'xml_format_version'    => '5.03',
            'xsd_schema_revision'   => 'auto',
            'calculation_mode'      => '1C',
            'address_source'        => 'requisite',
        ];
    }
}
