<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Contract;

/** Контракт настроек модуля (для DI и unit-тестов). */
interface ConfigInterface
{
    public function dadataApiKey(): string;

    public function sellerRequisiteId(): int;

    public function signatoryUserId(): int;

    public function signatoryMode(): string;

    public function signatoryPosition(): string;

    public function smartInvoiceTypeId(): int;

    public function publishTimeline(): bool;

    public function xsdPath(): string;

    public function updFunction(): string;

    public function fileEncoding(): string;

    public function mappingPath(): string;

    public function crmAdapter(): string;

    public function cloudRestWebhook(): string;

    public function xmlFormatVersion(): string;

    public function xsdSchemaRevision(): string;

    /** 1C | BITRIX24 */
    public function calculationMode(): string;
}
