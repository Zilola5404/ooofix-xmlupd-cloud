<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\App;

use Ooofix\XmlupdCloud\App\AppConfig;
use Ooofix\XmlupdCloud\Rest\AppDiskService;
use Ooofix\XmlupdCloud\Rest\BitrixClient;
use Ooofix\XmlupdCloud\Rest\PlacementRegistry;
use Ooofix\XmlupdCloud\Rest\UserFieldCodes;
use Ooofix\XmlupdCloud\Rest\UserFieldInstaller;
use Ooofix\XmlupdCloud\Storage\SettingsRepository;

/** Регистрация робота, триггеров, placements и UF-полей при установке */
final class InstallService
{
    private const ROBOT_CODE = 'ooofix_xmlupd_generate';
    private const ROBOT_NAME = 'Сформировать УПД (XML)';

    /** @var list<string> */
    private array $userFieldLog = [];

    /** @return list<string> предупреждения (установка считается успешной) */
    public function install(BitrixClient $client): array
    {
        $smartTypeId = $this->detectSmartInvoiceTypeId($client);
        $smartMeta = UserFieldCodes::resolveSmartType($client, $smartTypeId);
        $warnings = [];

        $this->installUserFields($client, $smartTypeId);

        try {
            (new AppDiskService($client, new SettingsRepository(), $client->portalId()))->ensureXmlFolder();
        } catch (\Throwable $e) {
            $warnings[] = $this->formatStepError('disk/XML', $e);
        }

        try {
            $this->registerRobot($client, $smartTypeId);
        } catch (\Throwable $e) {
            $warnings[] = $this->formatStepError('bizproc.robot.add', $e);
        }

        try {
            $this->registerTriggers($client);
        } catch (\Throwable $e) {
            $warnings[] = $this->formatStepError('crm.automation.trigger.add', $e);
        }

        $warnings = array_merge($warnings, $this->bindPlacements($client, $smartMeta));
        $warnings = array_merge($warnings, (new MenuPlacementService())->bindLeftMenu($client));
        $this->seedDefaultSettings($client->portalId(), $smartTypeId, $smartMeta);

        return $warnings;
    }

    /** Обновить URL кнопки в карточке CRM (после деплоя новой версии). */
    /** @return list<string> */
    public function refreshPlacements(BitrixClient $client): array
    {
        $smartTypeId = $this->detectSmartInvoiceTypeId($client);
        $smartMeta = UserFieldCodes::resolveSmartType($client, $smartTypeId);

        return $this->bindPlacements($client, $smartMeta);
    }

    /** @return list<string> */
    public function getUserFieldLog(): array
    {
        return $this->userFieldLog;
    }

    public function uninstall(BitrixClient $client): void
    {
        try {
            $client->call('bizproc.robot.delete', ['CODE' => self::ROBOT_CODE]);
        } catch (\Throwable) {
        }

        foreach (array_keys(TriggerRegistry::definitions()) as $code) {
            try {
                $client->call('crm.automation.trigger.delete', ['CODE' => $code]);
            } catch (\Throwable) {
            }
        }

        $available = PlacementRegistry::listAvailable($client);
        foreach (PlacementRegistry::toolbarCandidates(null) as $placement) {
            $this->unbindPlacement($client, $placement, $available);
        }
        foreach (PlacementRegistry::toolbarCandidates(UserFieldCodes::resolveSmartType($client, ConfigHelper::defaultSmartInvoiceTypeId())) as $placement) {
            $this->unbindPlacement($client, $placement, $available);
        }

        (new MenuPlacementService())->unbindLeftMenu($client);
    }

    private function registerRobot(BitrixClient $client, int $smartEntityTypeId): void
    {
        try {
            $client->call('bizproc.robot.delete', ['CODE' => self::ROBOT_CODE]);
        } catch (\Throwable) {
        }

        $filterInclude = [
            ['crm', 'CCrmDocumentDeal', 'DEAL'],
        ];

        if ($smartEntityTypeId > 0) {
            $filterInclude[] = ['crm', 'Bitrix\Crm\Integration\BizProc\Document\Dynamic', 'DYNAMIC_' . $smartEntityTypeId];
            $filterInclude[] = ['crm', 'Bitrix\Crm\Integration\BizProc\Document\SmartInvoice', 'SMART_INVOICE'];
        }

        $client->call('bizproc.robot.add', [
            'CODE'              => self::ROBOT_CODE,
            'HANDLER'           => AppConfig::appUrl() . '/handler/robot.php',
            'USE_SUBSCRIPTION'  => 'Y',
            'NAME'              => self::ROBOT_NAME,
            'PROPERTIES'        => [],
            'RETURN_PROPERTIES' => [
                'Success'  => ['Name' => 'Успех', 'Type' => 'bool'],
                'FileId'   => ['Name' => 'ID файла', 'Type' => 'int'],
                'FileName' => ['Name' => 'Имя файла', 'Type' => 'string'],
                'Version'  => ['Name' => 'Версия', 'Type' => 'int'],
                'Message'  => ['Name' => 'Сообщение', 'Type' => 'string'],
                'Errors'   => ['Name' => 'Ошибки', 'Type' => 'string'],
            ],
            'FILTER' => [
                'INCLUDE' => $filterInclude,
            ],
        ]);
    }

