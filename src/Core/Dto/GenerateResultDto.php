<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Dto;

/** Результат генерации УПД. XMLDOC-27 */
final class GenerateResultDto
{
    /** @param string[] $errors */
    public function __construct(
        public readonly bool $success,
        public readonly array $errors = [],
        public readonly ?int $fileId = null,
        public readonly ?string $fileName = null,
        public readonly ?int $version = null,
        public readonly ?string $encoding = null,
        public readonly ?string $docStatus = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if ($this->success) {
            return [
                'success'   => true,
                'fileId'    => $this->fileId,
                'fileName'  => $this->fileName,
                'fileUrl'   => $this->fileId ? \CFile::GetPath($this->fileId) : null,
                'version'   => $this->version,
                'encoding'  => $this->encoding,
                'docStatus' => $this->docStatus,
            ];
        }

        return [
            'success' => false,
            'errors'  => $this->errors,
            'message' => implode('; ', $this->errors),
        ];
    }
}
