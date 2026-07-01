<?php

declare(strict_types=1);

namespace Ooofix\XmlupdCloud\Core;

/**
 * Обогащение адресов через DaData (без Bitrix, только HTTP).
 */
final class DadataClient
{
    private const API_URL = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party';

    /** @param array<string, mixed> $buyer */
    public function enrich(array $buyer): array
    {
        $inn = trim((string)($buyer['INN'] ?? ''));
        if ($inn === '') {
            return $buyer;
        }

        $key = Config::dadataApiKey();
        if ($key === '') {
            return $buyer;
        }

        $party = $this->fetchParty($inn, $key);
        if ($party === null) {
            return $buyer;
        }

        $address = (string)($party['address']['unrestricted_value'] ?? '');
        if ($address !== '') {
            $buyer['ADDRESS_FULL'] = $address;
            $buyer['_DADATA_ENRICHED'] = true;
        }

        return $buyer;
    }

    /** @return array<string, mixed>|null */
    private function fetchParty(string $inn, string $apiKey): ?array
    {
        $ch = curl_init(self::API_URL);
        if ($ch === false) {
            return null;
        }

        $body = json_encode(['query' => $inn], JSON_UNESCAPED_UNICODE);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Token ' . $apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!is_string($response) || $response === '') {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return null;
        }

        $suggestions = $data['suggestions'] ?? [];

        return is_array($suggestions[0]['data'] ?? null) ? $suggestions[0]['data'] : null;
    }
}
