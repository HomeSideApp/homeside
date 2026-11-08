<?php

declare(strict_types=1);

/**
 * Standalone simulator for Google's three OAuth endpoints.
 *
 * It is a plain `php -S` router script, NOT a Laravel route: the application
 * calls the token and userinfo endpoints server-to-server while it is itself
 * handling the callback, and pointing those calls at the application's own
 * single-threaded server would deadlock. This process is a separate listener on
 * its own port, started by `e2e:serve` (see E2EServeCommand).
 *
 * Endpoints
 *   GET  /oauth2/auth       the consent screen the browser follows
 *   POST /oauth2/consent    the consent decision, redirects to the REAL callback
 *   POST /oauth2/token      the server-to-server code exchange
 *   GET  /oauth2/userinfo   the bearer-token identity lookup
 *
 * Control endpoints (test-only, never used by the application)
 *   GET  /__health          liveness probe
 *   POST /__reset           clear scenario, codes, tokens and counters
 *   POST /__scenario        set the identity returned for the next run
 *   GET  /__calls           read the call counters and the last request context
 *
 * The authorization code is single-use and the access token is opaque; the
 * application validates `state` itself, so a forged callback fails there.
 */

use App\Support\Auth\GoogleSimulator\SimulatorState;

require __DIR__.'/../../../../vendor/autoload.php';

$statePath = getenv('E2E_GOOGLE_SIMULATOR_STATE_FILE');
$statePath = is_string($statePath) && $statePath !== ''
    ? $statePath
    : rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'homeside-e2e-google-simulator.json';

$clientId = (string) getenv('GOOGLE_CLIENT_ID');
$clientSecret = (string) getenv('GOOGLE_CLIENT_SECRET');
$appUrl = rtrim((string) (getenv('APP_URL') ?: 'http://localhost:8010'), '/');

// Both callbacks the browser suite can use: login and the authenticated link.
$allowedRedirects = [
    $appUrl.'/auth/google/callback',
    $appUrl.'/settings/google/callback',
];

$state = new SimulatorState($statePath);
$path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

/** Send a JSON response and stop. */
function respond_json(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);

    exit;
}

/** Send an HTML error page and stop. */
function respond_error(int $status, string $message, string $detail = ''): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES);
    $safeDetail = htmlspecialchars($detail, ENT_QUOTES);

    echo <<<HTML
        <!doctype html><html lang="en"><head><meta charset="utf-8"><title>Simulated Google</title></head>
        <body style="font-family:system-ui;max-width:40rem;margin:4rem auto">
        <h1>{$safeMessage}</h1><p><code>{$safeDetail}</code></p></body></html>
        HTML;

    exit;
}

