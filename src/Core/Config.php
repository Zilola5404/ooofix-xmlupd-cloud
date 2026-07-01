<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core;

use Ooofix\XmlupdCloud\Core\Contract\ConfigInterface;

/**
 * Фасад настроек для ядра XML (скопировано из модуля коробки, без Bitrix).
 * Перед генерацией вызывайте Config::bindPortal($portalId).
 */
final class Config
{
    private static ?ConfigInterface $instance = null;

    public static function bindPortal(int $portalId): void
    {
        self::$instance = new \Ooofix\XmlupdCloud\App\PortalSettings($portalId);
    }

    public static function setInstance(?ConfigInterface $instance): void
    {
        self::$instance = $instance;
    }

    private static function i(): ConfigInterface
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Настройки не инициализированы. Вызовите Config::bindPortal()');
        }

        return self::$instance;
    }

    public static function dadataApiKey(): string
    {
        return self::i()->dadataApiKey();
    }

    public static function sellerRequisiteId(): int
    {
        return self::i()->sellerRequisiteId();
    }

    public static function signatoryUserId(): int
    {
        return self::i()->signatoryUserId();
    }

    public static function signatoryMode(): string
    {
        return self::i()->signatoryMode();
    }

    public static function signatoryPosition(): string
    {
        return self::i()->signatoryPosition();
    }

    public static function signatoryUserName(): string
    {
        return self::i()->signatoryUserName();
    }

    public static function smartInvoiceTypeId(): int
    {
        return self::i()->smartInvoiceTypeId();
    }

    public static function smartInvoiceSpaId(): int
    {
        return self::i()->smartInvoiceSpaId();
    }

    public static function publishTimeline(): bool
    {
        return self::i()->publishTimeline();
    }

    public static function xsdPath(): string
    {
        return self::i()->xsdPath();
    }

    public static function updFunction(): string
    {
        return self::i()->updFunction();
    }

    public static function fileEncoding(): string
    {
        return self::i()->fileEncoding();
    }

    public static function mappingPath(): string
    {
        return self::i()->mappingPath();
    }

    public static function crmAdapter(): string
    {
        return self::i()->crmAdapter();
    }

    public static function cloudRestWebhook(): string
    {
        return self::i()->cloudRestWebhook();
    }

    public static function xmlFormatVersion(): string
    {
        return self::i()->xmlFormatVersion();
    }

    public static function xsdSchemaRevision(): string
    {
        return self::i()->xsdSchemaRevision();
    }

    public static function calculationMode(): string
    {
        return self::i()->calculationMode();
    }
}
