<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Storage;

/** OAuth-токены порталов */
final class PortalRepository
{
    /** @return array<string, mixed>|null */
    public function findByDomain(string $domain): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM portals WHERE DOMAIN = ? LIMIT 1');
        $stmt->execute([$this->normalizeDomain($domain)]);
        $row = $stmt->fetch();

        return is_array($row) ? self::normalizeRow($row) : null;
    }

    public function findIdByDomain(string $domain): int
    {
        $row = $this->findByDomain($domain);

        return $row ? self::rowId($row) : 0;
    }

    /** @return array<string, mixed>|null */
    public function findByMemberId(string $memberId): ?array
    {
        $memberId = trim($memberId);
        if ($memberId === '') {
            return null;
        }

        $stmt = Database::pdo()->prepare('SELECT * FROM portals WHERE MEMBER_ID = ? LIMIT 1');
        $stmt->execute([$memberId]);
        $row = $stmt->fetch();

        return is_array($row) ? self::normalizeRow($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM portals WHERE ID = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return is_array($row) ? self::normalizeRow($row) : null;
    }

    public function isInstalled(string $domain): bool
    {
        return $this->findByDomain($domain) !== null;
    }

    /** Удалить портал и все связанные данные (CASCADE) */
    public function purgeByDomain(string $domain): void
    {
        $this->deleteByDomain($domain);
    }

    /** @param array<string, mixed> $data */
    public function upsert(string $domain, array $data): int
    {
        $domain = $this->normalizeDomain($domain);
        $existing = $this->findByDomain($domain);

        if ($existing) {
            $accessToken = (string)($data['access_token'] ?? '');
            if ($accessToken === '') {
                $accessToken = (string)($existing['ACCESS_TOKEN'] ?? '');
            }

            $refreshToken = (string)($data['refresh_token'] ?? '');
            if ($refreshToken === '') {
                $refreshToken = (string)($existing['REFRESH_TOKEN'] ?? '');
            }

            $memberId = $data['member_id'] ?? $existing['MEMBER_ID'];
            $expiresAt = $data['expires_at'] ?? $existing['EXPIRES_AT'];

            $stmt = Database::pdo()->prepare(
                'UPDATE portals SET MEMBER_ID = ?, ACCESS_TOKEN = ?, REFRESH_TOKEN = ?, EXPIRES_AT = ? WHERE ID = ?'
            );
            $stmt->execute([
                $memberId,
                $accessToken,
                $refreshToken,
                $expiresAt,
                self::rowId($existing),
            ]);

            return $this->assertPositivePortalId(self::rowId($existing), $domain);
        }

        return $this->insertPortal($domain, $data);
    }

    /**
     * Полная замена OAuth-токенов (переустановка / ONAPPUPDATE).
     * Не подставляет старый refresh_token, если в запросе он пустой.
     *
     * @param array<string, mixed> $data
     */
    public function replaceTokens(string $domain, array $data): int
    {
        $domain = $this->normalizeDomain($domain);
        $existing = $this->findByDomain($domain);

        if ($existing) {
            $stmt = Database::pdo()->prepare(
                'UPDATE portals SET MEMBER_ID = ?, ACCESS_TOKEN = ?, REFRESH_TOKEN = ?, EXPIRES_AT = ? WHERE ID = ?'
            );
            $stmt->execute([
                $data['member_id'] ?? $existing['MEMBER_ID'],
                (string)($data['access_token'] ?? ''),
                (string)($data['refresh_token'] ?? ''),
                $data['expires_at'] ?? null,
                self::rowId($existing),
            ]);

            return $this->assertPositivePortalId(self::rowId($existing), $domain);
        }

        return $this->insertPortal($domain, $data);
    }

    /** @param array<string, mixed> $data */
    private function insertPortal(string $domain, array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO portals (DOMAIN, MEMBER_ID, ACCESS_TOKEN, REFRESH_TOKEN, EXPIRES_AT, INSTALLED_AT)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $domain,
            $data['member_id'] ?? null,
            $data['access_token'] ?? '',
            $data['refresh_token'] ?? '',
            $data['expires_at'] ?? null,
        ]);

        return $this->assertPositivePortalId($this->resolvePortalId($domain), $domain);
    }

    private function resolvePortalId(string $domain): int
    {
        $id = (int)Database::pdo()->lastInsertId();
        if ($id > 0) {
            return $id;
        }

        $row = $this->findByDomain($domain);

        return $row !== null ? self::rowId($row) : 0;
    }

    /** @param array<string, mixed> $row */
    public static function rowId(array $row): int
    {
        if (isset($row['ID']) && (int)$row['ID'] > 0) {
            return (int)$row['ID'];
        }
        if (isset($row['id']) && (int)$row['id'] > 0) {
            return (int)$row['id'];
        }

        return 0;
    }

    public function assertPositivePortalId(int $portalId, string $domain): int
    {
        if ($portalId <= 0) {
            throw new \RuntimeException(
                'Не удалось сохранить портал в БД (domain: ' . $domain . '). Проверьте таблицу portals.'
            );
        }

        return $portalId;
    }

    public function deleteByDomain(string $domain): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM portals WHERE DOMAIN = ?');
        $stmt->execute([$this->normalizeDomain($domain)]);
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        return Database::pdo()->query('SELECT * FROM portals ORDER BY ID')->fetchAll();
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;

        return rtrim($domain, '/');
    }

    /** @param array<string, mixed> $row */
    private static function normalizeRow(array $row): array
    {
        $aliases = [
            'ACCESS_TOKEN'  => ['access_token', 'AUTH_ID', 'auth_id', 'TOKEN', 'token'],
            'REFRESH_TOKEN' => ['refresh_token', 'REFRESH_ID', 'refresh_id'],
            'EXPIRES_AT'    => ['expires_at', 'TOKEN_EXPIRES', 'token_expires'],
            'MEMBER_ID'     => ['member_id'],
            'INSTALLED_AT'  => ['installed_at'],
        ];

        foreach ($aliases as $canonical => $names) {
            if (($row[$canonical] ?? '') !== '') {
                continue;
            }
            foreach ($names as $name) {
                if (!empty($row[$name])) {
                    $row[$canonical] = $row[$name];
                    break;
                }
            }
        }

        $id = self::rowId($row);
        if ($id > 0) {
            $row['ID'] = $id;
        }

        return $row;
    }
}
