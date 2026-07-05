<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Storage;

/** Единый формат строк из MySQL (UPPER / lower) для API и UI. */
final class RowMapper
{
    /** @param array<string, mixed> $row */
    public static function log(array $row): array
    {
        $status = self::str($row, 'STATUS', 'status');

        return [
            'ID'          => self::int($row, 'ID', 'id'),
            'ENTITY_TYPE' => self::str($row, 'ENTITY_TYPE', 'entity_type'),
            'ENTITY_ID'   => self::int($row, 'ENTITY_ID', 'entity_id'),
            'STATUS'      => $status,
            'STATUS_LABEL'=> self::logStatusLabel($status),
            'MESSAGE'     => self::str($row, 'MESSAGE', 'message'),
            'CREATED_AT'  => self::formatDateTime(self::str($row, 'CREATED_AT', 'created_at')),
        ];
    }

    /** @param array<string, mixed> $row */
    public static function document(array $row): array
    {
        return [
            'ID'          => self::int($row, 'ID', 'id'),
            'ENTITY_TYPE' => self::str($row, 'ENTITY_TYPE', 'entity_type'),
            'ENTITY_ID'   => self::int($row, 'ENTITY_ID', 'entity_id'),
            'DOC_NUMBER'  => self::str($row, 'DOC_NUMBER', 'doc_number'),
            'FILE_NAME'   => self::str($row, 'FILE_NAME', 'file_name'),
            'FILE_ID'     => self::int($row, 'FILE_ID', 'file_id'),
            'VERSION'     => self::int($row, 'VERSION', 'version'),
            'CREATED_AT'  => self::formatDateTime(self::str($row, 'CREATED_AT', 'created_at')),
        ];
    }

    public static function crmPath(string $entityType, int $entityId, int $smartTypeId = 0): string
    {
        if ($entityId <= 0) {
            return '';
        }

        return match ($entityType) {
            'deal'          => '/crm/deal/details/' . $entityId . '/',
            'smart_invoice' => $smartTypeId > 0
                ? '/crm/type/' . $smartTypeId . '/details/' . $entityId . '/'
                : '',
            default         => '/crm/deal/details/' . $entityId . '/',
        };
    }

    public static function logStatusLabel(string $status): string
    {
        return match ($status) {
            LogRepository::STATUS_SUCCESS  => 'Успех',
            LogRepository::STATUS_ERROR    => 'Ошибка',
            LogRepository::STATUS_VALIDATE => 'Ошибка XSD',
            LogRepository::STATUS_STARTED  => 'Запуск',
            default                        => $status !== '' ? $status : '—',
        };
    }

    /** @param array<string, mixed> $row */
    private static function int(array $row, string $upper, string $lower): int
    {
        if (array_key_exists($upper, $row)) {
            return (int)$row[$upper];
        }

        if (array_key_exists($lower, $row)) {
            return (int)$row[$lower];
        }

        return 0;
    }

    /** @param array<string, mixed> $row */
    private static function str(array $row, string $upper, string $lower): string
    {
        if (isset($row[$upper]) && $row[$upper] !== null && $row[$upper] !== '') {
            return (string)$row[$upper];
        }

        if (isset($row[$lower]) && $row[$lower] !== null) {
            return (string)$row[$lower];
        }

        return '';
    }

    private static function formatDateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return date('d.m.Y H:i', $timestamp);
    }
}
