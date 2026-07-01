<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\App;

/** Коды и названия CRM-триггеров (как в модуле коробки) */
final class TriggerRegistry
{
    public const CODE_UPD_GENERATED = 'ooofix.xmlupd.upd.generated';
    public const CODE_EDO_SENT      = 'ooofix.xmlupd.edo.sent';
    public const CODE_EDO_DELIVERED = 'ooofix.xmlupd.edo.delivered';
    public const CODE_EDO_ACCEPTED  = 'ooofix.xmlupd.edo.accepted';
    public const CODE_EDO_REJECTED  = 'ooofix.xmlupd.edo.rejected';

    /** @return array<string, string> */
    public static function definitions(): array
    {
        return [
            self::CODE_UPD_GENERATED => 'УПД сформирован',
            self::CODE_EDO_SENT      => 'УПД отправлен в ЭДО',
            self::CODE_EDO_DELIVERED => 'УПД доставлен',
            self::CODE_EDO_ACCEPTED  => 'УПД принят',
            self::CODE_EDO_REJECTED  => 'УПД отклонён',
        ];
    }
}
