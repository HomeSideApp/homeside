<?php

namespace App\Actions\Sync;

/**
 * Encodes and decodes the opaque cursor of the offline delta feed.
 *
 * The payload carries the `updated_at` and `id` of the last emitted row so the
 * next call can continue with the exact same `(updated_at, id)` tuple. Both
 * halves are wrapped in base64url and the shape is deliberately undocumented:
 * clients must treat the value as an opaque token, so the ordering key can
 * change without breaking them.
 */
final class SyncCursor
{
    /**
     * The oldest representable position, used when a feed starts and has no rows.
     */
    private const string INITIAL_TIMESTAMP = '1970-01-01 00:00:00';

    private const string TIMESTAMP_PATTERN = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';

    /**
     * Build the cursor that represents "nothing emitted yet".
     */
    public static function initial(): string
    {
        return self::encode(self::INITIAL_TIMESTAMP, '');
    }

    /**
     * Encode the ordering key of the last emitted row.
     *
     * @param  string  $updatedAt  The row timestamp in database format.
     * @param  string  $id  The row identifier.
     * @return string The opaque cursor.
     */
    public static function encode(string $updatedAt, string $id): string
    {
        $payload = json_encode(['t' => $updatedAt, 'i' => $id], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    /**
     * Decode a client-supplied cursor.
     *
     * @param  string  $cursor  The opaque cursor.
     * @return array{t: string, i: string}|null The ordering key, or null when the cursor is malformed.
     */
    public static function decode(string $cursor): ?array
    {
        $base64 = strtr($cursor, '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);

        $json = base64_decode($base64, true);

        if ($json === false) {
            return null;
        }

        try {
            $payload = json_decode($json, true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        $timestamp = $payload['t'] ?? null;
        $id = $payload['i'] ?? null;

        if (! is_string($timestamp) || ! is_string($id) || preg_match(self::TIMESTAMP_PATTERN, $timestamp) !== 1) {
            return null;
        }

        return ['t' => $timestamp, 'i' => $id];
    }
}
