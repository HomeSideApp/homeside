<?php

namespace Tests\Fixtures;

use App\Services\Recipes\Import\SafeUrlFetcher;

class FakeSafeUrlFetcher extends SafeUrlFetcher
{
    /** @var array<string, string> URL → HTML content */
    private array $responses = [];

    private ?string $error = null;

    /**
     * Configure a URL to return specific HTML content.
     */
    public function whenUrl(string $url, string $html): self
    {
        $this->responses[$url] = $html;

        return $this;
    }

    /**
     * Configure the fetcher to throw an error for all URLs.
     */
    public function whenFail(string $message = 'Connection failed'): self
    {
        $this->error = $message;

        return $this;
    }

    public function fetch(string $url, int $maxBytes = 5242880): string
    {
        if ($this->error !== null) {
            throw new \Exception($this->error);
        }

        foreach ($this->responses as $pattern => $html) {
            if (str_starts_with($url, $pattern)) {
                return $html;
            }
        }

        throw new \Exception("No fake response configured for URL: {$url}");
    }
}
