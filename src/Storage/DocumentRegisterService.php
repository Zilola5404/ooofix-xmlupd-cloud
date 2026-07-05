<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Storage;

use Ooofix\XmlupdCloud\Core\Config;
use Ooofix\XmlupdCloud\Core\DocumentStatus;
use Ooofix\XmlupdCloud\Core\Logger;

/** Запись сгенерированного документа в реестр (в т.ч. после загрузки на Диск из браузера). */
final class DocumentRegisterService
{
    private readonly DocumentRepository $documents;

    public function __construct(
        private readonly int $portalId,
    ) {
        $this->documents = new DocumentRepository($portalId);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function registerFromRequest(array $payload): array
    {
        $entityType = trim((string)($payload['entityType'] ?? $payload['entity_type'] ?? ''));
        $entityId = (int)($payload['entityId'] ?? $payload['entity_id'] ?? 0);
        $fileId = (int)($payload['fileId'] ?? $payload['file_id'] ?? 0);
        $fileName = trim((string)($payload['fileName'] ?? $payload['file_name'] ?? ''));
        $version = (int)($payload['version'] ?? $payload['Version'] ?? 0);
        $docNumber = trim((string)($payload['docNumber'] ?? $payload['doc_number'] ?? ''));
        $encoding = trim((string)($payload['encoding'] ?? Config::fileEncoding()));
        $fileHash = trim((string)($payload['fileHash'] ?? $payload['file_hash'] ?? ''));

        if ($entityType === '' || $entityId <= 0) {
            throw new \InvalidArgumentException('Укажите entityType и entityId');
        }
        if ($fileId <= 0) {
            throw new \InvalidArgumentException('Укажите fileId файла на Диске');
        }
        if ($fileName === '') {
            $fileName = sprintf('УПД_%d.xml', $entityId);
        }
        if ($version <= 0) {
            $version = $this->documents->getNextVersion($entityType, $entityId);
        }

        if ($this->hasVersion($entityType, $entityId, $version)) {
            $existing = $this->findByVersion($entityType, $entityId, $version);
            if ($existing !== null) {
                return RowMapper::document($existing);
            }
        }

        $documentId = $this->documents->add(
            $entityType,
            $entityId,
            $fileName,
            $fileId,
            $docNumber !== '' ? $docNumber : null,
            $version,
            $encoding !== '' ? $encoding : Config::fileEncoding(),
            $fileHash !== '' ? $fileHash : null,
            DocumentStatus::GENERATED,
        );

        Logger::success(
            sprintf('Документ зарегистрирован: %s, версия %d, fileId %d', $fileName, $version, $fileId),
            $entityType,
            $entityId
        );

        $row = $this->documents->findById($documentId);

        return $row !== null ? RowMapper::document($row) : ['ID' => $documentId];
    }

    private function hasVersion(string $entityType, int $entityId, int $version): bool
    {
        return $this->findByVersion($entityType, $entityId, $version) !== null;
    }

    /** @return array<string, mixed>|null */
    private function findByVersion(string $entityType, int $entityId, int $version): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM b_xmldoc_document
             WHERE PORTAL_ID = ? AND ENTITY_TYPE = ? AND ENTITY_ID = ? AND VERSION = ?
             LIMIT 1'
        );
        $stmt->execute([$this->portalId, $entityType, $entityId, $version]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }
}
