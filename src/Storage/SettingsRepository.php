<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Storage;

use Ooofix\XmlupdCloud\App\DefaultOptions;
use Ooofix\XmlupdCloud\Core\Tenant;

/** Настройки портала (аналог b_option модуля коробки) */
final class SettingsRepository
{
    /** @return array<string, string> */
    public function getAll(int $portalId): array
    {
        if (Tenant::tryCurrent() !== null) {
            Tenant::assertPortalId($portalId);
        }
        $stmt = Database::pdo()->prepare(
            'SELECT OPTION_KEY, OPTION_VALUE FROM portal_settings WHERE PORTAL_ID = ?'
        );
        $stmt->execute([$portalId]);

        $options = DefaultOptions::all();
        foreach ($stmt->fetchAll() as $row) {
            $options[(string)$row['OPTION_KEY']] = (string)($row['OPTION_VALUE'] ?? '');
        }

        return $options;
    }

    public function get(int $portalId, string $key, string $default = ''): string
    {
        $stmt = Database::pdo()->prepare(
            'SELECT OPTION_VALUE FROM portal_settings WHERE PORTAL_ID = ? AND OPTION_KEY = ? LIMIT 1'
        );
        $stmt->execute([$portalId, $key]);
        $row = $stmt->fetch();

        if ($row) {
            return (string)($row['OPTION_VALUE'] ?? '');
        }

        return DefaultOptions::all()[$key] ?? $default;
    }

    /** @param array<string, string> $options */
    public function saveAll(int $portalId, array $options): void
    {
        if (Tenant::tryCurrent() !== null) {
            Tenant::assertPortalId($portalId);
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO portal_settings (PORTAL_ID, OPTION_KEY, OPTION_VALUE)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE OPTION_VALUE = VALUES(OPTION_VALUE)'
        );

        foreach ($options as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $stmt->execute([$portalId, $key, (string)$value]);
        }
    }
}