    private function registerTriggers(BitrixClient $client): void
    {
        foreach (TriggerRegistry::definitions() as $code => $name) {
            try {
                $client->call('crm.automation.trigger.add', [
                    'CODE' => $code,
                    'NAME' => $name,
                ]);
            } catch (\Throwable $e) {
                if (!$this->isAlreadyRegisteredError($e)) {
                    throw $e;
                }
            }
        }
    }

    private function installUserFields(BitrixClient $client, int $smartTypeId): void
    {
        $installer = new UserFieldInstaller();
        $installer->installAll($client, $smartTypeId);
        $this->userFieldLog = $installer->getLog();
    }

    /**
     * @param array<string, mixed>|null $smartMeta
     * @return list<string>
     */
    private function bindPlacements(BitrixClient $client, ?array $smartMeta): array
    {
        $base = AppConfig::appUrl();
        $available = PlacementRegistry::listAvailable($client);
        $candidates = PlacementRegistry::filterAvailable(
            PlacementRegistry::toolbarCandidates($smartMeta),
            $available
        );

        if ($available !== [] && $candidates === []) {
            return ['placement.bind: на портале нет доступных CRM toolbar placements для этого приложения'];
        }

        $warnings = [];
        $bound = 0;

        foreach ($candidates as $placement) {
            $entity = $placement === PlacementRegistry::DEAL_TOOLBAR ? 'deal' : 'smart_invoice';
            $handler = $base . '/placements/deal-button.php?entity=' . $entity
                . '&v=' . rawurlencode(AssetVersion::get());

            try {
                $client->call('placement.bind', [
                    'PLACEMENT' => $placement,
                    'HANDLER'   => $handler,
                    'TITLE'     => 'Сформировать УПД',
                ]);
                ++$bound;
            } catch (\Throwable $e) {
                if ($this->isAlreadyRegisteredError($e)) {
                    ++$bound;
                    continue;
                }
                if ($this->isPlacementNotFoundError($e)) {
                    continue;
                }
                $warnings[] = $placement . ': ' . $e->getMessage();
            }
        }

        if ($bound === 0 && $warnings === []) {
            $warnings[] = 'placement.bind: не удалось привязать кнопку в карточке CRM';
        }

        return $warnings;
    }

    /** @param list<string> $available */
    private function unbindPlacement(BitrixClient $client, string $placement, array $available): void
    {
        if ($available !== [] && !in_array($placement, $available, true)) {
            return;
        }

        try {
            $client->call('placement.unbind', ['PLACEMENT' => $placement]);
        } catch (\Throwable) {
        }
    }

    private function formatStepError(string $step, \Throwable $e): string
    {
        $message = $step . ': ' . self::shortRestMessage($e->getMessage());
        if (AppScopes::isPrivilegeError($e)) {
            $message .= ' (нет scope в OAuth-токене)';
        }

        return $message;
    }

    private static function shortRestMessage(string $message): string
    {
        $message = preg_replace('/\.\s*На vendors\.bitrix24\.ru.*$/su', '', $message) ?? $message;
        $message = preg_replace('/\s*— включите «Умные сценарии.*$/su', '', $message) ?? $message;

        return trim($message);
    }

    private function isAlreadyRegisteredError(\Throwable $e): bool
    {
        $msg = mb_strtolower($e->getMessage());

        return str_contains($msg, 'already')
            || str_contains($msg, 'уже')
            || str_contains($msg, 'installed');
    }

    private function isPlacementNotFoundError(\Throwable $e): bool
    {
        $msg = mb_strtolower($e->getMessage());

        return str_contains($msg, 'placement not found')
            || str_contains($msg, 'placement_not_found');
    }

    private function detectSmartInvoiceTypeId(BitrixClient $client): int
    {
        try {
            foreach (UserFieldCodes::listTypes($client) as $type) {
                $title = mb_strtolower((string)($type['title'] ?? ''));
                if (str_contains($title, 'счет') || str_contains($title, 'счёт')) {
                    return (int)($type['entityTypeId'] ?? 0);
                }
            }
        } catch (\Throwable) {
        }

        return ConfigHelper::defaultSmartInvoiceTypeId();
    }

    /** @param array<string, mixed>|null $smartMeta */
    private function seedDefaultSettings(int $portalId, int $smartTypeId, ?array $smartMeta = null): void
    {
        if ($portalId <= 0) {
            return;
        }

        $repo = new SettingsRepository();
        $existing = $repo->getAll($portalId);
        $defaults = DefaultOptions::all();

        $toSave = [];
        foreach ($defaults as $key => $value) {
            if (($existing[$key] ?? '') === '' && $value !== '') {
                $toSave[$key] = $value;
            }
        }

        if ($smartTypeId > 0 && ($existing['smart_invoice_type_id'] ?? '') === '') {
            $toSave['smart_invoice_type_id'] = (string)$smartTypeId;
        }

        if (is_array($smartMeta) && ($existing['smart_invoice_spa_id'] ?? '') === '') {
            $toSave['smart_invoice_spa_id'] = (string)($smartMeta['spaId'] ?? '');
        }

        if ($toSave !== []) {
            $repo->saveAll($portalId, $toSave);
        }
    }
}

/** Вспомогательный класс для InstallService */
final class ConfigHelper
{
    public static function defaultSmartInvoiceTypeId(): int
    {
        return 31;
    }
}
