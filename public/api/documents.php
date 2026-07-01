<?php

declare(strict_types=1);

require __DIR__ . '/../init.php';

use Ooofix\XmlupdCloud\App\ApiBootstrap;
use Ooofix\XmlupdCloud\App\Http;
use Ooofix\XmlupdCloud\Core\Tenant;
use Ooofix\XmlupdCloud\Storage\DocumentRepository;

ApiBootstrap::run(static function (array $request, $client): void {
    $repo = new DocumentRepository(Tenant::getPortalId());

    Http::jsonResponse([
        'success'    => true,
        'documents'  => $repo->fetchList((int)($request['limit'] ?? 100)),
        'request_id' => \Ooofix\XmlupdCloud\Core\Tenant::getRequestId(),
    ]);
});
