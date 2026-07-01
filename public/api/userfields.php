<?php

declare(strict_types=1);

/** @deprecated Используйте api/sync.php?action=userfields */
$_GET['action'] = 'userfields';
require __DIR__ . '/sync.php';
