<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Contract;

/** Результат операции генерации */
interface GenerateResultInterface
{
    public function isSuccess(): bool;

    /** @return string[] */
    public function getErrors(): array;

    public function getFileId(): ?int;

    public function getFileName(): ?string;
}
