<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Rest;

use Ooofix\XmlupdCloud\App\AppConfig;
use Ooofix\XmlupdCloud\App\AppScopes;
use Ooofix\XmlupdCloud\App\OAuthService;
use Ooofix\XmlupdCloud\Storage\SettingsRepository;

/**
 * Проверка REST scope и ключевых методов Bitrix24 для диагностики OAuth-токена.
 */
final class RestPermissionsDiagnostic
{
    /** @var array<string, array{method: string, params: array<string, mixed>, title: string, optional?: bool}> */
    private const PROBES = [
        'crm' => [
            'title'  => 'CRM',
            'method' => 'crm.type.list',
            'params' => [],
        ],
        'userfieldconfig' => [
            'title'  => 'Пользовательские поля CRM',
            'method' => 'userfieldconfig.list',
            'params' => [
                'moduleId' => 'crm',
                'filter'   => ['entityId' => 'CRM_DEAL'],
            ],
        ],
        'disk' => [
            'title'  => 'Общий Диск',
            'method' => 'disk.storage.getlist',
            'params' => [
                'filter' => ['ENTITY_TYPE' => 'common'],
                'order'  => ['ID' => 'ASC'],
            ],
        ],
        'bizproc' => [
            'title'    => 'Бизнес-процессы (роботы)',
            'method'   => 'bizproc.robot.list',
            'params'   => [],
            'optional' => true,
        ],
        'placement' => [
            'title'  => 'Виджеты (placement)',
            'method' => 'placement.list',
            'params' => [],
        ],
    ];

    public function __construct(
        private readonly BitrixClient $client,
        private readonly int $portalId,
        private readonly SettingsRepository $settings = new SettingsRepository(),
    ) {
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        $required = AppScopes::REST;
        $scopeManifestProbe = $this->client->probe('scope', ['full' => true]);
        $scopeTokenProbe = $this->client->probe('scope', []);
        $appManifestScopes = $this->normalizeScopes($scopeManifestProbe['result'] ?? null);
        $scopeMethodScopes = $this->normalizeScopes($scopeTokenProbe['result'] ?? null);
        $storedScope = $this->settings->get($this->portalId, OAuthService::SETTING_OAUTH_SCOPE, '');
        $storedScopes = OAuthService::parseScopeString($storedScope);
        $tokenScopes = $storedScopes !== [] ? $storedScopes : $scopeMethodScopes;

        $tokenSource = $this->client->hasSessionToken() ? 'session' : 'database';
        $probes = [];
        foreach (self::PROBES as $scope => $probe) {
            $probes[$scope] = $this->probeEntry($scope, $probe);
        }

        $dbClient = new BitrixClient($this->client->domain());
        $dbDiskProbe = $dbClient->probe('disk.storage.getlist', [
            'filter' => ['ENTITY_TYPE' => 'common'],
            'order'  => ['ID' => 'ASC'],
        ]);

        $blocking = [];
        foreach ($probes as $scope => $probe) {
            if (!($probe['ok'] ?? false) && empty($probe['optional'])) {
                $blocking[] = $scope;
            }
        }

        $missingScopes = $this->missingScopes($required, $tokenScopes, $blocking);
        $manifestComplete = $this->manifestHasRequired($required, $appManifestScopes);
        $diskFolder = $this->probeDiskFolder($probes['disk']['ok'] ?? false);

        $ready = $blocking === [] && ($diskFolder['ok'] ?? false);

        return [
            'success'              => true,
            'ready_for_generation' => $ready,
            'summary'              => $this->buildSummary(
                $ready,
                $missingScopes,
                $blocking,
                $probes,
                $manifestComplete,
                $tokenScopes,
            ),
            'domain'               => $this->client->domain(),
            'portal_id'            => $this->portalId,
            'client_id'            => AppConfig::clientId(),
            'token_source'         => $tokenSource,
            'required_scopes'      => $required,
            'token_scopes'         => $tokenScopes,
            'stored_oauth_scope'   => $storedScope,
            'app_manifest_scopes'  => $appManifestScopes,
            'granted_scopes'       => $tokenScopes,
            'missing_scopes'       => array_values($missingScopes),
            'manifest_complete'    => $manifestComplete,
            'scope_method_token'   => $scopeTokenProbe,
            'scope_method_manifest'=> $scopeManifestProbe,
            'scope_method'         => $scopeTokenProbe,
            'probes'               => $probes,
            'probes_database'      => [
                'disk' => $dbDiskProbe,
            ],
            'disk_xml_folder'      => $diskFolder,
            'vendors_checklist'    => $this->vendorsChecklist(),
            'fix_steps'            => $this->fixSteps(
                $missingScopes,
                $blocking,
                $probes,
                $manifestComplete,
                $tokenScopes,
                $dbDiskProbe,
            ),
        ];
    }

