<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Xml;

use DOMDocument;

/** Форматирование XML с отступами (как в выгрузке Diadoc / edo_*.xml). */
final class XmlFormatter
{
    public static function prettyPrint(string $xml): string
    {
        if (!class_exists(DOMDocument::class)) {
            return $xml;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        if (@$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS) === false) {
            return $xml;
        }

        $formatted = $dom->saveXML();
        if (!is_string($formatted) || $formatted === '') {
            return $xml;
        }

        return $formatted;
    }
}
