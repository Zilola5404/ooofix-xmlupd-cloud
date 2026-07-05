<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Storage;

use Ooofix\XmlupdCloud\Core\Tenant;

/** Журнал операций (аналог Logger модуля коробки) */
final class LogRepository
{
    public const STATUS_STARTED  = 'started';
    public const STATUS_SUCCESS  = 'success';
    public const STATUS_ERROR    = 'error';
    public const STATUS_VALIDATE = 'validate_error';

    public function __construct(
        private readonly int $portalId,
    ) {
    }

    public function write(string $entityType, int $entityId, string $status, string $message = ''): void
    {
        try {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO b_xmldoc_log (PORTAL_ID, ENTITY_TYPE, ENTITY_ID, STATUS, MESSAGE, CREATED_AT)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$this->portalId, $entityType, $entityId, $status, $message]);
        } catch (\Throwable) {
            // Лог не должен прерывать генерацию
        }
    }

    /** @return list<array<string, mixed>> */
    public function fetchList(int $limit = 100, ?string $entityType = null, ?int $entityId = null): array
    {
        Tenant::assertPortalId($this->portalId);
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT * FROM b_xmldoc_log WHERE PORTAL_ID = ?';
        $params = [$this->portalId];

        if ($entityType !== null && $entityType !== '') {
            $sql .= ' AND ENTITY_TYPE = ?';
            $params[] = $entityType;
        }
        if ($entityId !== null && $entityId > 0) {
            $sql .= ' AND ENTITY_ID = ?';
            $params[] = $entityId;
        }

        $sql .= ' ORDER BY ID DESC LIMIT ' . $limit;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $mapped = RowMapper::log($row);
            $mapped['CRM_PATH'] = RowMapper::crmPath(
                $mapped['ENTITY_TYPE'],
                (int)$mapped['ENTITY_ID'],
            );
            $rows[] = $mapped;
        }

        return $rows;
    }
}
