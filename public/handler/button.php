<?php

declare(strict_types=1);

/**
 * Генерация УПД по кнопке в карточке CRM (placement / API).
 */
require __DIR__ . '/_bootstrap.php';

use Ooofix\XmlupdCloud\App\Http;
use Ooofix\XmlupdCloud\Core\Logger;
use Ooofix\XmlupdCloud\Core\Tenant;
use Ooofix\XmlupdCloud\Queue\JobQueue;
use Ooofix\XmlupdCloud\Queue\Worker;
use Ooofix\XmlupdCloud\Rest\RestDataCollector;

try {
    [$request, $client, $requestId] = handlerBootstrap();

    $entityType = (string)($request['entityType'] ?? $request['entity'] ?? RestDataCollector::TYPE_DEAL);
    $entityId = (int)($request['entityId'] ?? $request['id'] ?? 0);
    $userId = (int)($request['USER_ID'] ?? $request['user_id'] ?? 0);

    if ($entityId <= 0) {
        throw new RuntimeException('Некорректный entityId');
    }

    Logger::info('Button: постановка в очередь', $entityType, $entityId);

    $queue = new JobQueue();
    $jobId = $queue->enqueue(
        Tenant::getPortalId(),
        $entityType,
        $entityId,
        $userId,
        '',
        JobQueue::SOURCE_BUTTON,
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
