<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Storage;

/** Лёгкие миграции схемы для уже существующих БД на хостинге */
final class SchemaMigrator
{
    /** @var array<string, string> */
    private const PORTAL_COLUMNS = [
        'MEMBER_ID'     => 'VARCHAR(64) DEFAULT NULL',
        'ACCESS_TOKEN'  => 'TEXT',
        'REFRESH_TOKEN' => 'TEXT',
        'EXPIRES_AT'    => 'DATETIME DEFAULT NULL',
        'INSTALLED_AT'  => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ];

    /** @var array<string, string> canonical => legacy lowercase */
    private const PORTAL_LEGACY_COLUMNS = [
        'MEMBER_ID'     => 'member_id',
        'ACCESS_TOKEN'  => 'access_token',
        'REFRESH_TOKEN' => 'refresh_token',
        'EXPIRES_AT'    => 'expires_at',
        'INSTALLED_AT'  => 'installed_at',
    ];

    /** @var array<string, string> */
    private const QUEUE_JOB_COLUMNS = [
        'REQUEST_ID'  => 'VARCHAR(36) DEFAULT NULL',
        'ENTITY_TYPE' => "VARCHAR(32) NOT NULL DEFAULT 'deal'",
        'ENTITY_ID'   => 'INT NOT NULL DEFAULT 0',
        'USER_ID'     => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'EVENT_TOKEN' => 'TEXT',
        'SOURCE'      => "VARCHAR(32) NOT NULL DEFAULT 'robot'",
        'STATUS'      => "VARCHAR(16) NOT NULL DEFAULT 'pending'",
        'PAYLOAD'     => 'JSON DEFAULT NULL',
        'RESULT'      => 'JSON DEFAULT NULL',
        'ERROR_TEXT'  => 'TEXT',
        'ATTEMPTS'    => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
        'CREATED_AT'  => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'STARTED_AT'  => 'DATETIME DEFAULT NULL',
        'FINISHED_AT' => 'DATETIME DEFAULT NULL',
    ];

    /** @var array<string, string> */
    private const LOG_COLUMNS = [
        'PORTAL_ID'   => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'REQUEST_ID'  => 'VARCHAR(36) DEFAULT NULL',
        'ENTITY_TYPE' => "VARCHAR(32) NOT NULL DEFAULT 'system'",
        'ENTITY_ID'   => 'INT NOT NULL DEFAULT 0',
        'STATUS'      => "VARCHAR(32) NOT NULL DEFAULT 'started'",
        'MESSAGE'     => 'TEXT',
        'CREATED_AT'  => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ];

    /** @var array<string, string> */
    private const DOCUMENT_COLUMNS = [
        'PORTAL_ID'          => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'ENTITY_TYPE'        => "VARCHAR(32) NOT NULL DEFAULT 'deal'",
        'ENTITY_ID'          => 'INT NOT NULL DEFAULT 0',
        'DOC_NUMBER'         => 'VARCHAR(64) DEFAULT NULL',
        'FILE_NAME'          => "VARCHAR(255) NOT NULL DEFAULT ''",
        'FILE_ID'            => 'INT DEFAULT NULL',
        'VERSION'            => 'INT NOT NULL DEFAULT 1',
        'ENCODING'           => "VARCHAR(16) DEFAULT 'windows-1251'",
        'FILE_HASH'          => 'VARCHAR(64) DEFAULT NULL',
        'DOC_STATUS'         => "VARCHAR(32) NOT NULL DEFAULT 'generated'",
        'XML_FORMAT_VERSION' => "VARCHAR(8) DEFAULT '5.03'",
        'CREATED_AT'         => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ];

    /** @var array<string, array<string, string>> */
    private const TABLE_LEGACY_COLUMNS = [
        'queue_jobs'        => [
            'ENTITY_TYPE' => 'entity_type',
            'ENTITY_ID'   => 'entity_id',
            'REQUEST_ID'  => 'request_id',
        ],
        'b_xmldoc_log'      => [
            'PORTAL_ID'   => 'portal_id',
            'REQUEST_ID'  => 'request_id',
            'ENTITY_TYPE' => 'entity_type',
            'ENTITY_ID'   => 'entity_id',
        ],
        'b_xmldoc_document' => [
            'PORTAL_ID'   => 'portal_id',
            'ENTITY_TYPE' => 'entity_type',
            'ENTITY_ID'   => 'entity_id',
        ],
    ];

    public static function ensure(): void
    {
        $pdo = Database::pdo();

        self::ensureQueueJobsTable($pdo);

        if (self::tableExists($pdo, 'portals')) {
            self::ensureTableColumns($pdo, 'portals', self::PORTAL_COLUMNS, self::PORTAL_LEGACY_COLUMNS);
            self::syncLegacyPortalData($pdo);
        }

        if (self::tableExists($pdo, 'queue_jobs')) {
            self::ensureTableColumns(
                $pdo,
                'queue_jobs',
                self::QUEUE_JOB_COLUMNS,
                self::TABLE_LEGACY_COLUMNS['queue_jobs']
            );
        }

        if (self::tableExists($pdo, 'b_xmldoc_log')) {
            self::ensureTableColumns(
                $pdo,
                'b_xmldoc_log',
                self::LOG_COLUMNS,
                self::TABLE_LEGACY_COLUMNS['b_xmldoc_log']
            );
        }

        if (self::tableExists($pdo, 'b_xmldoc_document')) {
            self::ensureTableColumns(
                $pdo,
                'b_xmldoc_document',
                self::DOCUMENT_COLUMNS,
                self::TABLE_LEGACY_COLUMNS['b_xmldoc_document']
            );
        }
    }

