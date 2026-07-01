<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Dto;

/** Запрос на генерацию УПД. XMLDOC-27 */
final class GenerateRequestDto
{
    public function __construct(
        public readonly EntityContextDto $context,
        public readonly bool $checkPermissions = true,
    ) {
    }
}
