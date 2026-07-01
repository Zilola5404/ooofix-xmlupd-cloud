<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core;

use DOMDocument;
use Ooofix\XmlupdCloud\Core\Xml\XsdErrorFormatter;
use Ooofix\XmlupdCloud\Core\Xml\XsdSchemaRegistry;
use Ooofix\XmlupdCloud\Core\Xml\XmlValidationException;

/**
 * XSD-валидация XML УПД по официальным схемам ФНС (локально в config/schemas).
 * XMLDOC-42
 */
class XmlValidator
{
    /**
     * @return array{
     *     valid: bool,
     *     errors: list<string>,
     *     user_message: string,
     *     schema_path: string|null,
     *     format_version: string|null
     * }
     */
    public function validateDetailed(string $xml, ?string $xsdPath = null, ?string $formatVersion = null): array
    {
        $formatVersion = $formatVersion
            ?? XsdSchemaRegistry::extractFormatVersion($xml)
            ?? Config::xmlFormatVersion();

        try {
            $schemaPath = $xsdPath ?? XsdSchemaRegistry::resolveSellerSchema($formatVersion);
        } catch (\Throwable $e) {
            return $this->fail(
                ['Не удалось определить XSD-схему: ' . $e->getMessage()],
                null,
                $formatVersion
            );
        }

        if (!is_file($schemaPath)) {
            return $this->fail(
                ['XSD-схема не найдена: ' . $schemaPath],
                $schemaPath,
                $formatVersion
            );
        }

        $dom = new DOMDocument();
        $loaded = $this->loadXmlIntoDom($dom, $xml);
        if (!$loaded['ok']) {
            return $this->fail([$loaded['error']], $schemaPath, $formatVersion);
        }

        libxml_use_internal_errors(true);
        libxml_clear_errors();

        $valid = @$dom->schemaValidate($schemaPath);
        $rawErrors = libxml_get_errors();
        libxml_clear_errors();

        if ($valid) {
            return [
                'valid'           => true,
                'errors'          => [],
                'user_message'    => '',
                'schema_path'     => $schemaPath,
                'format_version'  => $formatVersion,
            ];
        }

        $errors = XsdErrorFormatter::format($rawErrors);
        if ($errors === []) {
            $errors = ['Документ не соответствует XSD-схеме ФНС'];
        }

        return $this->fail($errors, $schemaPath, $formatVersion);
    }

    public function validate(string $xml, ?string $xsdPath = null, ?string $formatVersion = null): bool
    {
        return $this->validateDetailed($xml, $xsdPath, $formatVersion)['valid'];
    }

    /**
     * @throws XmlValidationException
     */
    public function assertValid(string $xml, ?string $xsdPath = null, ?string $formatVersion = null): void
    {
        $result = $this->validateDetailed($xml, $xsdPath, $formatVersion);
        if ($result['valid']) {
            return;
        }

        throw new XmlValidationException(
            $result['user_message'],
            $result['errors'],
            $result['schema_path']
        );
    }

    /**
     * @param list<string> $errors
     * @return array{
     *     valid: bool,
     *     errors: list<string>,
     *     user_message: string,
     *     schema_path: string|null,
     *     format_version: string|null
     * }
     */
    private function fail(array $errors, ?string $schemaPath, ?string $formatVersion): array
    {
        return [
            'valid'          => false,
            'errors'         => $errors,
            'user_message'   => XsdErrorFormatter::userFacingMessage($errors),
            'schema_path'    => $schemaPath,
            'format_version' => $formatVersion,
        ];
    }

    /**
     * @return array{ok: bool, error: string}
     */
    private function loadXmlIntoDom(DOMDocument $dom, string $xml): array
    {
        $fileId = XsdSchemaRegistry::extractFileId($xml);
        if ($fileId !== null && $fileId !== '') {
            $safeName = preg_replace('/[^\w\-@.]/u', '_', $fileId) ?? 'upd';
            $tmpPath = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . $safeName . '.xml';

            if (@file_put_contents($tmpPath, $xml) !== false) {
                $loaded = @$dom->load($tmpPath);
                @unlink($tmpPath);

                if ($loaded) {
                    return ['ok' => true, 'error' => ''];
                }
            }
        }

        if (@$dom->loadXML($xml)) {
            return ['ok' => true, 'error' => ''];
        }

        return ['ok' => false, 'error' => 'Не удалось разобрать XML перед XSD-проверкой'];
    }
}
