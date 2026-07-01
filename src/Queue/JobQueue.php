<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Queue;

use Ooofix\XmlupdCloud\Core\Tenant;
use Ooofix\XmlupdCloud\Storage\Database;

/** Очередь задач генерации УПД (MySQL) */
final class JobQueue
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE       = 'done';
    public const STATUS_FAILED     = 'failed';

    public const SOURCE_ROBOT  = 'robot';
    public const SOURCE_BUTTON = 'button';
    public const SOURCE_WEBHOOK = 'webhook';

    public function enqueue(
        int $portalId,
        string $entityType,
        int $entityId,
        int $userId = 0,
        string $eventToken = '',
        string $source = self::SOURCE_ROBOT,
        string $requestId = '',
    ): int {
        Tenant::assertPortalId($portalId);

        $stmt = Database::pdo()->prepare(
            'INSERT INTO queue_jobs
             (PORTAL_ID, REQUEST_ID, ENTITY_TYPE, ENTITY_ID, USER_ID, EVENT_TOKEN, SOURCE, STATUS, CREATED_AT)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $portalId,
            $requestId !== '' ? $requestId : Tenant::getRequestId(),
            $entityType,
            $entityId,
            $userId,
            $eventToken !== '' ? $eventToken : null,
            $source,
            self::STATUS_PENDING,
        ]);

        return (int)Database::pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function find(int $jobId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM queue_jobs WHERE ID = ? AND PORTAL_ID = ? LIMIT 1'
        );
        $stmt->execute([$jobId, Tenant::getPortalId()]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function claimNext(?int $portalId = null): ?array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $sql = 'SELECT * FROM queue_jobs WHERE STATUS = ?';
            $params = [self::STATUS_PENDING];

            if ($portalId !== null && $portalId > 0) {
                $sql .= ' AND PORTAL_ID = ?';
                $params[] = $portalId;
            }

            $sql .= ' ORDER BY ID ASC LIMIT 1 FOR UPDATE';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch();

            if (!$row) {
                $pdo->commit();

                return null;
            }

            $update = $pdo->prepare(
                'UPDATE queue_jobs SET STATUS = ?, STARTED_AT = NOW(), ATTEMPTS = ATTEMPTS + 1 WHERE ID = ?'
            );
            $update->execute([self::STATUS_PROCESSING, $row['ID']]);
            $pdo->commit();

            $row['STATUS'] = self::STATUS_PROCESSING;

            return $row;
        } catch (\Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }

    /** @param array<string, mixed> $result */
    public function markDone(int $jobId, array $result): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE queue_jobs SET STATUS = ?, RESULT = ?, FINISHED_AT = NOW(), ERROR_TEXT = NULL
             WHERE ID = ? AND PORTAL_ID = ?'
        );
        $stmt->execute([
            self::STATUS_DONE,
            json_encode($result, JSON_UNESCAPED_UNICODE),
            $jobId,
            Tenant::getPortalId(),
        ]);
    }

    public function markFailed(int $jobId, string $error): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE queue_jobs SET STATUS = ?, ERROR_TEXT = ?, FINISHED_AT = NOW()
             WHERE ID = ? AND PORTAL_ID = ?'
        );
        $stmt->execute([self::STATUS_FAILED, $error, $jobId, Tenant::getPortalId()]);
    }
}
