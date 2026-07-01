<?php

namespace Ooofix\XmlupdCloud\Core;

use Ooofix\XmlupdCloud\Core\Contract\GenerateResultInterface;

class GenerateResult implements GenerateResultInterface
{
    /** @param string[] $errors */
    public function __construct(
        private readonly bool $success,
        private readonly array $errors = [],
        private readonly ?int $fileId = null,
        private readonly ?string $fileName = null,
        private readonly ?int $version = null,
        private readonly ?string $encoding = null,
        private readonly ?string $docStatus = null,
        private readonly ?string $downloadUrl = null,
        private readonly ?string $detailUrl = null,
        private readonly string $storageMode = 'disk',
        private readonly string $storageWarning = '',
        private readonly bool $clientDiskUpload = false,
        private readonly string $xmlBase64 = '',
        private readonly string $storageFileName = '',
        private readonly string $docNumber = '',
        private readonly string $entityType = '',
        private readonly int $entityId = 0,
        private readonly int $entityTypeId = 0,
        private readonly string $ufFileKey = '',
        private readonly string $ufNumberKey = '',
        private readonly bool $useOriginalUfNames = false,
    ) {
    }

    public static function ok(
        int $fileId,
        string $fileName,
        ?int $version = null,
        ?string $encoding = null,
        ?string $docStatus = null,
        ?string $downloadUrl = null,
        ?string $detailUrl = null,
        string $storageMode = 'disk',
        string $storageWarning = '',
    ): self {
        return new self(
            true,
            [],
            $fileId,
            $fileName,
            $version,
            $encoding,
            $docStatus,
            $downloadUrl,
            $detailUrl,
            $storageMode,
            $storageWarning,
        );
    }

    public static function okClientDisk(
        string $xmlBase64,
        string $fileName,
        string $storageFileName,
        int $version,
        string $encoding,
        string $docNumber,
        string $entityType,
        int $entityId,
        int $entityTypeId,
        string $ufFileKey = '',
        string $ufNumberKey = '',
        bool $useOriginalUfNames = false,
    ): self {
        return new self(
            true,
            [],
            0,
            $fileName,
            $version,
            $encoding,
            DocumentStatus::GENERATED,
            '',
            '',
            'client_disk',
            '',
            true,
            $xmlBase64,
            $storageFileName,
            $docNumber,
            $entityType,
            $entityId,
            $entityTypeId,
            $ufFileKey,
            $ufNumberKey,
            $useOriginalUfNames,
        );
    }

    /** @param string[] $errors */
    public static function fail(array $errors): self
    {
        return new self(false, $errors);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFileId(): ?int
    {
        return $this->fileId;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if ($this->success) {
            $fileUrl = $this->downloadUrl ?: $this->detailUrl;

            return [
                'success'     => true,
                'Success'     => true,
                'fileId'      => $this->fileId,
                'FileId'      => $this->fileId,
                'fileName'    => $this->fileName,
                'FileName'    => $this->fileName,
                'fileUrl'     => $fileUrl,
                'FileUrl'     => $fileUrl,
                'downloadUrl' => $this->downloadUrl,
                'DownloadUrl' => $this->downloadUrl,
                'detailUrl'   => $this->detailUrl,
                'DetailUrl'   => $this->detailUrl,
                'storageMode' => $this->storageMode,
                'StorageMode' => $this->storageMode,
                'storageWarning' => $this->storageWarning,
                'StorageWarning' => $this->storageWarning,
                'clientDiskUpload' => $this->clientDiskUpload,
                'ClientDiskUpload' => $this->clientDiskUpload,
                'xmlBase64'      => $this->xmlBase64,
                'XmlBase64'      => $this->xmlBase64,
                'storageFileName'=> $this->storageFileName,
                'StorageFileName'=> $this->storageFileName,
                'docNumber'      => $this->docNumber,
                'DocNumber'      => $this->docNumber,
                'entityType'     => $this->entityType,
                'EntityType'     => $this->entityType,
                'entityId'       => $this->entityId,
                'EntityId'       => $this->entityId,
                'entityTypeId'   => $this->entityTypeId,
                'EntityTypeId'   => $this->entityTypeId,
                'ufFileKey'      => $this->ufFileKey,
                'UfFileKey'      => $this->ufFileKey,
                'ufNumberKey'    => $this->ufNumberKey,
                'UfNumberKey'    => $this->ufNumberKey,
                'useOriginalUfNames' => $this->useOriginalUfNames,
                'UseOriginalUfNames' => $this->useOriginalUfNames,
                'version'     => $this->version,
                'Version'     => $this->version,
                'encoding'    => $this->encoding,
                'docStatus'   => $this->docStatus,
                'Message'     => 'УПД успешно сформирован',
                'Errors'      => '',
            ];
        }

        return [
            'success' => false,
            'Success' => false,
            'errors'  => $this->errors,
            'Errors'  => implode('; ', $this->errors),
            'message' => self::formatMessage($this->errors),
            'Message' => self::formatMessage($this->errors),
            'hints'   => $this->errors,
        ];
    }

    /** @param string[] $errors */
    public static function formatMessage(array $errors): string
    {
        return ValidationMessages::formatList($errors);
    }
}
