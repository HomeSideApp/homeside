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
    | Simulated Google provider
    |--------------------------------------------------------------------------
    |
    | The Google browser scenarios must run through the REAL callback route and
    | the REAL OAuth exchange: state validation, the redirect-URI check, the code
    | exchange and the identity mapping all stay in Socialite's real code paths.
    | Only the three provider endpoints are moved to a local simulator.
    |
    | Selection is FAIL-CLOSED. `enabled` is a hint; `environments` is the
    | authority, and it deliberately contains ONLY `e2e`. A production install
    | that somehow exports the flag still talks to Google, because the driver
    | override is never registered outside this list (and the URL must be a
    | loopback address as a second, independent condition). The alternative --
    | refusing to boot -- was rejected: a stray variable must not take a real
    | install down, and the safe direction is to keep using the real provider.
    |
    | The base URL is a SEPARATE listener from the application server. The app
    | calls the token and userinfo endpoints server-to-server; pointing them at
    | its own single-threaded `php artisan serve` would deadlock, so the
    | simulator runs as its own `php -S` process on its own port.
    */

    'google_simulator' => [
        'enabled' => (bool) env('E2E_GOOGLE_SIMULATOR_ENABLED', false),
        'environments' => ['e2e'],
        'host' => env('E2E_GOOGLE_SIMULATOR_HOST', '127.0.0.1'),
        'port' => (int) env('E2E_GOOGLE_SIMULATOR_PORT', 8011),
        'base_url' => env('E2E_GOOGLE_SIMULATOR_URL', 'http://127.0.0.1:8011'),
        'router' => base_path('app/Support/Auth/GoogleSimulator/router.php'),
        'state_file' => env(
            'E2E_GOOGLE_SIMULATOR_STATE_FILE',
            rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'homeside-e2e-google-simulator.json',
        ),
        'pid_file' => env(
            'E2E_GOOGLE_SIMULATOR_PID_FILE',
            rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'homeside-e2e-google-simulator.pid',
        ),
        'log_file' => env(
            'E2E_GOOGLE_SIMULATOR_LOG_FILE',
            storage_path('logs/e2e-google-simulator.log'),
        ),
    ],

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
