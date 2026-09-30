<?php

namespace App\Services\Contacts;

use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class SafeRemoteConnectionClient implements ClientInterface
{
    private ?string $allowedOrigin = null;

    public function forOrigin(string $url): self
    {
        $this->assertSafeUrl($url);
        $client = clone $this;
        $client->allowedOrigin = $this->origin($url);

        return $client;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $url = (string) $request->getUri();

        try {
            if (! defined('CURLOPT_RESOLVE') || ! function_exists('curl_exec')) {
                throw new RuntimeException('The cURL extension is required for contact connections.');
            }

            if ($this->allowedOrigin !== null && $this->origin($url) !== $this->allowedOrigin) {
                throw new RuntimeException('Contact server redirected to another origin.');
            }

            [$host, $port, $ip] = $this->resolveSafeTarget($url);
            $pinnedIp = str_contains($ip, ':') ? '['.$ip.']' : $ip;
            $response = Http::withHeaders($request->getHeaders())
                ->setHandler(new CurlHandler)
                ->connectTimeout(5)
                ->timeout(20)
                ->retry(3, 250, static fn (?\Throwable $exception, PendingRequest $pendingRequest, ?string $method): bool => $exception instanceof ConnectionException
                    && in_array(strtoupper($method ?? ''), ['GET', 'HEAD', 'OPTIONS', 'PROPFIND', 'REPORT'], true), throw: false)
                ->withOptions([
                    'allow_redirects' => false,
                    'stream' => true,
                    'proxy' => '',
                    'curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.$pinnedIp]],
                ])
                ->send($request->getMethod(), $url, ['body' => (string) $request->getBody()]);

            $stream = $response->toPsrResponse()->getBody();
            $content = '';
            $limit = (int) config('contacts.max_response_bytes', 5242880);

            while (true) {
                $chunk = $stream->read(min(8192, $limit + 1 - strlen($content)));
                if ($chunk === '') {
                    if ($stream->eof()) {
                        break;
                    }

                    throw new RuntimeException('Contact server response stream stopped unexpectedly.');
                }

                $content .= $chunk;
                if (strlen($content) > $limit) {
                    throw new RuntimeException('Contact server response exceeds size limit.');
                }
            }

            return $response->toPsrResponse()->withBody(Utils::streamFor($content));
        } catch (ConnectionException|RuntimeException $exception) {
            throw new ContactTransportException($request, $exception->getMessage(), $exception);
        }
    }

    public function assertSafeUrl(string $url): void
    {
        $this->resolveSafeTarget($url);
    }

    /** @return array{string, int, string} */
    private function resolveSafeTarget(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Invalid contact server URL.');
        }

        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? (($parts['scheme'] === 'https') ? 443 : 80);
        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : [];

        if ($addresses === []) {
            foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
                $address = $record['ip'] ?? $record['ipv6'] ?? null;
                if (is_string($address)) {
                    $addresses[] = $address;
                }
            }
        }

        if ($addresses === []) {
            throw new RuntimeException('Contact server hostname could not be resolved.');
        }

        foreach ($addresses as $address) {
            if ($this->blockedAddress($address)) {
                throw new RuntimeException('Contact server address is not permitted.');
            }
        }

        return [$host, $port, $addresses[0]];
    }

    private function blockedAddress(string $address): bool
    {
        if (in_array($address, ['127.0.0.1', '::1', '169.254.169.254'], true)
            || str_starts_with($address, '127.') || str_starts_with($address, '169.254.')
            || str_starts_with(strtolower($address), 'fe80:')) {
            return true;
        }

        if (config('contacts.allow_private_networks')) {
            return false;
        }

        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);

        return strtolower(($parts['scheme'] ?? '').'://'.($parts['host'] ?? '').':'.($parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80)));
    }
}