    /**
     * @param array{title: string, method: string, params: array<string, mixed>, optional?: bool} $probe
     * @return array<string, mixed>
     */
    private function probeEntry(string $scope, array $probe): array
    {
        $result = $this->client->probe($probe['method'], $probe['params']);

        return [
            'scope'      => $scope,
            'title'      => $probe['title'],
            'method'     => $probe['method'],
            'optional'   => !empty($probe['optional']),
            'ok'         => $result['ok'],
            'error'      => $result['error'] ?? '',
            'error_code' => $result['error_code'] ?? '',
        ];
    }

    /** @return array<string, mixed> */
    private function probeDiskFolder(bool $diskOk): array
    {
        if (!$diskOk) {
            return [
                'ok'        => false,
                'folder_id' => 0,
                'message'   => 'Пропущено: нет доступа к disk.storage.getlist (общий Диск)',
            ];
        }

        try {
            $folderId = (new AppDiskService(
                $this->client,
                $this->settings,
                $this->portalId,
            ))->ensureXmlFolder();

            return [
                'ok'        => $folderId > 0,
                'folder_id' => $folderId,
                'message'   => $folderId > 0 ? 'Папка /XML/ на общем Диске готова' : 'Не удалось создать папку /XML/ на общем Диске',
            ];
        } catch (\Throwable $e) {
            return [
                'ok'        => false,
                'folder_id' => 0,
                'message'   => $e->getMessage(),
            ];
        }
    }

    /** @param list<string> $required */
    /** @param list<string> $tokenScopes */
    /** @param list<string> $blocking */
    /** @return list<string> */
    private function missingScopes(array $required, array $tokenScopes, array $blocking): array
    {
        if ($tokenScopes !== []) {
            $grantedMap = array_fill_keys($tokenScopes, true);
            $missing = [];
            foreach ($required as $scope) {
                if (!isset($grantedMap[strtolower($scope)])) {
                    $missing[] = $scope;
                }
            }

            return $missing;
        }

        if ($blocking !== []) {
            $missing = [];
            foreach ($required as $scope) {
                if (in_array($scope, $blocking, true)) {
                    $missing[] = $scope;
                }
            }

            return $missing;
        }

        return [];
    }

