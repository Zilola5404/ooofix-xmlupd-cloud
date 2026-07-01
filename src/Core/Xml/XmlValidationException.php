<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Xml;

/** Ошибка XSD-валидации с детализацией для UI и лога. */
final class XmlValidationException extends \RuntimeException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        string $userMessage,
        private readonly array $errors = [],
        private readonly ?string $schemaPath = null,
    ) {
        parent::__construct($userMessage);
    }

    /** @return list<string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function schemaPath(): ?string
    {
        return $this->schemaPath;
    }
}
