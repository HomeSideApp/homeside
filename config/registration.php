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

    /*
    |--------------------------------------------------------------------------
    | Single-use bootstrap lifetimes
    |--------------------------------------------------------------------------
    |
    | A bootstrap grant is the credential that lets a browser start (or recover)
    | a two-factor enrolment without a session. Its lifetime depends on WHAT the
    | grant is for, because the two purposes carry different risk:
    |
    |  - `two_factor_enrolment` (12 hours): the person has to read the mail,
    |    install an authenticator and come back. Thirty minutes made a slow
    |    afternoon a dead end. The grant only lets its holder set up a factor on
    |    an account they just proved they own, and completing it grants no
    |    session unless approval, email verification and account status allow it.
    |
    |  - `account_recovery` (30 minutes): this one can REPLACE the existing second
    |    factor, so a stolen mailbox with a long-lived link becomes a permanent
    |    takeover. Keep it short.
    |
    | Both are `env`-overridable. A non-positive or non-numeric value falls back
    | to the shipped default instead of producing an unusable grant.
    |
    */

    'bootstrap' => [
        'ttl_minutes' => [
            'two_factor_enrolment' => (int) env('REGISTRATION_ENROLMENT_TTL_MINUTES', 720),
            'account_recovery' => (int) env('REGISTRATION_RECOVERY_TTL_MINUTES', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Registration rate limits
    |--------------------------------------------------------------------------
    |
    | The public registration route is throttled on two independent axes, each
    | with a one-minute window:
    |
    |  - `per_ip`: the maximum number of registration attempts from a single
    |    source IP address. It stops one host from mass-creating accounts.
    |  - `per_identifier`: the maximum number of attempts for one normalised
    |    identifier (the lower-cased, transliterated email) from a single IP.
    |    It stops one address from being hammered without punishing every other
    |    visitor behind the same NAT.
    |
    | Both limits apply per-IP AND per-normalised-identifier: the identifier
    | limit is additionally keyed by the request IP, so a distributed set of
    | hosts can never make a single address account for the whole budget, and
    | an anonymous host can never spend another host's identifier budget.
    |
    | Override per environment with the variables below. The defaults are
    | deliberately conservative for a self-hosted install.
    |
    */

    'rate_limits' => [
        'per_ip' => (int) env('REGISTRATION_RATE_LIMIT_PER_IP', 10),
        'per_identifier' => (int) env('REGISTRATION_RATE_LIMIT_PER_IDENTIFIER', 5),

        // Resending the enrolment link for a token that already expired
        // (the `/auth/bootstrap/{token}/2fa` resend endpoint). Two axes: one
        // host cannot hammer the endpoint, and one token cannot be turned into
        // a mail cannon. The token axis is keyed by a digest of the token, so
        // the cache never holds a redeemable credential.
        'enrolment_resend_per_ip' => (int) env('REGISTRATION_ENROLMENT_RESEND_PER_IP', 5),
        'enrolment_resend_per_token' => (int) env('REGISTRATION_ENROLMENT_RESEND_PER_TOKEN', 3),

        // The "I do not have a link" form: it is unauthenticated and it sends
        // mail, so it is limited per source IP and per normalised email
        // address. The address axis is deliberately NOT keyed by IP: a
        // distributed set of hosts must not be able to flood one mailbox.
        'enrolment_link_per_ip' => (int) env('REGISTRATION_ENROLMENT_LINK_PER_IP', 5),
        'enrolment_link_per_identifier' => (int) env('REGISTRATION_ENROLMENT_LINK_PER_IDENTIFIER', 3),
    ],

];
