<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\App;

/**
 * REST scope и настройки карточки версии на vendors.bitrix24.ru.
 *
 * @see https://apidocs.bitrix24.ru/api-reference/scopes/permissions.html
 */
final class AppScopes
{
    /** @var list<string> */
    public const REST = [
        'crm',
        'userfieldconfig',
        'user',
        'disk',
        'bizproc',
        'placement',
    ];

    public static function vendorHint(): string
    {
        return 'На vendors.bitrix24.ru в версии приложения:'
            . ' 1) REST-права: ' . implode(', ', self::REST) . ' (disk — для хранилища приложения);'
            . ' 2) «Умные сценарии / шаблоны» = Да (нужно для bizproc.robot.add);'
            . ' 3) «Настраивать CRM» = Да;'
            . ' 4) «Виджеты в интерфейс» = Да.'
            . ' Опубликуйте версию и переустановите приложение на портале (обновит OAuth-токен).';
    }

    public static function isPrivilegeError(\Throwable $e): bool
    {
        $msg = mb_strtolower($e->getMessage());

        return str_contains($msg, 'higher privileges')
            || str_contains($msg, 'required more scopes')
            || str_contains($msg, 'more scopes')
            || str_contains($msg, 'insufficient_scope')
            || str_contains($msg, 'access denied');
    }
}
