<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Contract;

use Ooofix\XmlupdCloud\Core\Dto\GenerateRequestDto;
use Ooofix\XmlupdCloud\Core\GenerateResult;

/** Контракт runtime генерации УПД (коробка / облако). */
interface GenerateRuntimeInterface
{
    public function runFromDto(GenerateRequestDto $request): GenerateResult;
}
