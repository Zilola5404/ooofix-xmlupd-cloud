<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Xml;

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
    public static function userFacingMessage(array $errors): string
    {
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
}
