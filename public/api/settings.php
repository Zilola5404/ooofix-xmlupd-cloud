<?php

declare(strict_types=1);

require __DIR__ . '/../init.php';

use Ooofix\XmlupdCloud\App\ApiBootstrap;
use Ooofix\XmlupdCloud\App\DefaultOptions;
use Ooofix\XmlupdCloud\App\Http;
use Ooofix\XmlupdCloud\App\SettingsFieldRegistry;
use Ooofix\XmlupdCloud\Core\Tenant;
use Ooofix\XmlupdCloud\Storage\SettingsRepository;

ApiBootstrap::run(static function (array $request, $client): void {
    $portalId = Tenant::getPortalId();
    $repo = new SettingsRepository();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $options = $request['options'] ?? $request;
        if (!is_array($options)) {
            throw new RuntimeException('Некорректные данные настроек');
        }

        $allowed = SettingsFieldRegistry::codes();
        $filtered = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $options)) {
                continue;
            }
            if (in_array($key, ['crm_adapter', 'cloud_rest_webhook'], true)) {
                continue;
            }
            $filtered[$key] = (string)$options[$key];
        }
        if ($filtered !== []) {
            $repo->saveAll($portalId, $filtered);
        }
    }

    $stored = array_merge(DefaultOptions::all(), $repo->getAll($portalId));

    Http::jsonResponse([
        'success'    => true,
        'options'    => $stored,
        'sections'   => SettingsFieldRegistry::sections(),
        'fields'     => SettingsFieldRegistry::fields(),
        'request_id' => \Ooofix\XmlupdCloud\Core\Tenant::getRequestId(),
    ]);
});
