<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Contract;

/** Заглушка под ЭДО — реализация на этапе 2+ */
interface EdoGatewayInterface
{
    /** @param array<string, mixed> $context */
    public function send(string $xml, array $context): array;
}
