<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core\Person;

/** Разбор ФИО для XML (Фамилия / Имя / Отчество) */
final class FioParser
{
    /**
     * @return array{last: string, first: string, middle: string}
     */
    public static function resolve(string $last, string $first, string $middle, string $fullName = ''): array
    {
        $last = trim($last);
        $first = trim($first);
        $middle = trim($middle);

        if ($last !== '' && $first !== '') {
            return ['last' => $last, 'first' => $first, 'middle' => $middle];
        }

        $combined = trim($fullName);
        if ($combined === '') {
            $combined = trim(implode(' ', array_filter([$last, $first, $middle], static fn(string $p): bool => $p !== '')));
        }

        if ($combined === '') {
            return ['last' => '', 'first' => '', 'middle' => ''];
        }

        $combined = preg_replace('/^ИП\s+/u', '', $combined) ?? $combined;
        $parts = preg_split('/\s+/u', $combined, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) >= 3) {
            return [
                'last'   => $parts[0],
                'first'  => $parts[1],
                'middle' => trim(implode(' ', array_slice($parts, 2))),
            ];
        }

        if (count($parts) === 2) {
            return ['last' => $parts[0], 'first' => $parts[1], 'middle' => $middle];
        }

        $token = $parts[0];
        if ($last !== '') {
            return ['last' => $last, 'first' => $token, 'middle' => $middle];
        }
        if ($first !== '') {
            return ['last' => $token, 'first' => $first, 'middle' => $middle];
        }

        return ['last' => $token, 'first' => $token, 'middle' => $middle];
    }
}
