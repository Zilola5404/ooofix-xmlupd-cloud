<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Generate;

use Ooofix\XmlupdCloud\Core\Config;
use Ooofix\XmlupdCloud\Core\DocumentStatus;
use Ooofix\XmlupdCloud\Core\Documents\Upd\UpdBuilder;
use Ooofix\XmlupdCloud\Core\Dto\EntityContextDto;
use Ooofix\XmlupdCloud\Core\Dto\GenerateRequestDto;
use Ooofix\XmlupdCloud\Core\GenerateResult;
use Ooofix\XmlupdCloud\Core\Logger;
use Ooofix\XmlupdCloud\Core\ValidationMessages;
use Ooofix\XmlupdCloud\Core\XmlEncoder;
use Ooofix\XmlupdCloud\Core\XmlValidator;
use Ooofix\XmlupdCloud\Rest\BitrixClient;
use Ooofix\XmlupdCloud\Rest\RestDataCollector;
use Ooofix\XmlupdCloud\Rest\RestFileSaver;
use Ooofix\XmlupdCloud\Rest\TriggerService;
use Ooofix\XmlupdCloud\Rest\UserFieldCodes;
use Ooofix\XmlupdCloud\Storage\DocumentRepository;

/**
 * Конвейер генерации УПД для облака (аналог AbstractGenerateRuntime модуля коробки).
 */
final class GenerateService
{
    public function __construct(
        private readonly BitrixClient $client,
        private readonly int $currentUserId = 0,
        private readonly bool $deferDiskToClient = false,
        private readonly UpdBuilder $builder = new UpdBuilder(),
        private readonly XmlValidator $xmlValidator = new XmlValidator(),
    ) {
    }

    public function runFromDto(GenerateRequestDto $request): GenerateResult
    {
        $portalId = $this->client->portalId();
        if ($portalId <= 0) {
            return GenerateResult::fail(['Портал не установлен']);
        }

        Config::bindPortal($portalId);

        $entityType = $request->context->entityType;
        $entityId = $request->context->entityId;

        Logger::info('Запуск генерации УПД', $entityType, $entityId);

        try {
            $collector = new RestDataCollector($this->client, currentUserId: $this->currentUserId);
            $crmData = $collector->collect($entityType, $entityId);

            $preErrors = ValidationMessages::preValidate($crmData, $entityType);
            if ($preErrors !== []) {
                Logger::validateError(implode('; ', $preErrors), $entityType, $entityId);

                return GenerateResult::fail($preErrors);
            }

            $result = $this->builder->process($crmData);
            if (!$result['success']) {
                $errors = $result['errors'] ?? ['Неизвестная ошибка'];
                Logger::validateError(implode('; ', $errors), $entityType, $entityId);

                return GenerateResult::fail($errors);
            }

            $xml = (string)$result['xml'];
            $xmlCheck = $this->xmlValidator->validateDetailed($xml);
            if (!$xmlCheck['valid']) {
                $msg = (string)($xmlCheck['user_message'] ?? 'XML не прошёл XSD-проверку');
                Logger::error($msg, $entityType, $entityId);

                return GenerateResult::fail([$msg]);
            }

            $mapped = $result['mapped'] ?? [];
            $entityTypeId = (int)($crmData['entity']['ENTITY_TYPE_ID'] ?? BitrixClient::dealTypeId());
            $docNumber = (string)($mapped['doc_number'] ?? $entityId);

            if ($this->deferDiskToClient) {
                $documents = new DocumentRepository($portalId);
                $version = $documents->getNextVersion($entityType, $entityId);
                $fileName = sprintf('УПД_%d.xml', $entityId);
                $storageName = sprintf('УПД_%d_v%d.xml', $entityId, $version);
                $encoding = Config::fileEncoding();
                $xmlContent = XmlEncoder::forStorage($xml);

                Logger::success(
                    sprintf('УПД сформирован (загрузка на Диск через BX24): %s, версия %d', $fileName, $version),
                    $entityType,
                    $entityId
                );

                [$ufFileKey, $ufNumberKey, $useOriginalUfNames] = $this->resolveUfItemKeys($entityType);

                return GenerateResult::okClientDisk(
                    base64_encode($xmlContent),
                    $fileName,
                    $storageName,
                    $version,
                    $encoding,
                    $docNumber,
                    $entityType,
                    $entityId,
                    $entityTypeId,
                    $ufFileKey,
                    $ufNumberKey,
                    $useOriginalUfNames,
                );
            }

            $fileSaver = new RestFileSaver($this->client, new DocumentRepository($portalId));
            $file = $fileSaver->save($entityType, $entityId, $entityTypeId, $xml, $docNumber);

            Logger::success(
                sprintf('УПД сформирован: %s, версия %d (общий Диск B24 /XML/)', $file['fileName'], $file['version'] ?? 1),
                $entityType,
                $entityId
            );

            (new TriggerService($this->client))->fireUpdGenerated($entityType, $entityId, $entityTypeId);

            return GenerateResult::ok(
                (int)$file['fileId'],
                (string)$file['fileName'],
                (int)($file['version'] ?? 1),
                (string)($file['encoding'] ?? 'windows-1251'),
                (string)($file['docStatus'] ?? DocumentStatus::GENERATED),
                (string)($file['downloadUrl'] ?? ''),
                (string)($file['detailUrl'] ?? ''),
            );
        } catch (\Throwable $e) {
            Logger::error($e->getMessage(), $entityType, $entityId);

            return GenerateResult::fail([$e->getMessage()]);
        }
    }

    public static function request(
        string $entityType,
        int $entityId,
        bool $checkPermissions = true,
        int $ownerTypeId = 0,
    ): GenerateRequestDto {
        return new GenerateRequestDto(
            EntityContextDto::from($entityType, $entityId, $ownerTypeId),
            $checkPermissions
        );
    }

    /** @return array{0: string, 1: string, 2: bool} */
    private function resolveUfItemKeys(string $entityType): array
    {
        if ($entityType === RestDataCollector::TYPE_DEAL) {
            return [UserFieldCodes::DEAL_FILE, UserFieldCodes::DEAL_NUMBER, true];
        }

        $spaId = Config::smartInvoiceSpaId();

        return [
            UserFieldCodes::smartItemFieldKey($spaId, UserFieldCodes::SUFFIX_FILE),
            UserFieldCodes::smartItemFieldKey($spaId, UserFieldCodes::SUFFIX_NUMBER),
            false,
        ];
    }
}
