<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Rest;

use Ooofix\XmlupdCloud\App\AppScopes;

/**
 * Создание UF для сделок (crm.deal.userfield.*) и смарт-процессов (userfieldconfig.*).
 */
final class UserFieldInstaller
{
    private const LABEL_NUMBER = 'Номер УПД (1С)';
    private const LABEL_FILE   = 'Файл УПД';

    /** @var list<string> */
    private array $log = [];

    public function installAll(BitrixClient $client, int $smartEntityTypeId): void
    {
        $this->installForDeals($client);

        if ($smartEntityTypeId > 0) {
            $this->installForSmartType($client, $smartEntityTypeId);
        }
    }

    /** @return list<string> */
    public function getLog(): array
    {
        return $this->log;
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
            $this->log[] = 'СП entityTypeId=' . $entityTypeId . ': тип не найден в crm.type.list';

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

  /** @deprecated Используйте UserFieldCodes::resolveSmartType */
    public static function resolveUserFieldEntityIds(BitrixClient $client, int $entityTypeId): array
    {
        $meta = UserFieldCodes::resolveSmartType($client, $entityTypeId);

        return $meta !== null ? [$meta['entityId']] : ['CRM_' . $entityTypeId];
    }

    /** @deprecated Используйте UserFieldCodes::resolveSmartType */
    public static function resolveUserFieldEntityId(BitrixClient $client, int $entityTypeId): string
    {
        $ids = self::resolveUserFieldEntityIds($client, $entityTypeId);

        return $ids[0] ?? ('CRM_' . $entityTypeId);
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

        try {
            $client->call('crm.deal.userfield.add', [
                'fields' => $this->buildDealFieldPayload($fieldName, $userTypeId, $label),
            ]);
            $this->log[] = 'Сделки: создано поле ' . $fieldName;
        } catch (\Throwable $e) {
            if ($this->isAlreadyExistsError($e)) {
                $this->log[] = 'Сделки: поле ' . $fieldName . ' уже существует';

                return;
            }

            throw new \RuntimeException(
                'Не удалось создать поле ' . $fieldName . ' для сделок: ' . $e->getMessage(),
                0,
                $e
            );
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

            if ($this->isScopeError($e)) {
                throw new \RuntimeException(
                    'Недостаточно прав для userfieldconfig.add (смарт-процесс ' . $entityId . '). '
                    . AppScopes::vendorHint(),
                    0,
                    $e
                );
            }

            throw new \RuntimeException(
                'Не удалось создать поле ' . $fieldName . ' для ' . $entityId . ': ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function dealFieldExists(BitrixClient $client, string $fieldName): bool
    {
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
        $msg = mb_strtolower($e->getMessage());

        return str_contains($msg, 'higher privileges')
            || str_contains($msg, 'required more scopes')
            || str_contains($msg, 'more scopes');
    }
}
