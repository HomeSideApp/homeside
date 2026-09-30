<?php

namespace App\Services\Contacts\Providers;

use App\Data\Contacts\ContactSyncCursorData;
use App\Data\Contacts\ContactSyncResultData;
use App\Data\Contacts\ExternalContactData;
use MStilkerich\CardDavClient\Services\SyncHandler;
use RuntimeException;
use Sabre\VObject\Component\VCard;

final class CardDavSyncHandler implements SyncHandler
{
    /** @var array<string, string> */
    private array $etags;

    /** @var list<ExternalContactData> */
    private array $changed = [];

    /** @var list<string> */
    private array $deleted = [];

    /** @param array<string, string> $etags */
    public function __construct(private string $collectionUrl, private VCardParser $parser, array $etags)
    {
        $this->etags = $etags;
    }

    public function addressObjectChanged(string $uri, string $etag, ?VCard $card): void
    {
        if ($card === null) {
            throw new RuntimeException('CardDAV did not return a changed vCard.');
        }

        $url = $this->absoluteUrl($uri);
        $contact = $this->parser->parse($card->serialize(), $url, $etag, $url);
        $this->changed[] = $contact;
        $this->etags[$url] = $etag;
        $this->deleted = array_values(array_diff($this->deleted, [$url]));
    }

    public function addressObjectDeleted(string $uri): void
    {
        $url = $this->absoluteUrl($uri);
        $this->deleted[] = $url;
        unset($this->etags[$url]);
    }

    public function getExistingVCardETags(): array
    {
        $existing = [];
        foreach ($this->etags as $url => $etag) {
            $path = parse_url($this->absoluteUrl($url), PHP_URL_PATH);
            if (is_string($path)) {
                $existing[$path] = $etag;
            }
        }

        return $existing;
    }

    public function finalizeSync(): void {}

    public function result(string $token, string $type): ContactSyncResultData
    {
        return new ContactSyncResultData(
            $this->changed,
            array_values(array_unique($this->deleted)),
            new ContactSyncCursorData($token === '' ? null : $token, $token === '' ? 'etag' : $type, ['etags' => $this->etags]),
        );
    }

    private function absoluteUrl(string $uri): string
    {
        $url = \Sabre\Uri\resolve($this->collectionUrl, $uri);
        $base = parse_url($this->collectionUrl);
        $target = parse_url($url);
        if (! is_array($base) || ! is_array($target)
            || strtolower($base['scheme'] ?? '') !== strtolower($target['scheme'] ?? '')
            || strtolower($base['host'] ?? '') !== strtolower($target['host'] ?? '')
            || ($base['port'] ?? null) !== ($target['port'] ?? null)) {
            throw new RuntimeException('CardDAV returned an out-of-origin URL.');
        }

        return $url;
    }
}
