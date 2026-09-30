<?php

namespace Tests\Feature;

use App\Data\Contacts\ContactSyncCursorData;
use App\Models\ContactCollection;
use App\Models\ContactSource;
use App\Services\Contacts\ContactTransportException;
use App\Services\Contacts\Providers\CardDavContactProvider;
use App\Services\Contacts\Providers\CardDavSyncHandler;
use App\Services\Contacts\Providers\VCardParser;
use App\Services\Contacts\SafeRemoteConnectionClient;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use MStilkerich\CardDavClient\Account;
use MStilkerich\CardDavClient\Config;
use MStilkerich\CardDavClient\Services\Discovery;
use MStilkerich\CardDavClient\Services\DiscoveryOptions;
use MStilkerich\CardDavClient\Services\Sync;
use MStilkerich\CardDavClient\Services\SyncHandler;
use Psr\Log\NullLogger;
use RuntimeException;
use Sabre\VObject\Reader;
use Tests\TestCase;

class CardDavIntegrationTest extends TestCase
{
    public function test_psr18_client_sends_request_through_laravel_and_rejects_other_origins(): void
    {
        Http::fake(['*' => Http::response('card', 200, ['ETag' => '"1"'])]);
        $factory = new HttpFactory;
        $client = (new SafeRemoteConnectionClient)->forOrigin('https://93.184.216.34/dav');
        $request = $factory->createRequest('GET', 'https://93.184.216.34/dav/ada.vcf')
            ->withHeader('Authorization', 'Basic '.base64_encode('ada:secret'));

        $response = $client->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('card', (string) $response->getBody());
        $this->assertSame('"1"', $response->getHeaderLine('ETag'));
        Http::assertSent(fn (Request $sent): bool => $sent->method() === 'GET'
            && $sent->url() === 'https://93.184.216.34/dav/ada.vcf'
            && $sent->header('Authorization')[0] === 'Basic '.base64_encode('ada:secret'));

        try {
            $client->sendRequest($factory->createRequest('GET', 'https://93.184.216.35/dav/ada.vcf'));
            $this->fail('A different origin was accepted.');
        } catch (ContactTransportException $exception) {
            $this->assertSame('Contact server redirected to another origin.', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_psr18_client_retries_a_read_after_a_connection_failure(): void
    {
        $attempts = 0;
        Http::fake(function (Request $request) use (&$attempts) {
            $attempts++;

            return $attempts === 1 ? Http::failedConnection('Connection timed out') : Http::response('card', 200);
        });
        $request = (new HttpFactory)->createRequest('GET', 'https://93.184.216.34/dav/ada.vcf');

        $response = (new SafeRemoteConnectionClient)->forOrigin('https://93.184.216.34/dav')->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('card', (string) $response->getBody());
        Http::assertSentCount(2);
    }

    public function test_psr18_client_does_not_retry_failed_write_requests(): void
    {
        Http::fake(Http::failedConnection('Connection timed out'));
        $request = (new HttpFactory)->createRequest('PUT', 'https://93.184.216.34/dav/ada.vcf');

        $this->expectException(ContactTransportException::class);
        try {
            (new SafeRemoteConnectionClient)->forOrigin('https://93.184.216.34/dav')->sendRequest($request);
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_psr18_client_streams_a_real_response_with_pinned_curl_transport(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required for the local HTTP server.');
        }

        $host = gethostbyname(gethostname());
        if (! filter_var($host, FILTER_VALIDATE_IP) || str_starts_with($host, '127.')) {
            $this->markTestSkipped('A non-loopback local address is required for this transport test.');
        }

        $server = stream_socket_server('tcp://'.$host.':0', $errorCode, $errorMessage);
        $this->assertNotFalse($server, $errorMessage);
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
        $child = pcntl_fork();
        $this->assertNotSame(-1, $child);

        if ($child === 0) {
            $connection = stream_socket_accept($server, 5);
            if (is_resource($connection)) {
                fread($connection, 8192);
                fwrite($connection, "HTTP/1.1 207 Multi-Status\r\nContent-Length: 2\r\nConnection: close\r\n\r\nok");
                fclose($connection);
            }
            fclose($server);
            exit(0);
        }

        try {
            config()->set('contacts.allow_private_networks', true);
            $url = 'http://'.$host.':'.$port.'/dav';
            $response = (new SafeRemoteConnectionClient)->forOrigin($url)
                ->sendRequest((new HttpFactory)->createRequest('GET', $url));

            $this->assertSame(207, $response->getStatusCode());
            $this->assertSame('ok', (string) $response->getBody());
        } finally {
            fclose($server);
            pcntl_waitpid($child, $status);
        }
    }

    public function test_psr18_client_rejects_oversized_responses(): void
    {
        config()->set('contacts.max_response_bytes', 3);
        Http::fake(['*' => Http::response('long', 200)]);
        $request = (new HttpFactory)->createRequest('GET', 'https://93.184.216.34/dav');

        $this->expectException(ContactTransportException::class);
        $this->expectExceptionMessage('Contact server response exceeds size limit.');
        (new SafeRemoteConnectionClient)->forOrigin('https://93.184.216.34/dav')->sendRequest($request);
    }

    public function test_provider_discovers_only_configured_origin_through_injected_transport(): void
    {
        Http::fake(['*' => Http::response('reachable', 200)]);
        $discovery = $this->createMock(Discovery::class);
        $discovery->expects($this->once())->method('discoverAddressbooks')
            ->willReturnCallback(function (Account $account, DiscoveryOptions $options): array {
                $this->assertFalse($options->useDnsSrv);
                $this->assertFalse($options->useDnsTxt);
                $this->assertTrue($options->useWellKnown);
                $this->assertFalse($options->useKnownServers);
                $this->assertSame('reachable', (string) $account->getClient()->getResource('/health')->getBody());

                return [];
            });
        $provider = new CardDavContactProvider(
            new SafeRemoteConnectionClient,
            new VCardParser,
            $discovery,
            $this->createMock(Sync::class),
        );

        $this->assertSame([], $provider->collections($this->source()));
        Http::assertSentCount(1);
    }

    public function test_real_discovery_initializes_the_package_logger(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $previousLogger = Config::$logger;
        $previousHttpLogger = Config::$httplogger;
        Config::$logger = null;
        Config::$httplogger = null;
        $provider = new CardDavContactProvider(
            new SafeRemoteConnectionClient,
            new VCardParser,
            new Discovery,
            $this->createMock(Sync::class),
        );

        try {
            try {
                $provider->collections($this->source());
                $this->fail('Discovery unexpectedly succeeded.');
            } catch (\Exception $exception) {
                $this->assertSame('Could not determine the addressbook home', $exception->getMessage());
            }
            $this->assertInstanceOf(NullLogger::class, Config::$logger);
        } finally {
            Config::init($previousLogger, $previousHttpLogger);
        }
    }

    public function test_package_redirects_stay_inside_the_configured_origin(): void
    {
        Http::fakeSequence()
            ->push('', 302, ['Location' => '/final'])
            ->push('card', 200);
        $discovery = $this->createMock(Discovery::class);
        $discovery->method('discoverAddressbooks')
            ->willReturnCallback(function (Account $account): array {
                $this->assertSame('card', (string) $account->getClient()->getResource('/start')->getBody());

                return [];
            });
        $provider = new CardDavContactProvider(
            new SafeRemoteConnectionClient,
            new VCardParser,
            $discovery,
            $this->createMock(Sync::class),
        );

        $this->assertSame([], $provider->collections($this->source()));
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $sent): bool => $sent->url() === 'https://93.184.216.34/final'
            && $sent->header('Authorization')[0] === 'Basic '.base64_encode('ada:secret'));
    }

    public function test_package_redirect_to_another_origin_is_rejected_before_request(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://93.184.216.35/other'])]);
        $discovery = $this->createMock(Discovery::class);
        $discovery->method('discoverAddressbooks')
            ->willReturnCallback(function (Account $account): array {
                $account->getClient()->getResource('/start');

                return [];
            });
        $provider = new CardDavContactProvider(
            new SafeRemoteConnectionClient,
            new VCardParser,
            $discovery,
            $this->createMock(Sync::class),
        );

        try {
            $provider->collections($this->source());
            $this->fail('A cross-origin redirect was accepted.');
        } catch (ContactTransportException $exception) {
            $this->assertSame('Contact server redirected to another origin.', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_provider_preserves_etags_and_translates_package_sync_events(): void
    {
        $sync = $this->createMock(Sync::class);
        $sync->expects($this->once())->method('synchronize')
            ->willReturnCallback(function ($book, SyncHandler $handler, array $properties, string $token): string {
                $this->assertSame('old-token', $token);
                $this->assertSame(['/dav/old.vcf' => '"old"'], $handler->getExistingVCardETags());
                $handler->addressObjectDeleted('/dav/old.vcf');
                $handler->addressObjectChanged('/dav/ada.vcf', '"new"', Reader::read("BEGIN:VCARD\r\nVERSION:3.0\r\nUID:ada\r\nFN:Ada Lovelace\r\nEND:VCARD\r\n"));
                $handler->finalizeSync();

                return 'new-token';
            });
        $provider = new CardDavContactProvider(
            new SafeRemoteConnectionClient,
            new VCardParser,
            $this->createMock(Discovery::class),
            $sync,
        );
        $collection = new ContactCollection(['remote_href' => 'https://93.184.216.34/dav/']);
        $collection->setRelation('source', $this->source());
        $cursor = new ContactSyncCursorData('old-token', 'dav-sync', [
            'etags' => ['https://93.184.216.34/dav/old.vcf' => '"old"'],
        ]);

        $result = $provider->synchronize($collection, $cursor);

        $this->assertSame(['https://93.184.216.34/dav/old.vcf'], $result->deletedRemoteIds);
        $this->assertSame('Ada Lovelace', $result->contacts[0]->formattedName);
        $this->assertSame('https://93.184.216.34/dav/ada.vcf', $result->contacts[0]->remoteId);
        $this->assertSame('new-token', $result->nextCursor->value);
        $this->assertSame(['https://93.184.216.34/dav/ada.vcf' => '"new"'], $result->nextCursor->state['etags']);
    }

    public function test_missing_changed_vcard_stops_sync_before_cursor_is_saved(): void
    {
        $handler = new CardDavSyncHandler('https://93.184.216.34/dav/', new VCardParser, []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CardDAV did not return a changed vCard.');
        $handler->addressObjectChanged('/dav/ada.vcf', '"new"', null);
    }

    private function source(): ContactSource
    {
        return new ContactSource([
            'provider' => 'carddav',
            'provider_configuration' => ['server_url' => 'https://93.184.216.34/dav/'],
            'encrypted_credentials' => ['username' => 'ada', 'password' => 'secret'],
        ]);
    }
}
