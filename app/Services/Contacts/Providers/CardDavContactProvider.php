<?php

namespace App\Services\Contacts\Providers;

use App\Contracts\Contacts\ContactProvider;
use App\Data\Contacts\ContactProviderCapabilitiesData;
use App\Data\Contacts\ContactSyncCursorData;
use App\Data\Contacts\ContactSyncResultData;
use App\Data\Contacts\ExternalCollectionData;
use App\Models\ContactCollection;
use App\Models\ContactSource;
use App\Services\Contacts\SafeRemoteConnectionClient;
use GuzzleHttp\Psr7\HttpFactory;
use MStilkerich\CardDavClient\Account;
use MStilkerich\CardDavClient\AddressbookCollection;
use MStilkerich\CardDavClient\Config;
use MStilkerich\CardDavClient\Psr18TransportOptions;
use MStilkerich\CardDavClient\Services\Discovery;
use MStilkerich\CardDavClient\Services\DiscoveryOptions;
use MStilkerich\CardDavClient\Services\Sync;
use RuntimeException;

final class CardDavContactProvider implements ContactProvider
{
    public function __construct(
        private SafeRemoteConnectionClient $client,
        private VCardParser $parser,
        private Discovery $discovery,
        private Sync $sync,
    ) {}

    public function capabilities(): ContactProviderCapabilitiesData
    {
        return new ContactProviderCapabilitiesData(true, false, true, true, false, true, true);
    }

    public function collections(ContactSource $source): array
    {
        $account = $this->account($source);
        $books = $this->discovery->discoverAddressbooks($account, new DiscoveryOptions(false, false, true, false));

        return array_map(function (AddressbookCollection $book) use ($account): ExternalCollectionData {
            $url = $this->collectionUrl($account->getDiscoveryUri(), $book->getUri());

            return new ExternalCollectionData($url, $book->getName(), $url, true);
        }, $books);
    }

    public function discoverServerUrl(string $input, string $username, string $password): string
    {
        $serverUrl = $this->normalizeDiscoveryUrl($input);
        $source = new ContactSource([
            'provider' => 'carddav',
            'provider_configuration' => ['server_url' => $serverUrl],
            'encrypted_credentials' => ['username' => $username, 'password' => $password],
        ]);

        $this->discovery->discoverAddressbooks($this->account($source), new DiscoveryOptions(false, false, true, false));

        return $serverUrl;
    }

    private function normalizeDiscoveryUrl(string $input): string
    {
        $input = trim($input);
        $url = str_contains($input, '://') ? $input : 'https://'.$input;
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || ! (filter_var($parts['host'], FILTER_VALIDATE_IP) || filter_var($parts['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))) {
            throw new RuntimeException('Introduce un dominio o una URL de servidor válidos.');
        }

        $scheme = strtolower($parts['scheme']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.strtolower($parts['host']).$port.($parts['path'] ?? '');
    }

    public function synchronize(ContactCollection $collection, ?ContactSyncCursorData $cursor): ContactSyncResultData
    {
        $url = $collection->remote_href;
        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Contact collection has no remote URL.');
        }

        $source = $collection->source;
        if ($source === null) {
            throw new RuntimeException('Contact collection has no source.');
        }

        $account = $this->account($source);
        $url = $this->collectionUrl($account->getDiscoveryUri(), $url);
        $book = new AddressbookCollection($url, $account);
        $handler = new CardDavSyncHandler($url, $this->parser, $cursor?->state['etags'] ?? []);
        $token = $this->sync->synchronize($book, $handler, [], $cursor->value ?? '');

        return $handler->result($token, 'dav-sync');
    }

    private function account(ContactSource $source): Account
    {
        $configuration = $source->getAttribute('provider_configuration');
        $credentials = $source->getAttribute('encrypted_credentials');
        $url = is_array($configuration) ? ($configuration['server_url'] ?? null) : null;
        $username = is_array($credentials) ? ($credentials['username'] ?? null) : null;
        $password = is_array($credentials) ? ($credentials['password'] ?? null) : null;

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('CardDAV server URL is missing.');
        }
        if (! is_string($username) || ! is_string($password)) {
            throw new RuntimeException('CardDAV credentials are missing.');
        }

        Config::init();

        $origin = $this->origin($url);
        $account = new Account($url, [
            'username' => $username,
            'password' => $password,
            'preemptive_basic_auth' => true,
        ], '', $origin);
        $factory = new HttpFactory;

        return $account->withHttpClient(
            $this->client->forOrigin($url),
            $factory,
            $factory,
            new Psr18TransportOptions(3),
        );
    }

    private function collectionUrl(string $serverUrl, string $href): string
    {
        $url = \Sabre\Uri\resolve($serverUrl, $href);
        if ($this->origin($url) !== $this->origin($serverUrl)) {
            throw new RuntimeException('CardDAV returned an out-of-origin URL.');
        }

        return $url;
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('Invalid CardDAV URL.');
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('Invalid CardDAV URL.');
        }

        return $scheme.'://'.strtolower($parts['host']).':'.($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
    }
}
