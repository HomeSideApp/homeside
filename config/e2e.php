<?php

return [

    /*
    |--------------------------------------------------------------------------
    | E2E environment allow-list
    |--------------------------------------------------------------------------
    |
    | `e2e:seed`, `e2e:reset` and `e2e:policy` may destroy or rewrite the target
    | database, so they refuse to run unless the application itself declares a
    | testing environment. `local`, `staging` and `production` are never in this
    | list on purpose: a destructive command must not be one typo away from
    | wiping a real install.
    |
    */

    'environments' => ['testing', 'e2e'],

    /*
    |--------------------------------------------------------------------------
    | E2E database marker
    |--------------------------------------------------------------------------
    |
    | The second, independent guard: the resolved database name must END WITH
    | this marker. The environment check alone is not enough — a developer can
    | export APP_ENV=testing while still pointing at the development database —
    | so the destructive commands require BOTH the environment and a database
    | whose name proves it is disposable (`testing_e2e`, `homeside_e2e`, ...).
    |
    | The PHPUnit suite runs against the `testing` database, which deliberately
    | does NOT carry the marker, so the feature tests can never trigger a wipe.
    |
    */

    'database_marker' => env('E2E_DATABASE_MARKER', '_e2e'),

    /*
    |--------------------------------------------------------------------------
    | Isolated application server
    |--------------------------------------------------------------------------
    |
    | The Playwright run brings up its own `php artisan serve` instance bound to
    | the loopback interface, already pointing at the E2E database and the
    | existing Mailpit container. That keeps a browser run from touching the
    | developer's own database while still reusing the stack that is already
    | running (MySQL, Redis, Mailpit).
    |
    */

    'host' => env('E2E_HOST', '127.0.0.1'),
    'port' => (int) env('E2E_PORT', 8010),

    /*
    |--------------------------------------------------------------------------
    | Secrets file
    |--------------------------------------------------------------------------
    |
    | Passwords and TOTP secrets are GENERATED for the E2E fixtures and written
    | to a 0600 file OUTSIDE the repository. They are never committed, never
    | printed by a command and never embedded in a spec. The Playwright harness
    | reads them from here. Override the path (for example to a mounted host
    | directory) with E2E_SECRETS_FILE.
    |
    */

    'secrets_file' => env(
        'E2E_SECRETS_FILE',
        rtrim(getenv('HOME') ?: sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'.local'.DIRECTORY_SEPARATOR.'share'.DIRECTORY_SEPARATOR.'homeside-e2e'.DIRECTORY_SEPARATOR.'secrets.json',
    ),

];
