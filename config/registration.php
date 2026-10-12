<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registration block identifiers
    |--------------------------------------------------------------------------
    |
    | Persistent registration blocks store an HMAC-SHA256 of the normalised
    | identifier, never a plain hash. A plain hash (sha256 of the email) is a
    | dictionary attack: an attacker with the table can confirm whether any
    | guessed address was blocked. An HMAC with a dedicated secret makes that
    | infeasible without the key.
    |
    | `REGISTRATION_BLOCK_KEY` MUST be set. It is deliberately NOT the app key:
    | rotating APP_KEY must not silently make every existing block unmatchable.
    | Generate one with `php artisan key:generate --show` and store it in the
    | deployment secret manager alongside the database credentials.
    |
    | Key lifecycle duties:
    |  - Backup: the key is part of the data. Losing it makes every existing
    |    block unmatchable, because the HMAC cannot be recomputed.
    |  - Rotation: set a new key and a new `key_id`, then reindex existing rows.
    |    `identifier_key_id` records which key produced each stored hash, so a
    |    rotation can recompute a row only when it knows the old key. Until the
    |    reindex completes, lookups must check every active key id.
    |  - Never log the key or the raw identifier next to its hash.
    |
    */

    'block' => [
        'key' => env('REGISTRATION_BLOCK_KEY'),
        'key_id' => env('REGISTRATION_BLOCK_KEY_ID', 'v1'),
        'algorithm' => 'sha256',
    ],

];
