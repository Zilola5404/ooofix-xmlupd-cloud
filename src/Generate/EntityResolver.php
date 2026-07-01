<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Generate;

use Ooofix\XmlupdCloud\Core\Config;
use Ooofix\XmlupdCloud\Rest\RestDataCollector;

/** Определение сущности CRM из document_id робота (скопировано из модуля коробки) */
final class EntityResolver
{
    /**
     * @param array<int, mixed> $documentId
     * @return array{0: string, 1: int}
     */
    public static function fromDocumentId(array $documentId): array
    {
        $docClass = (string)($documentId[1] ?? '');
        $docKey = (string)($documentId[2] ?? '');

        if ($docKey === '') {
            throw new \RuntimeException('Не удалось определить документ БП');
        }

        if (preg_match('/^DEAL_(\d+)$/i', $docKey, $m)) {
            return [RestDataCollector::TYPE_DEAL, (int)$m[1]];
        }

        if (preg_match('/^DYNAMIC_(\d+)_(\d+)$/i', $docKey, $m)) {
            $typeId = (int)$m[1];
            $itemId = (int)$m[2];
            $smartTypeId = Config::smartInvoiceTypeId();

            if ($smartTypeId > 0 && $typeId === $smartTypeId) {
                return [RestDataCollector::TYPE_SMART_INVOICE, $itemId];
            }

            throw new \RuntimeException(
                'Смарт-процесс entityTypeId=' . $typeId . ' не настроен как СП «Счета»'
            );
        }

        if (stripos($docClass, 'Deal') !== false && preg_match('/(\d+)/', $docKey, $m)) {
            return [RestDataCollector::TYPE_DEAL, (int)$m[1]];
        }

        throw new \RuntimeException('Неподдерживаемый тип документа: ' . $docClass . ' / ' . $docKey);
    }
}
