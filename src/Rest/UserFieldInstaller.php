<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Rest;

use Ooofix\XmlupdCloud\App\AppScopes;

/**
 * Создание UF для сделок (crm.deal.userfield.* / userfieldconfig) и смарт-процессов.
 */
final class UserFieldInstaller
{
    private const LABEL_NUMBER = 'Номер УПД (1С)';
    private const LABEL_FILE   = 'Файл УПД';
    private const DEAL_ENTITY  = 'CRM_DEAL';

    /** @var list<string> */
    private array $log = [];

    /** @var list<string> */
    private array $errors = [];

    /**
     * @return array{success: bool, message: string, log: list<string>, errors: list<string>}
     */
    public function installAllResult(BitrixClient $client, int $smartEntityTypeId): array
    {
        $this->log = [];
        $this->errors = [];

        $this->installForDeals($client);

        if ($smartEntityTypeId > 0) {
            $this->installForSmartType($client, $smartEntityTypeId);
        }

        $dealOk = $this->dealFieldsReady($client);
        $success = $dealOk;
        $message = $dealOk
            ? ($this->errors === []
                ? 'Поля UF_UPD_NUMBER и UF_UPD_FILE проверены/созданы'
                : 'Поля для сделок готовы. Смарт-процесс: ' . implode('; ', $this->errors))
            : 'Не удалось создать поля для сделок: ' . implode('; ', $this->errors);

        return [
            'success' => $success,
            'message' => $message,
            'log'     => $this->log,
            'errors'  => $this->errors,
        ];
    }

    public function installAll(BitrixClient $client, int $smartEntityTypeId): void
    {
        $result = $this->installAllResult($client, $smartEntityTypeId);
        if (!$result['success'] && !$this->dealFieldsReady($client)) {
            throw new \RuntimeException($result['message']);
        }
    }

    /** @return list<string> */
    public function getLog(): array
    {
        return $this->log;
    }