    /** @return array<string, list<string>> */
    public static function schemaReport(): array
    {
        $pdo = Database::pdo();
        $report = [];

        foreach (self::requiredColumnsByTable() as $table => $columns) {
            if (!self::tableExists($pdo, $table)) {
                $report[$table] = ['__table__'];
                continue;
            }
            $report[$table] = self::missingColumns($table, $columns);
        }

        return $report;
    }

    /** @return array<string, list<string>> */
    public static function requiredColumnsByTable(): array
    {
        return [
            'portals'           => array_merge(['ID', 'DOMAIN'], array_keys(self::PORTAL_COLUMNS)),
            'queue_jobs'        => array_merge(['ID', 'PORTAL_ID'], array_keys(self::QUEUE_JOB_COLUMNS)),
            'b_xmldoc_log'      => array_merge(['ID'], array_keys(self::LOG_COLUMNS)),
            'b_xmldoc_document' => array_merge(['ID'], array_keys(self::DOCUMENT_COLUMNS)),
        ];
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    public static function missingColumns(string $table, array $columns): array
    {
        $pdo = Database::pdo();
        if (!self::tableExists($pdo, $table)) {
            return $columns;
        }

        $legacy = self::TABLE_LEGACY_COLUMNS[$table] ?? [];
        $missing = [];
        foreach ($columns as $column) {
            if (self::hasColumn($pdo, $table, $column, $legacy)) {
                continue;
            }
            $missing[] = $column;
        }

        return $missing;
    }

    private static function ensureQueueJobsTable(\PDO $pdo): void
    {
        if (self::tableExists($pdo, 'queue_jobs')) {
            return;
        }

        if (!self::tableExists($pdo, 'portals')) {
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS queue_jobs (
                ID           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                PORTAL_ID    INT UNSIGNED NOT NULL,
                REQUEST_ID   VARCHAR(36) DEFAULT NULL,
                ENTITY_TYPE  VARCHAR(32) NOT NULL DEFAULT 'deal',
                ENTITY_ID    INT NOT NULL DEFAULT 0,
                USER_ID      INT UNSIGNED NOT NULL DEFAULT 0,
                EVENT_TOKEN  TEXT,
                SOURCE       VARCHAR(32) NOT NULL DEFAULT 'robot',
                STATUS       VARCHAR(16) NOT NULL DEFAULT 'pending',
                PAYLOAD      JSON DEFAULT NULL,
                RESULT       JSON DEFAULT NULL,
                ERROR_TEXT   TEXT,
                ATTEMPTS     TINYINT UNSIGNED NOT NULL DEFAULT 0,
                CREATED_AT   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                STARTED_AT   DATETIME DEFAULT NULL,
                FINISHED_AT  DATETIME DEFAULT NULL,
                PRIMARY KEY (ID),
                KEY IX_QUEUE_PORTAL_STATUS (PORTAL_ID, STATUS),
                KEY IX_QUEUE_CREATED (CREATED_AT),
                CONSTRAINT FK_QUEUE_PORTAL FOREIGN KEY (PORTAL_ID) REFERENCES portals (ID) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    /**
     * @param array<string, string> $columns
     * @param array<string, string> $legacyColumns
     */
    private static function ensureTableColumns(
        \PDO $pdo,
        string $table,
        array $columns,
        array $legacyColumns,
    ): void {
        foreach ($columns as $name => $definition) {
            if (self::hasColumn($pdo, $table, $name, $legacyColumns)) {
                continue;
            }

            $legacy = $legacyColumns[$name] ?? null;
            if ($legacy !== null && self::columnExists($pdo, $table, $legacy)) {
                $pdo->exec(sprintf(
                    'ALTER TABLE `%s` CHANGE `%s` `%s` %s',
                    $table,
                    $legacy,
                    $name,
                    $definition
                ));
                continue;
            }

            $pdo->exec(sprintf(
                'ALTER TABLE `%s` ADD COLUMN `%s` %s',
                $table,
                $name,
                $definition
            ));
        }
    }

    /** Если раньше добавили пустые UPPER-колонки рядом со старыми lower — копируем данные. */
    private static function syncLegacyPortalData(\PDO $pdo): void
    {
        foreach (self::PORTAL_LEGACY_COLUMNS as $canonical => $legacy) {
            if (!self::columnExists($pdo, 'portals', $canonical)
                || !self::columnExists($pdo, 'portals', $legacy)
            ) {
                continue;
            }

            $pdo->exec(
                "UPDATE `portals`
                 SET `{$canonical}` = `{$legacy}`
                 WHERE (`{$canonical}` IS NULL OR `{$canonical}` = '')
                   AND `{$legacy}` IS NOT NULL
                   AND `{$legacy}` <> ''"
            );
        }
    }

    /**
     * @param array<string, string> $legacyColumns
     */
    private static function hasColumn(\PDO $pdo, string $table, string $column, array $legacyColumns = []): bool
    {
        if (self::columnExists($pdo, $table, $column)) {
            return true;
        }

        $legacy = $legacyColumns[$column]
            ?? self::PORTAL_LEGACY_COLUMNS[$column]
            ?? null;

        return $legacy !== null && self::columnExists($pdo, $table, $legacy);
    }

    private static function tableExists(\PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);

        return (int)$stmt->fetchColumn() > 0;
    }

    private static function columnExists(\PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$table, $column]);

        return (int)$stmt->fetchColumn() > 0;
    }
}
