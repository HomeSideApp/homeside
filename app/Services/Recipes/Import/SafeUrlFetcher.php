<?php

namespace App\Services\Recipes\Import;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SafeUrlFetcher
{
    /** @var array<string> */
    private array $blockedHosts = [
        'localhost',
        '127.0.0.1',
        '::1',
        '0.0.0.0',
        '0000:0000:0000:0000:0000:0000:0000:0001',
    ];

    /**
     * Fetch a URL safely, blocking internal/private/reserved IP ranges.
     *
     * @throws \Exception If the URL is not safe to fetch
     */
    public function fetch(string $url, int $maxBytes = 5242880): string
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            throw new \Exception('Invalid URL format.');
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new \Exception('Only HTTP and HTTPS URLs are allowed.');
        }

        $host = $parsed['host'] ?? '';
        if (empty($host)) {
            throw new \Exception('URL has no host.');
        }

        if (in_array(strtolower($host), $this->blockedHosts, true)) {
            throw new \Exception('Access to localhost is blocked.');
        }

        try {
            $response = Http::safeUrl()->get($url)->throw();
        } catch (RequestException $e) {
            Log::warning('SafeUrlFetcher: HTTP request failed', [
                'url' => $url,
                'status' => $e->response->status(),
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to fetch URL: HTTP '.$e->response->status());
        } catch (ConnectionException $e) {
            Log::warning('SafeUrlFetcher: Connection failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to fetch URL: '.$e->getMessage());
        } catch (\Exception $e) {
            Log::warning('SafeUrlFetcher: Request failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to fetch URL: '.$e->getMessage());
        }

        $body = $response->body();
        if (mb_strlen($body, 'UTF-8') > $maxBytes) {
            throw new \Exception('Response exceeds maximum size.');
        }

        return $body;
    }
}
