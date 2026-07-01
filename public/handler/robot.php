<?php

declare(strict_types=1);

/**
 * Обработчик робота CRM «Сформировать УПД (XML)».
 * Кладёт задачу в очередь и выполняет синхронно (ответ bizproc.event.send).
 */
require __DIR__ . '/_bootstrap.php';

use Ooofix\XmlupdCloud\App\Http;
use Ooofix\XmlupdCloud\Core\Logger;
use Ooofix\XmlupdCloud\Core\Tenant;
use Ooofix\XmlupdCloud\Generate\EntityResolver;
use Ooofix\XmlupdCloud\Queue\JobQueue;
use Ooofix\XmlupdCloud\Queue\Worker;

try {
    [$request, $client, $requestId] = handlerBootstrap();

    $documentId = $request['document_id'] ?? $request['DOCUMENT_ID'] ?? [];
    if (is_string($documentId)) {
        $documentId = json_decode($documentId, true) ?? [];
    }
    if (!is_array($documentId)) {
        throw new RuntimeException('Некорректный document_id');
    }

    [$entityType, $entityId] = EntityResolver::fromDocumentId(array_values($documentId));
    $eventToken = (string)($request['event_token'] ?? $request['EVENT_TOKEN'] ?? '');
    $userId = (int)($request['USER_ID'] ?? $request['user_id'] ?? 0);

    Logger::info('Robot: постановка в очередь', $entityType, $entityId);

    $queue = new JobQueue();
    $jobId = $queue->enqueue(
        Tenant::getPortalId(),
        $entityType,
        $entityId,
        $userId,
        $eventToken,
        JobQueue::SOURCE_ROBOT,
        $requestId
    );

    $data = (new Worker())->processJob($jobId, $client);

    Http::jsonResponse(array_merge($data, ['job_id' => $jobId, 'request_id' => $requestId]));
} catch (Throwable $e) {
    Http::jsonResponse([
        'Success' => false,
        'Message' => $e->getMessage(),
        'Errors'  => [$e->getMessage()],
    ], 500);
} finally {
    Tenant::clear();
}
