<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Storage;

use Ooofix\XmlupdCloud\Core\Tenant;

use Ooofix\XmlupdCloud\Core\Config;
use Ooofix\XmlupdCloud\Core\DocumentStatus;

/** Реестр документов (аналог DocumentRegistry модуля коробки) */
final class DocumentRepository
{
    public function __construct(
        private readonly int $portalId,
    ) {
    }

    public function portalId(): int
    {
        return $this->portalId;
    }

    public function getNextVersion(string $entityType, int $entityId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT MAX(VERSION) AS V FROM b_xmldoc_document
             WHERE PORTAL_ID = ? AND ENTITY_TYPE = ? AND ENTITY_ID = ?'
        );
        $stmt->execute([$this->portalId, $entityType, $entityId]);
        $row = $stmt->fetch();

        return ((int)($row['V'] ?? 0)) + 1;
    }

    public function add(
        string $entityType,
        int $entityId,
        string $fileName,
        ?int $fileId,
        ?string $docNumber,
        int $version,
        string $encoding,
        ?string $fileHash = null,
        string $docStatus = DocumentStatus::GENERATED,
    ): int {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO b_xmldoc_document
             (PORTAL_ID, ENTITY_TYPE, ENTITY_ID, DOC_NUMBER, FILE_NAME, FILE_ID, VERSION, ENCODING, FILE_HASH, DOC_STATUS, XML_FORMAT_VERSION, CREATED_AT)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $this->portalId,
            $entityType,
            $entityId,
            $docNumber,
            $fileName,
            $fileId,
            $version,
            $encoding,
            $fileHash,
            $docStatus,
            Config::xmlFormatVersion(),
        ]);

        return (int)Database::pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $documentId): ?array
    {
        Tenant::assertPortalId($this->portalId);
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM b_xmldoc_document WHERE PORTAL_ID = ? AND ID = ? LIMIT 1'
        );
        $stmt->execute([$this->portalId, $documentId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function fetchList(int $limit = 100, ?string $entityType = null, ?int $entityId = null): array
    {
        Tenant::assertPortalId($this->portalId);
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT * FROM b_xmldoc_document WHERE PORTAL_ID = ?';
        $params = [$this->portalId];

        if ($entityType !== null && $entityType !== '') {
            $sql .= ' AND ENTITY_TYPE = ?';
            $params[] = $entityType;
        }
        if ($entityId !== null && $entityId > 0) {
            $sql .= ' AND ENTITY_ID = ?';
            $params[] = $entityId;
        }

        $sql .= ' ORDER BY ID DESC LIMIT ' . $limit;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return array_map(
            static fn (array $row): array => RowMapper::document($row),
            array_values(array_filter($stmt->fetchAll(), 'is_array')),
        );
    }
}
