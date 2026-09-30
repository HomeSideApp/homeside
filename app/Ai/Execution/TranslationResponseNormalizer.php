<?php

declare(strict_types=1);

namespace App\Ai\Execution;

/**
 * Parses and validates the JSON response of the translation agent.
 *
 * Validates that the structure is `{translations: [{client_id, value}],
 * unmatched: [string]}`, that the client_ids are known and that values
 * are non-empty strings within the database limit.
 */
final class TranslationResponseNormalizer
{
    /**
     * Collect the validation errors of a parsed translation response against
     * the set of client_ids sent to the agent.
     *
     * @param  array<string, mixed>  $response  Parsed response
     * @param  list<string>  $expectedIds  client_ids sent in the request
     * @return list<string>
     */
    public static function validationErrors(array $response, array $expectedIds): array
    {
        $errors = [];

        if (! is_array($response['translations'] ?? null) || ! is_array($response['unmatched'] ?? null)) {
            $errors[] = 'La respuesta debe incluir los arrays "translations" y "unmatched".';

            return $errors;
        }

        $expected = array_fill_keys($expectedIds, true);
        $translatedIds = [];

        foreach ($response['translations'] as $index => $item) {
            if (! is_array($item)) {
                $errors[] = sprintf('La traducción %d no tiene una estructura válida.', $index + 1);

                continue;
            }

            $clientId = $item['client_id'] ?? null;

            if (! is_string($clientId) || ! isset($expected[$clientId])) {
                $errors[] = sprintf('La traducción %d referencia un client_id desconocido.', $index + 1);

                continue;
            }

            $translatedIds[] = $clientId;

            if (! is_string($item['value'] ?? null) || trim($item['value']) === '' || mb_strlen($item['value']) > 255) {
                $errors[] = sprintf('La traducción del client_id %s debe tener un value no vacío de máximo 255 caracteres.', $clientId);
            }
        }

        $duplicated = array_keys(array_filter(array_count_values($translatedIds), fn (int $count): bool => $count > 1));

        foreach ($duplicated as $clientId) {
            $errors[] = sprintf('El client_id %s aparece más de una vez en "translations".', $clientId);
        }

        foreach ($response['unmatched'] as $unmatchedId) {
            if (! is_string($unmatchedId) || ! isset($expected[$unmatchedId])) {
                $errors[] = 'El array "unmatched" solo puede contener client_id conocidos.';
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * Parse JSON from the agent response (direct, markdown code block, or
     * embedded).
     *
     * @return array<string, mixed>|null
     */
    public static function parseJsonFromResponse(string $content): ?array
    {
        // 1. Intento directo
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // 2. Extract from markdown code block: ```json ... ```
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $content, $matches)) {
            $decoded = json_decode(trim($matches[1]), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // 3. Find the first { and last } to extract embedded JSON
        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $jsonStr = substr($content, $start, $end - $start + 1);
            $decoded = json_decode($jsonStr, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