    /** @return list<string> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function installForDeals(BitrixClient $client): void
    {
        $this->ensureDealField($client, UserFieldCodes::DEAL_NUMBER, 'string', self::LABEL_NUMBER);
        $this->ensureDealField($client, UserFieldCodes::DEAL_FILE, 'file', self::LABEL_FILE);
    }

    public function installForSmartType(BitrixClient $client, int $entityTypeId): void
    {
        $meta = UserFieldCodes::resolveSmartType($client, $entityTypeId);
        if ($meta === null) {
            $this->log[] = 'СП entityTypeId=' . $entityTypeId . ': тип не найден в crm.type.list (пропущено)';

            return;
        }

        $pairs = [
            [$meta['fieldNumber'], 'string', self::LABEL_NUMBER],
            [$meta['fieldFile'], 'file', self::LABEL_FILE],
        ];

        foreach ($pairs as [$fieldName, $userTypeId, $label]) {
            $this->ensureSmartField($client, $meta['entityId'], $fieldName, $userTypeId, $label);
        }
    }

    private function dealFieldsReady(BitrixClient $client): bool
    {
        return $this->dealFieldExists($client, UserFieldCodes::DEAL_NUMBER)
            && $this->dealFieldExists($client, UserFieldCodes::DEAL_FILE);
    }

    private function ensureDealField(
        BitrixClient $client,
        string $fieldName,
        string $userTypeId,
        string $label,
    ): void {
        if ($this->dealFieldExists($client, $fieldName)) {
            $this->log[] = 'Сделки: поле ' . $fieldName . ' уже существует';

            return;
        }

        $lastError = '';

        try {
            $client->call('crm.deal.userfield.add', [
                'fields' => $this->buildDealFieldPayload($fieldName, $userTypeId, $label),
            ]);
            $this->log[] = 'Сделки: создано поле ' . $fieldName . ' (crm.deal.userfield.add)';

            return;
        } catch (\Throwable $e) {
            $lastError = $e->getMessage();
            if ($this->isAlreadyExistsError($e)) {
                $this->log[] = 'Сделки: поле ' . $fieldName . ' уже существует';

                return;
            }
        }

        try {
            $client->call('userfieldconfig.add', [
                'moduleId' => 'crm',
                'field'    => $this->buildSmartFieldPayload(self::DEAL_ENTITY, $fieldName, $userTypeId, $label),
            ]);
            $this->log[] = 'Сделки: создано поле ' . $fieldName . ' (userfieldconfig.add)';

            return;
        } catch (\Throwable $e) {
            if ($this->isAlreadyExistsError($e)) {
                $this->log[] = 'Сделки: поле ' . $fieldName . ' уже существует';

                return;
            }

            $this->errors[] = 'Сделки ' . $fieldName . ': '
                . self::shortError($lastError !== '' ? $lastError : $e->getMessage());
        }
    }

    private function ensureSmartField(
        BitrixClient $client,
        string $entityId,
        string $fieldName,
        string $userTypeId,
        string $label,
    ): void {
        if ($entityId === '') {
            return;
        }

        if ($this->smartFieldExists($client, $entityId, $fieldName)) {
            $this->log[] = $entityId . ': поле ' . $fieldName . ' уже существует';

            return;
        }

        try {
            $client->call('userfieldconfig.add', [
                'moduleId' => 'crm',
                'field'    => $this->buildSmartFieldPayload($entityId, $fieldName, $userTypeId, $label),
            ]);
            $this->log[] = $entityId . ': создано поле ' . $fieldName;
        } catch (\Throwable $e) {
            if ($this->isAlreadyExistsError($e)) {
                $this->log[] = $entityId . ': поле ' . $fieldName . ' уже существует';

                return;
            }

            $message = self::shortError($e->getMessage());
            if ($this->isScopeError($e)) {
                $message .= '. ' . AppScopes::vendorHint();
            }

            $this->errors[] = $entityId . ' ' . $fieldName . ': ' . $message;
            $this->log[] = $entityId . ': ошибка ' . $fieldName . ' — ' . $message;
        }
    }

    private function dealFieldExists(BitrixClient $client, string $fieldName): bool
    {
        if ($this->smartFieldExists($client, self::DEAL_ENTITY, $fieldName)) {
            return true;
        }

        try {
            $list = $client->result('crm.deal.userfield.list', [
                'filter' => ['FIELD_NAME' => $fieldName],
            ]);

            return is_array($list) && $list !== [];
        } catch (\Throwable) {
            return false;
        }
    }

    private function smartFieldExists(BitrixClient $client, string $entityId, string $fieldName): bool
    {
        try {
            $list = $client->result('userfieldconfig.list', [
                'moduleId' => 'crm',
                'filter'   => [
                    'entityId'  => $entityId,
                    'fieldName' => $fieldName,
                ],
            ]);

            if (is_array($list['fields'] ?? null) && $list['fields'] !== []) {
                return true;
            }

            return is_array($list) && $list !== [] && !isset($list['fields']);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed> */
    private function buildDealFieldPayload(string $fieldName, string $userTypeId, string $label): array
    {
        return [
            'FIELD_NAME'        => $fieldName,
            'USER_TYPE_ID'      => $userTypeId,
            'XML_ID'            => 'OOOFIX_XMLUPD_' . $fieldName,
            'SORT'              => 500,
            'MULTIPLE'          => 'N',
            'MANDATORY'         => 'N',
            'SHOW_FILTER'       => 'N',
            'SHOW_IN_LIST'      => 'N',
            'EDIT_IN_LIST'      => 'N',
            'IS_SEARCHABLE'     => 'N',
            'EDIT_FORM_LABEL'   => ['ru' => $label, 'en' => $label],
            'LIST_COLUMN_LABEL' => ['ru' => $label, 'en' => $label],
            'LIST_FILTER_LABEL' => ['ru' => $label, 'en' => $label],
        ];
    }

    /** @return array<string, mixed> */
    private function buildSmartFieldPayload(
        string $entityId,
        string $fieldName,
        string $userTypeId,
        string $label,
    ): array {
        return [
            'entityId'        => $entityId,
            'fieldName'       => $fieldName,
            'userTypeId'      => $userTypeId,
            'xmlId'           => 'OOOFIX_XMLUPD_' . $fieldName,
            'sort'            => 500,
            'multiple'        => 'N',
            'mandatory'       => 'N',
            'showFilter'      => 'N',
            'showInList'      => 'N',
            'editInList'      => 'N',
            'isSearchable'    => 'N',
            'editFormLabel'   => ['ru' => $label, 'en' => $label],
            'listColumnLabel' => ['ru' => $label, 'en' => $label],
            'listFilterLabel' => ['ru' => $label, 'en' => $label],
        ];
    }

    private function isAlreadyExistsError(\Throwable $e): bool
    {
        $msg = mb_strtolower($e->getMessage());

        return str_contains($msg, 'already exist')
            || str_contains($msg, 'уже существ')
            || str_contains($msg, 'duplicate')
            || str_contains($msg, 'дубликат');
    }

    private function isScopeError(\Throwable $e): bool
    {
        return AppScopes::isPrivilegeError($e);
    }

    private static function shortError(string $message): string
    {
        $message = preg_replace('/\.\s*На vendors\.bitrix24\.ru.*$/su', '', $message) ?? $message;

        return trim($message);
    }
}
