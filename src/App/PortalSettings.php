<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\App;

use Ooofix\XmlupdCloud\Core\Contract\ConfigInterface;
use Ooofix\XmlupdCloud\Storage\SettingsRepository;

/** Настройки портала — реализация ConfigInterface для ядра XML */
final class PortalSettings implements ConfigInterface
{
    /** @var array<string, string> */
    private array $cache;

    public function __construct(
        private readonly int $portalId,
        ?SettingsRepository $repository = null,
    ) {
        $repository ??= new SettingsRepository();
        $this->cache = $repository->getAll($portalId);
    }

    public function dadataApiKey(): string
    {
        return $this->cache['dadata_api_key'] ?? '';
    }

    public function sellerRequisiteId(): int
    {
        return (int)($this->cache['seller_requisite_id'] ?? 0);
    }

    public function signatoryUserId(): int
    {
        return (int)($this->cache['signatory_user_id'] ?? 0);
    }

    public function signatoryMode(): string
    {
        return (string)($this->cache['signatory_mode'] ?? 'settings');
    }

    public function signatoryPosition(): string
    {
        return (string)($this->cache['signatory_position'] ?? 'Сотрудник');
    }

    public function signatoryUserName(): string
    {
        return trim((string)($this->cache['signatory_user_name'] ?? ''));
    }

    public function smartInvoiceTypeId(): int
    {
        $id = (int)($this->cache['smart_invoice_type_id'] ?? 31);

        return $id > 0 ? $id : 31;
    }

    public function smartInvoiceSpaId(): int
    {
        return max(0, (int)($this->cache['smart_invoice_spa_id'] ?? 0));
    }

    public function publishTimeline(): bool
    {
        return ($this->cache['publish_timeline'] ?? 'Y') === 'Y';
    }

    public function xsdPath(): string
    {
        $path = (string)($this->cache['xsd_path'] ?? '');

        return $path !== '' && is_file($path) ? $path : '';
    }

    public function updFunction(): string
    {
        return (string)($this->cache['upd_function'] ?? 'СЧФДОП');
    }

    public function fileEncoding(): string
    {
        return (string)($this->cache['file_encoding'] ?? 'windows-1251');
    }

    public function mappingPath(): string
    {
        return dirname(__DIR__, 2) . '/src/Core/config/mapping/upd.php';
    }

    public function crmAdapter(): string
    {
        return 'cloud';
    }

    public function cloudRestWebhook(): string
    {
        return '';
    }

    public function xmlFormatVersion(): string
    {
        $version = (string)($this->cache['xml_format_version'] ?? '5.03');

        return in_array($version, ['5.02', '5.03'], true) ? $version : '5.03';
    }

    public function xsdSchemaRevision(): string
    {
        return (string)($this->cache['xsd_schema_revision'] ?? 'auto');
    }

    public function calculationMode(): string
    {
        $mode = strtoupper((string)($this->cache['calculation_mode'] ?? '1C'));

        return $mode === 'BITRIX24' ? 'BITRIX24' : '1C';
    }

    public function addressSource(): string
    {
        return (string)($this->cache['address_source'] ?? 'requisite');
    }
}
