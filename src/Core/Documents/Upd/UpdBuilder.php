<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Documents\Upd;

use Ooofix\XmlupdCloud\Core\Config;
use Ooofix\XmlupdCloud\Core\Contract\DocumentBuilderInterface;
use Ooofix\XmlupdCloud\Core\Xml\XmlFormatter;

/** Сборка и валидация УПД — реализация DocumentBuilderInterface */
class UpdBuilder implements DocumentBuilderInterface
{
    public function __construct(
        private readonly UpdMapper $mapper = new UpdMapper(),
        private readonly UpdValidator $validator = new UpdValidator(),
        private readonly UpdXmlWriter $writer = new UpdXmlWriter(),
    ) {
    }

    public function getType(): string
    {
        return 'upd';
    }

    /** @param array<string, mixed> $mapped */
    public function validate(array $mapped): array
    {
        return $this->validator->validate($mapped);
    }

    /** @param array<string, mixed> $mapped */
    public function build(array $mapped): string
    {
        return XmlFormatter::prettyPrint($this->writer->build($mapped));
    }

    /** @param array<string, mixed> $crmData — результат DataCollector::collect */
    public function process(array $crmData): array
    {
        $mapped = $this->mapper->map($crmData);
        $errors = $this->validate($mapped);

        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors, 'mapped' => $mapped];
        }

        return [
            'success' => true,
            'xml'     => $this->build($mapped),
            'mapped'  => $mapped,
        ];
    }
}
