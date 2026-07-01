<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core;

use Ooofix\XmlupdCloud\Storage\Database;
use Ooofix\XmlupdCloud\Storage\LogRepository;

/**
 * Централизованное логирование с request_id, portal_id, crm_entity_id.
 */
final class Logger
{
    public static function generateRequestId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function info(
        string $message,
        ?string $entityType = null,
        int $entityId = 0,
        string $status = LogRepository::STATUS_STARTED,
    ): void {
        self::write($status, $message, $entityType, $entityId);
    }

    public static function error(string $message, ?string $entityType = null, int $entityId = 0): void
    {
        self::write(LogRepository::STATUS_ERROR, $message, $entityType, $entityId);
    }

    public static function success(string $message, ?string $entityType = null, int $entityId = 0): void
    {
        self::write(LogRepository::STATUS_SUCCESS, $message, $entityType, $entityId);
    }

    public static function validateError(string $message, ?string $entityType = null, int $entityId = 0): void
    {
        self::write(LogRepository::STATUS_VALIDATE, $message, $entityType, $entityId);
    }

    private static function write(
        string $status,
        string $message,
        ?string $entityType,
        int $entityId,
    ): void {
        $tenant = Tenant::tryCurrent();
        if ($tenant === null) {
            error_log(sprintf('[ooofix-xmlupd] %s: %s', $status, $message));

            return;
        }

        try {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO b_xmldoc_log
                 (PORTAL_ID, REQUEST_ID, ENTITY_TYPE, ENTITY_ID, STATUS, MESSAGE, CREATED_AT)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $tenant->portalId,
                $tenant->requestId,
                $entityType ?? 'system',
                max(0, $entityId),
                $status,
                $message,
            ]);
        } catch (\Throwable $e) {
            error_log('[ooofix-xmlupd] log failed: ' . $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    public static function contextExtra(?string $entityType = null, int $entityId = 0): array
    {
        $tenant = Tenant::tryCurrent();

        return [
            'request_id'     => $tenant?->requestId,
            'portal_id'      => $tenant?->portalId,
            'crm_entity_type'=> $entityType,
            'crm_entity_id'  => $entityId > 0 ? $entityId : null,
        ];
    }
}
