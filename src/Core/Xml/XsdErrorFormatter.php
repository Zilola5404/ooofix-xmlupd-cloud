<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Xml;

use Ooofix\XmlupdCloud\Core\Documents\Upd\OkeiMeasureValidator;
use Ooofix\XmlupdCloud\Core\ValidationMessages;

/** Форматирование ошибок libxml для пользователя и журнала. */
final class XsdErrorFormatter
{
    /**
     * @param list<\LibXMLError> $libxmlErrors
     * @return list<string>
     */
    public static function format(array $libxmlErrors): array
    {
        $messages = [];
        foreach ($libxmlErrors as $error) {
            $text = self::formatOne($error);
            if ($text !== '') {
                $messages[] = $text;
            }
        }

        return array_values(array_unique($messages));
    }

    private static function formatOne(\LibXMLError $error): string
    {
        $message = trim($error->message);
        if ($message === '') {
            return '';
        }

        $message = preg_replace('/\s+/u', ' ', $message) ?? $message;

        if ($error->line > 0) {
            return sprintf('Строка %d: %s', $error->line, $message);
        }

        return $message;
    }

    /**
     * @param list<string> $errors
     */
    public static function userFacingMessage(array $errors, ?string $xml = null): string
    {
        if ($xml !== null) {
            $okeiMessage = self::buildOkeiMessageFromXml($xml);
            if ($okeiMessage !== null) {
                return $okeiMessage;
            }
        }

        if (self::containsOkeiError($errors)) {
            return ValidationMessages::productMeasureInvalid(0, '', '');
        }

        if ($errors === []) {
            return "Не удалось сформировать УПД.\n\nОшибка XSD: документ не соответствует схеме ФНС.";
        }

        $visible = array_slice($errors, 0, 5);
        $body = implode("\n", $visible);
        if (count($errors) > 5) {
            $body .= "\n… и ещё " . (count($errors) - 5) . ' ошибок';
        }

        return "Не удалось сформировать УПД.\n\nОшибка XSD:\n" . $body;
    }

    /**
     * @param list<string> $errors
     */
    private static function containsOkeiError(array $errors): bool
    {
        foreach ($errors as $error) {
            if (self::isOkeiErrorText($error)) {
                return true;
            }
        }

        return false;
    }

    private static function isOkeiErrorText(string $text): bool
    {
        return str_contains($text, 'ОКЕИ_Тов')
            || str_contains($text, 'ОКЕИ_Тov')
            || str_contains($text, 'ОКЕИ');
    }

    private static function buildOkeiMessageFromXml(string $xml): ?string
    {
        if (!preg_match_all('/<(?:[\w-]+:)?СведТов\b([^>]*)\/?>/u', $xml, $matches)) {
            return null;
        }

        foreach ($matches[1] as $attrString) {
            $okei = self::readXmlAttribute($attrString, 'ОКЕИ_Тов');
            if ($okei === null || OkeiMeasureValidator::isValidCode($okei)) {
                continue;
            }

            $name = self::readXmlAttribute($attrString, 'НаимТов') ?? '';
            $measure = self::readXmlAttribute($attrString, 'НаимЕдИзм') ?? $okei;

            return ValidationMessages::productMeasureInvalid(0, $name, $measure);
        }

        return null;
    }

    private static function readXmlAttribute(string $attrString, string $name): ?string
    {
        $pattern = '/\b' . preg_quote($name, '/') . '="([^"]*)"/u';
        if (!preg_match($pattern, $attrString, $match)) {
            return null;
        }

        return html_entity_decode($match[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
