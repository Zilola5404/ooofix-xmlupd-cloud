<?php

declare(strict_types=1);

require __DIR__ . '/../init.php';

use Ooofix\XmlupdCloud\App\PlacementRenderer;

PlacementRenderer::renderDealButton((string)($_GET['entity'] ?? 'deal'));
