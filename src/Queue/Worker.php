<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Queue;

use Ooofix\XmlupdCloud\Core\Config;
use Ooofix\XmlupdCloud\Core\Logger;
use Ooofix\XmlupdCloud\Core\Tenant;
use Ooofix\XmlupdCloud\Generate\GenerateService;
use Ooofix\XmlupdCloud\Rest\BitrixClient;
use Ooofix\XmlupdCloud\Storage\PortalRepository;

/** Выполнение задач из очереди */
final class Worker
{
    public function __construct(
        private readonly JobQueue $queue = new JobQueue(),
        private readonly PortalRepository $portals = new PortalRepository(),
    ) {
    }

    /** Обработать одну задачу по ID (для robot/button — синхронно после enqueue) */
    /** @return array<string, mixed> */
    public function processJob(int $jobId, ?BitrixClient $client = null): array
    {
        $job = $this->queue->find($jobId);
        if ($job === null) {
            throw new \RuntimeException('Задача не найдена: ' . $jobId);
        }

        if (($job['STATUS'] ?? '') === JobQueue::STATUS_PENDING) {
            $this->markProcessing((int)$job['ID']);
            $job['STATUS'] = JobQueue::STATUS_PROCESSING;
        }

        return $this->executeJob($job, $client);
    }

    /** Cron: обработать пакет pending-задач всех порталов */
    public function processBatch(int $limit = 20): int
    {
        $processed = 0;

        for ($i = 0; $i < $limit; $i++) {
            $job = $this->queue->claimNext();
            if ($job === null) {
                break;
            }

            $portalId = (int)$job['PORTAL_ID'];
            $portal = $this->portals->findById($portalId);
            if ($portal === null) {
                continue;
            }

            Tenant::bind(
                $portalId,
                (string)$portal['DOMAIN'],
                (string)($job['REQUEST_ID'] ?? Logger::generateRequestId())
            );
            Config::bindPortal($portalId);

            try {
                $this->executeJob($job);
                $processed++;
            } catch (\Throwable $e) {
                $this->queue->markFailed((int)$job['ID'], $e->getMessage());
            } finally {
                Tenant::clear();
            }
        }

        return $processed;
    }

    /** @param array<string, mixed> $job */
    private function executeJob(array $job, ?BitrixClient $client = null): array
    {
        $jobId = (int)$job['ID'];
        $entityType = (string)$job['ENTITY_TYPE'];
        $entityId = (int)$job['ENTITY_ID'];
        $userId = (int)($job['USER_ID'] ?? 0);
        $eventToken = (string)($job['EVENT_TOKEN'] ?? '');
        $source = (string)($job['SOURCE'] ?? JobQueue::SOURCE_ROBOT);

        Logger::info('Worker: старт задачи #' . $jobId, $entityType, $entityId);

        $client ??= new BitrixClient(Tenant::getDomain());
        $deferDisk = $source === JobQueue::SOURCE_BUTTON;
        $service = new GenerateService($client, $userId, $deferDisk);
        $dto = GenerateService::request($entityType, $entityId, false);
        $result = $service->runFromDto($dto);
        $data = $result->toArray();

        if ($eventToken !== '') {
            $this->sendBizprocEvent($client, $eventToken, $data);
        }

        if ($result->isSuccess()) {
            $this->queue->markDone($jobId, $data);
            Logger::success('Worker: задача #' . $jobId . ' выполнена', $entityType, $entityId);
        } else {
            $error = (string)($data['Message'] ?? 'Ошибка генерации');
            $this->queue->markFailed($jobId, $error);
            Logger::error('Worker: задача #' . $jobId . ' — ' . $error, $entityType, $entityId);
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function sendBizprocEvent(BitrixClient $client, string $eventToken, array $data): void
    {
        try {
            $client->call('bizproc.event.send', [
                'EVENT_TOKEN'   => $eventToken,
                'RETURN_VALUES' => [
                    'Success'  => (bool)($data['Success'] ?? false),
                    'FileId'   => (int)($data['FileId'] ?? 0),
                    'FileName' => (string)($data['FileName'] ?? ''),
                    'Version'  => (int)($data['Version'] ?? 0),
                    'Message'  => (string)($data['Message'] ?? ''),
                    'Errors'   => is_array($data['Errors'] ?? null)
                        ? implode('; ', $data['Errors'])
                        : (string)($data['Errors'] ?? ''),
                ],
            ]);
        } catch (\Throwable $e) {
            Logger::error('bizproc.event.send: ' . $e->getMessage());
        }
    }

    private function markProcessing(int $jobId): void
    {
        $stmt = \Ooofix\XmlupdCloud\Storage\Database::pdo()->prepare(
            'UPDATE queue_jobs SET STATUS = ?, STARTED_AT = NOW(), ATTEMPTS = ATTEMPTS + 1
             WHERE ID = ? AND PORTAL_ID = ?'
        );
        $stmt->execute([JobQueue::STATUS_PROCESSING, $jobId, Tenant::getPortalId()]);
    }
}
