<?php

declare(strict_types=1);

require __DIR__ . '/../init.php';

use Ooofix\XmlupdCloud\App\ApiBootstrap;
use Ooofix\XmlupdCloud\App\AppBranding;
use Ooofix\XmlupdCloud\App.Http;
use Ooofix\XmlupdCloud\App\InstallService;
use Ooofix\XmlupdCloud\Core\Tenant;
use Ooofix\XmlupdCloud\Rest\AppDiskService;
use Ooofix\XmlupdCloud\Rest\BitrixClient;
use Ooofix\XmlupdCloud\Rest\RestPermissionsDiagnostic;
use Ooofix\XmlupdCloud\Rest\UserFieldInstaller;
use Ooofix\XmlupdCloud\Storage\SettingsRepository;

ApiBootstrap::run(static function (array $request, BitrixClient $client, string $requestId): void {
    $action = (string)($request['action'] ?? $_GET['action'] ?? 'health');
    $portalId = Tenant::getPortalId();

    match ($action) {
        'userfields' => handleUserfields($client, $portalId),
        'menu'       => handleMenu($client),
        'branding'   => handleBranding($client),
        'placements' => handlePlacements($client),
        'disk'         => handleDisk($client, $portalId),
        'permissions'  => handlePermissions($client, $portalId),
        'oauth_refresh'=> handleOAuthRefresh($client, $portalId),
        'health'     => Http::jsonResponse([
            'success'    => true,
            'action'     => 'health',
            'portal_id'  => $portalId,
            'domain'     => $client->domain(),
            'request_id' => Tenant::getRequestId(),
        ]),
        default => throw new RuntimeException('Неизвестное действие sync: ' . $action),
    };
});

function handleMenu(BitrixClient $client): void
{
    $warnings = (new AppBranding())->apply($client);

    Http::jsonResponse([
        'success'  => true,
        'action'   => 'menu',
        'message'  => $warnings === [] ? 'Пункт меню и брендинг обновлены' : implode('; ', $warnings),
        'warnings' => $warnings,
        'branding' => AppBranding::manifest(),
    ]);
}

function handleBranding(BitrixClient $client): void
{
    $warnings = (new AppBranding())->apply($client);

    Http::jsonResponse([
        'success'  => true,
        'action'   => 'branding',
        'message'  => 'Название и иконка приложения обновлены в меню портала',
        'warnings' => $warnings,
        'branding' => AppBranding::manifest(),
    ]);
}

function handlePlacements(BitrixClient $client): void
{
    $warnings = (new InstallService())->refreshPlacements($client);

    Http::jsonResponse([
        'success'  => true,
        'action'   => 'placements',
        'message'  => $warnings === [] ? 'Кнопка в карточке CRM обновлена' : implode('; ', $warnings),
        'warnings' => $warnings,
    ]);
}

function handleDisk(BitrixClient $client, int $portalId): void
{
    $folderId = (new AppDiskService($client, new SettingsRepository(), $portalId))->ensureXmlFolder();

    Http::jsonResponse([
        'success'   => true,
        'action'    => 'disk',
        'message'   => 'Папка /XML/ на общем Диске B24 готова',
        'folder_id' => $folderId,
    ]);
}

function handlePermissions(BitrixClient $client, int $portalId): void
{
    try {
        $report = (new RestPermissionsDiagnostic($client, $portalId))->run();
        Http::jsonResponse($report);
    } catch (\Throwable $e) {
        Http::jsonResponse([
            'success'              => false,
            'ready_for_generation' => false,
            'summary'              => 'Ошибка проверки REST: ' . $e->getMessage(),
            'portal_id'            => $portalId,
            'domain'               => $client->domain(),
            'probes'               => [],
            'fix_steps'            => [
                'Откройте приложение из меню портала (не прямой URL в браузере).',
                'Убедитесь, что приложение установлено администратором.',
                'Переустановите приложение и подтвердите запрошенные права.',
            ],
        ]);
    }
}

function handleUserfields(BitrixClient $client, int $portalId): void
{
    $smartTypeId = (int)(new SettingsRepository())->get($portalId, 'smart_invoice_type_id', '0');
    if ($smartTypeId <= 0) {
        $smartTypeId = (int)(new SettingsRepository())->get($portalId, 'smart_invoice_spa_id', '0');
    }

    $installer = new UserFieldInstaller();
    $result = $installer->installAllResult($client, $smartTypeId);

    Http::jsonResponse([
        'success' => $result['success'],
        'action'  => 'userfields',
        'message' => $result['message'],
        'log'     => $result['log'],
        'errors'  => $result['errors'],
    ], $result['success'] ? 200 : 422);
}

function handleOAuthRefresh(BitrixClient $client, int $portalId): void
{
    $result = $client->forceRefreshToken();

    Http::jsonResponse([
        'success'    => true,
        'action'     => 'oauth_refresh',
        'portal_id'  => $portalId,
        'scope'      => $result['scope'] ?? '',
        'token_hint' => $result['token_hint'] ?? '',
        'message'    => 'Токен обновлён через oauth.bitrix.info',
    ]);
}
