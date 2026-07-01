<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Xml;

/**
 * Потоковая генерация XML через ext-xmlwriter с fallback на DOMDocument (ext-dom).
 * API повторяет методы, используемые UpdXmlWriter.
 */
final class WriterBuffer
{
    private bool $useXmlWriter = false;

    private ?\XMLWriter $xw = null;

    private ?\DOMDocument $dom = null;

    /** @var \DOMElement[] */
    private array $stack = [];

    public function openMemory(): void
    {
        if (extension_loaded('xmlwriter')) {
            $this->useXmlWriter = true;
            $this->xw = new \XMLWriter();
            $this->xw->openMemory();
            $this->xw->setIndent(true);
            $this->xw->setIndentString('  ');

            return;
        }

        $this->useXmlWriter = false;
        $this->stack = [];
    }

    public function startDocument(string $version, string $encoding): void
    {
        if ($this->useXmlWriter && $this->xw !== null) {
            $this->xw->startDocument($version, $encoding);

            return;
        }

        if (!class_exists(\DOMDocument::class)) {
            throw new \RuntimeException(
                'На сервере не установлено расширение PHP DOM (ext-dom). Обратитесь к администратору портала.'
            );
        }

        $this->dom = new \DOMDocument($version, $encoding);
        $this->dom->encoding = $encoding;
        $this->dom->formatOutput = true;
        $this->dom->preserveWhiteSpace = false;
    }

    public function startElement(string $name): void
    {
        if ($this->useXmlWriter && $this->xw !== null) {
            $this->xw->startElement($name);

            return;
        }

        $element = $this->dom->createElement($name);

        if ($this->stack === []) {
            $this->dom->appendChild($element);
        } else {
            $this->current()->appendChild($element);
        }

        $this->stack[] = $element;
    }

    public function writeAttribute(string $name, string $value): void
    {
        if ($this->useXmlWriter && $this->xw !== null) {
            $this->xw->writeAttribute($name, $value);

            return;
        }

        if ($this->stack === []) {
            return;
        }

        $this->current()->setAttribute($name, $value);
    }

    public function text(string $content): void
    {
        if ($this->useXmlWriter && $this->xw !== null) {
            $this->xw->text($content);

            return;
        }

        if ($this->stack === []) {
            return;
        }

        $this->current()->appendChild($this->dom->createTextNode($content));
    }

    public function endElement(): void
    {
        if ($this->useXmlWriter && $this->xw !== null) {
            $this->xw->endElement();

            return;
        }

        if ($this->stack !== []) {
            array_pop($this->stack);
        }
    }

    public function outputMemory(): string
    {
        if ($this->useXmlWriter && $this->xw !== null) {
            $xml = $this->xw->outputMemory(true);

            return is_string($xml) ? $xml : '';
        }

        $xml = $this->dom?->saveXML();

        return is_string($xml) ? $xml : '';
    }

    private function current(): \DOMElement
    {
        return $this->stack[count($this->stack) - 1];
    }
}
