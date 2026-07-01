<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Contract;

/**
 * Абстракция CRM для коробки и облака. XMLDOC-26
 */
interface CrmAdapterInterface
{
    public function isCloud(): bool;

    public function getOwnerTypeId(string $entityType): int;

    /** Регистрация триггера автоматизации CRM (crm.automation.trigger.add). */
    public function registerAutomationTrigger(string $code, string $name): bool;

    /** Запуск триггера (crm.automation.trigger.execute / внутренний API). */
    public function executeAutomationTrigger(string $code, int $ownerTypeId, int $ownerId): bool;
}
