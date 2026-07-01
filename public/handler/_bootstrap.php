<?php

declare(strict_types=1);

/**
 * Общая инициализация handler/*.php
 */
require_once dirname(__DIR__) . '/init.php';

use Ooofix\XmlupdCloud\App\PortalBootstrap;

/** @return array{0: array<string, mixed>, 1: \Ooofix\XmlupdCloud\Rest\BitrixClient, 2: string} */
function handlerBootstrap(): array
{
    [$request, $client, , , $requestId] = PortalBootstrap::boot();

    return [$request, $client, $requestId];
}