/** Read the JSON request body. */
function json_body(): array
{
    $raw = (string) file_get_contents('php://input');
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

/** Normalise a scenario payload from a test. */
function normalise_identity(array $input): array
{
    $verified = $input['email_verified'] ?? true;

    return [
        'email' => mb_strtolower(trim((string) ($input['email'] ?? ''))),
        'sub' => trim((string) ($input['sub'] ?? '')),
        'name' => trim((string) ($input['name'] ?? '')) ?: (string) ($input['email'] ?? ''),
        'email_verified' => filter_var($verified, FILTER_VALIDATE_BOOLEAN),
        'picture' => (string) ($input['picture'] ?? ''),
    ];
}

if ($method === 'GET' && $path === '/__health') {
    respond_json(200, ['status' => 'ok']);
}

if ($method === 'POST' && $path === '/__reset') {
    $state->reset();
    respond_json(200, ['ok' => true]);
}

if ($method === 'POST' && $path === '/__scenario') {
    $identity = normalise_identity(json_body());

    if ($identity['email'] === '' || $identity['sub'] === '') {
        respond_json(422, ['error' => 'invalid_scenario', 'message' => 'email and sub are required']);
    }

    $state->setScenario($identity);
    respond_json(200, ['ok' => true, 'scenario' => $identity]);
}

if ($method === 'GET' && $path === '/__calls') {
    respond_json(200, $state->snapshot());
}

if ($method === 'GET' && $path === '/oauth2/auth') {
    $query = $_GET;
    $requestedClient = (string) ($query['client_id'] ?? '');
    $redirect = (string) ($query['redirect_uri'] ?? '');
    $requestedState = (string) ($query['state'] ?? '');

    $state->recordCall('auth', [
        'client_id' => $requestedClient,
        'redirect_uri' => $redirect,
        'state' => $requestedState,
        'scope' => (string) ($query['scope'] ?? ''),
    ]);

    if ($requestedClient !== $clientId) {
        $state->recordCall('denied', ['reason' => 'invalid_client', 'endpoint' => 'auth']);
        respond_error(400, 'Invalid client id', $requestedClient);
    }

    if (! in_array($redirect, $allowedRedirects, true)) {
        $state->recordCall('denied', ['reason' => 'invalid_redirect_uri', 'redirect_uri' => $redirect]);
        respond_error(400, 'Invalid redirect URI', $redirect);
    }

    if ($requestedState === '') {
        $state->recordCall('denied', ['reason' => 'missing_state']);
        respond_error(400, 'Missing state', 'state is required');
    }

    $scenario = $state->scenario();

    if ($scenario === null) {
        $state->recordCall('denied', ['reason' => 'no_scenario']);
        respond_error(409, 'No simulated identity configured', 'POST /__scenario first');
    }

    $email = htmlspecialchars((string) $scenario['email'], ENT_QUOTES);
    $sub = htmlspecialchars((string) $scenario['sub'], ENT_QUOTES);
    $verified = $scenario['email_verified'] === true ? 'verified' : 'NOT verified';
    $hidden = '';

    foreach (['state' => $requestedState, 'redirect_uri' => $redirect, 'scope' => (string) ($query['scope'] ?? '')] as $name => $value) {
        $hidden .= sprintf(
            '<input type="hidden" name="%s" value="%s" />',
            htmlspecialchars($name, ENT_QUOTES),
            htmlspecialchars($value, ENT_QUOTES),
        );
    }

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo <<<HTML
        <!doctype html><html lang="en"><head><meta charset="utf-8">
        <title>Sign in - Simulated Google</title></head>
        <body style="font-family:system-ui;max-width:32rem;margin:4rem auto">
        <h1>Simulated Google account</h1>
        <ul>
          <li>Email: <strong>{$email}</strong> ({$verified})</li>
          <li>Subject: <code>{$sub}</code></li>
        </ul>
        <form method="POST" action="/oauth2/consent">
          {$hidden}
          <button type="submit" name="decision" value="allow">Continue</button>
          <button type="submit" name="decision" value="deny">Cancel</button>
        </form>
        </body></html>
        HTML;

    exit;
}

if ($method === 'POST' && $path === '/oauth2/consent') {
    $redirect = (string) ($_POST['redirect_uri'] ?? '');
    $requestedState = (string) ($_POST['state'] ?? '');
    $decision = (string) ($_POST['decision'] ?? 'deny');
    $scope = (string) ($_POST['scope'] ?? '');

    $state->recordCall('consent', [
        'redirect_uri' => $redirect,
        'state' => $requestedState,
        'decision' => $decision,
    ]);

    if (! in_array($redirect, $allowedRedirects, true) || $requestedState === '') {
        $state->recordCall('denied', ['reason' => 'invalid_consent']);

        respond_error(400, 'Invalid consent request', $redirect);
    }

    if ($decision !== 'allow') {
        $state->recordCall('denied', ['reason' => 'consent_denied']);

        header('Location: '.$redirect.'?error=access_denied&state='.rawurlencode($requestedState), true, 302);

        exit;
    }

    $scenario = $state->scenario();

    if ($scenario === null) {
        $state->recordCall('denied', ['reason' => 'no_scenario', 'endpoint' => 'consent']);

        respond_error(409, 'No simulated identity configured', 'POST /__scenario first');
    }

    $code = $state->issueCode([
        'scenario' => $scenario,
        'redirect_uri' => $redirect,
        'client_id' => $clientId,
    ]);

    header(
        'Location: '.$redirect.'?code='.rawurlencode($code)
        .'&state='.rawurlencode($requestedState)
        .'&scope='.rawurlencode($scope),
        true,
        302,
    );

    exit;
}

if ($method === 'POST' && $path === '/oauth2/token') {
    $requestClient = (string) ($_POST['client_id'] ?? '');
    $requestSecret = (string) ($_POST['client_secret'] ?? '');
    $redirect = (string) ($_POST['redirect_uri'] ?? '');
    $code = (string) ($_POST['code'] ?? '');

    $state->recordCall('token', [
        'client_id' => $requestClient,
        'redirect_uri' => $redirect,
        'has_code' => $code !== '',
        'grant_type' => (string) ($_POST['grant_type'] ?? ''),
    ]);

    if ($requestClient !== $clientId || $requestSecret !== $clientSecret) {
        $state->recordCall('denied', ['reason' => 'invalid_client', 'endpoint' => 'token']);
        respond_json(401, ['error' => 'invalid_client']);
    }

    if (! in_array($redirect, $allowedRedirects, true)) {
        $state->recordCall('denied', ['reason' => 'invalid_redirect_uri', 'endpoint' => 'token']);
        respond_json(400, ['error' => 'invalid_grant', 'error_description' => 'redirect_uri mismatch']);
    }

    $payload = $state->consumeCode($code);

    if ($payload === null) {
        $state->recordCall('denied', ['reason' => 'invalid_code', 'endpoint' => 'token']);
        respond_json(400, ['error' => 'invalid_grant', 'error_description' => 'code is unknown or already used']);
    }

    $identity = is_array($payload['scenario'] ?? null) ? $payload['scenario'] : [];
    $token = $state->issueToken($identity);

    respond_json(200, [
        'access_token' => $token,
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'scope' => 'openid profile email',
    ]);
}

if ($method === 'GET' && $path === '/oauth2/userinfo') {
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    $token = str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';

    $identity = $token !== '' ? $state->resolveToken($token) : null;

    $state->recordCall('userinfo', [
        'has_token' => $token !== '',
        'email' => is_array($identity) ? ($identity['email'] ?? null) : null,
    ]);

    if ($identity === null) {
        $state->recordCall('denied', ['reason' => 'invalid_token', 'endpoint' => 'userinfo']);
        respond_json(401, ['error' => 'invalid_token']);
    }

    respond_json(200, [
        'sub' => $identity['sub'] ?? '',
        'email' => $identity['email'] ?? '',
        'email_verified' => $identity['email_verified'] ?? false,
        'name' => $identity['name'] ?? '',
        'picture' => $identity['picture'] ?? '',
    ]);
}

respond_json(404, ['error' => 'not_found', 'path' => $path]);
