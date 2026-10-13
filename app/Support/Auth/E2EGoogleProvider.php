<?php

namespace App\Support\Auth;

use GuzzleHttp\RequestOptions;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\GoogleProvider;

/**
 * The real Google OAuth2 provider with only its three endpoints moved.
 *
 * It extends the framework's own `GoogleProvider` and overrides exactly three
 * URLs -- the authorization URL, the token URL and the userinfo URL -- so the
 * whole exchange remains Socialite's real implementation:
 *
 *  - `redirect()` still writes the `state` into the session;
 *  - `user()` still validates the round-tripped `state` (`hasInvalidState()`),
 *    still POSTs the authorization code to the token endpoint and still maps
 *    the userinfo response through the real `mapUserToObject()`.
 *
 * Nothing about the flow is stubbed; only the host that answers those three
 * requests changes, and only for the E2E environment (see E2EGoogleSimulator).
 */
final class E2EGoogleProvider extends GoogleProvider
{
    /**
     * Create the provider.
     *
     * @param  Request  $request  The incoming request.
     * @param  string  $clientId  The OAuth client id.
     * @param  string  $clientSecret  The OAuth client secret.
     * @param  string  $redirectUrl  The registered callback URL.
     * @param  array<string, mixed>  $guzzle  Guzzle client options.
     * @param  string  $simulatorBaseUrl  The simulator base URL, without a trailing slash.
     */
    public function __construct(
        Request $request,
        string $clientId,
        string $clientSecret,
        string $redirectUrl,
        array $guzzle = [],
        private readonly string $simulatorBaseUrl = '',
    ) {
        parent::__construct($request, $clientId, $clientSecret, $redirectUrl, $guzzle);
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->endpoint('/oauth2/auth'), $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl(): string
    {
        return $this->endpoint('/oauth2/token');
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->endpoint('/oauth2/userinfo'), [
            RequestOptions::QUERY => [
                'prettyPrint' => 'false',
            ],
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * Build an absolute simulator URL for an endpoint path.
     *
     * @param  string  $path  The endpoint path, including the leading slash.
     * @return string The absolute URL.
     */
    private function endpoint(string $path): string
    {
        return $this->simulatorBaseUrl.$path;
    }
}
