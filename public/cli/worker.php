<?php

declare(strict_types=1);

/**
 * Cron worker: php public/cli/worker.php
 * Рекомендуется: * * * * * php /path/to/public/cli/worker.php
 */
require dirname(__DIR__) . '/init.php';

use Ooofix\XmlupdCloud\Queue\Worker;

$limit = (int)($argv[1] ?? 20);
$processed = (new Worker())->processBatch(max(1, min(100, $limit)));

echo date('Y-m-d H:i:s') . " processed: {$processed}\n";