    /** @param list<string> $required */
    /** @param list<string> $manifestScopes */
    private function manifestHasRequired(array $required, array $manifestScopes): bool
    {
        if ($manifestScopes === []) {
            return false;
        }

        $map = array_fill_keys($manifestScopes, true);
        foreach ($required as $scope) {
            if (!isset($map[strtolower($scope)])) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function normalizeScopes(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $scopes = [];
        foreach ($raw as $item) {
            if (is_string($item) && $item !== '') {
                $scopes[] = strtolower($item);
            }
        }

        sort($scopes);

        return array_values(array_unique($scopes));
    }

    /**
     * @param list<string> $missingScopes
     * @param list<string> $blocking
     * @param array<string, array<string, mixed>> $probes
     * @param list<string> $tokenScopes
     */
    private function buildSummary(
        bool $ready,
        array $missingScopes,
        array $blocking,
        array $probes,
        bool $manifestComplete,
        array $tokenScopes,
    ): string {
        if ($ready) {
            return 'REST-права в порядке: генерация УПД и папка /XML/ на общем Диске доступны.';
        }

        if ($missingScopes !== []) {
            return 'В OAuth-токене нет scope: ' . implode(', ', $missingScopes)
                . '. Опубликуйте версию на vendors, переустановите приложение и подтвердите новые права.';
        }

        if ($manifestComplete && $blocking !== [] && $tokenScopes !== []) {
            return 'На vendors все нужные scope указаны, но REST-методы недоступны с текущим токеном. '
                . 'Переустановите приложение от имени администратора портала с правами на общий Диск.';
        }

        if (in_array('disk', $blocking, true)) {
            $diskError = (string)($probes['disk']['error'] ?? '');

            return 'Метод disk.storage.getlist (общий Диск) недоступен'
                . ($diskError !== '' ? ': ' . $diskError : '')
                . '. Установите приложение администратором портала; для кнопки в CRM файл сохраняется через BX24 в iframe.';
        }

        return 'Не все REST-методы доступны: ' . implode(', ', $blocking) . '. См. детали probes.';
    }

    /** @return list<array{label: string, required: string}> */
    private function vendorsChecklist(): array
    {
        return [
            [
                'label'    => 'REST-права',
                'required' => implode(', ', AppScopes::REST),
            ],
            [
                'label'    => 'Умные сценарии / шаблоны',
                'required' => 'Да (bizproc.robot.add)',
            ],
            [
                'label'    => 'Настраивать CRM',
                'required' => 'Да',
            ],
            [
                'label'    => 'Виджеты в интерфейс',
                'required' => 'Да (placement.bind)',
            ],
        ];
    }

    /**
     * @param list<string> $missingScopes
     * @param list<string> $blocking
     * @param array<string, array<string, mixed>> $probes
     * @param list<string> $tokenScopes
     * @param array<string, mixed> $dbDiskProbe
     * @return list<string>
     */
    private function fixSteps(
        array $missingScopes,
        array $blocking,
        array $probes,
        bool $manifestComplete,
        array $tokenScopes,
        array $dbDiskProbe,
    ): array {
        $steps = [];
        $clientId = AppConfig::clientId();

        if ($clientId !== '') {
            $steps[] = 'На vendors.bitrix24.ru откройте приложение с CLIENT_ID=' . $clientId
                . ' (должен совпадать с B24_CLIENT_ID в .env на сервере).';
        }

        if ($missingScopes !== []) {
            $steps[] = 'В версии на vendors включите REST scope: ' . implode(', ', AppScopes::REST) . '.';
            $steps[] = 'Создайте новую версию и нажмите «Опубликовать» (без публикации портал не получит новые права).';
            $steps[] = 'На портале удалите приложение и установите снова; подтвердите запрос новых прав.';
        } elseif ($manifestComplete && $blocking !== []) {
            $steps[] = 'На vendors scope уже указаны — переустановите приложение от имени администратора портала.';
            $steps[] = 'Проверьте, что у вашего пользователя есть доступ к приложению (настройки приложения на портале).';
        } elseif ($blocking !== []) {
            $steps[] = 'В версии на vendors включите REST scope: ' . implode(', ', AppScopes::REST) . '.';
            $steps[] = 'Включите: «Умные сценарии / шаблоны» = Да, «Настраивать CRM» = Да, «Виджеты в интерфейс» = Да.';
            $steps[] = 'Создайте новую версию и нажмите «Опубликовать», затем переустановите приложение на портале.';
        }

        if ($tokenScopes === []) {
            $steps[] = 'Scope OAuth-токена не сохранён — переустановите приложение, чтобы записать auth[scope] в БД.';
        }

        if (in_array('disk', $blocking, true)) {
            $steps[] = 'disk.storage.getlist (общий Диск): нужен scope disk и права администратора на общий Диск портала. '
                . 'Кнопка «Сформировать УПД» сохраняет файл через BX24 в iframe, даже если серверный токен без disk.';
            if (!($dbDiskProbe['ok'] ?? false)) {
                $steps[] = 'Токен в БД (для робота/очереди) тоже без disk — обновите приложение на портале.';
            }
        }

        if ($manifestComplete && $missingScopes !== []) {
            $steps[] = 'На vendors scope указаны, но портал выдал старый OAuth. '
                . 'На портале: Приложения → Установленные → «Обновить» (вызовет ONAPPUPDATE с новым токеном). '
                . 'URL обработчика событий: ' . AppConfig::appUrl() . '/handler/webhook.php';
            $steps[] = 'Если обновления нет — удалите приложение, очистите запись в таблице portals, установите снова администратором.';
        }

        $steps[] = 'В настройках приложения нажмите «Проверить REST-права» — все строки должны быть OK.';

        foreach ($blocking as $scope) {
            $probe = $probes[$scope] ?? [];
            $method = (string)($probe['method'] ?? $scope);
            $error = (string)($probe['error'] ?? '');
            if ($error !== '') {
                $steps[] = 'Сейчас недоступно: ' . $method . ' — ' . $error;
            }
        }

        return array_values(array_unique($steps));
    }

    /** Текст для экрана установки (без дублирования длинных подсказок). */
    public static function formatInstallText(array $report): string
    {
        $lines = ['--- Проверка REST ---'];
        $lines[] = (string)($report['summary'] ?? '');

        $clientId = (string)($report['client_id'] ?? '');
        if ($clientId !== '') {
            $lines[] = 'CLIENT_ID: ' . $clientId;
        }

        $tokenScopes = $report['token_scopes'] ?? $report['granted_scopes'] ?? [];
        if (is_array($tokenScopes) && $tokenScopes !== []) {
            $lines[] = 'Scope в OAuth-токене: ' . implode(', ', $tokenScopes);
        } else {
            $scopeErr = (string)($report['scope_method_token']['error'] ?? $report['scope_method']['error'] ?? '');
            $lines[] = $scopeErr !== ''
                ? 'Метод scope: ' . $scopeErr
                : 'Scope токена неизвестен — переустановите приложение.';
        }

        $manifest = $report['app_manifest_scopes'] ?? [];
        if (is_array($manifest) && $manifest !== []) {
            $lines[] = 'Scope приложения на vendors (scope?full=true): ' . implode(', ', $manifest);
        }

        $missing = $report['missing_scopes'] ?? [];
        if (is_array($missing) && $missing !== []) {
            $lines[] = 'Не хватает scope в токене: ' . implode(', ', $missing);
        }

        foreach ($report['probes'] ?? [] as $probe) {
            if (!is_array($probe) || !empty($probe['ok'])) {
                continue;
            }
            $lines[] = '✗ ' . ($probe['method'] ?? '?') . ' — ' . ($probe['error'] ?? 'ошибка');
        }

        $disk = $report['disk_xml_folder'] ?? null;
        if (is_array($disk)) {
            if (!empty($disk['ok']) && (int)($disk['folder_id'] ?? 0) > 0) {
                $lines[] = 'Папка /XML/ (общий Диск): готова (ID ' . (int)$disk['folder_id'] . ')';
            } else {
                $lines[] = 'Папка /XML/ (общий Диск): ' . (string)($disk['message'] ?? 'не создана');
            }
        }

        $lines[] = '';
        $lines[] = 'Что сделать:';
        foreach ($report['fix_steps'] ?? [] as $i => $step) {
            $lines[] = ($i + 1) . '. ' . $step;
        }

        return implode("\n", $lines);
    }
}
