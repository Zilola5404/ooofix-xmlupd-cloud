<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Dto;

/** Запись реестра документов. XMLDOC-27 */
final class DocumentRecordDto
{
    public function __construct(
        public readonly string $entityType,
        public readonly int $entityId,
        public readonly string $fileName,
        public readonly ?int $fileId,
        public readonly ?string $docNumber,
        public readonly int $version,
        public readonly string $encoding,
        public readonly string $docStatus,
        public readonly ?string $fileHash = null,
    ) {
    }
}
