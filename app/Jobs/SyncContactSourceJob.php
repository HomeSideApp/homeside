<?php

namespace App\Jobs;

use App\Actions\Contacts\DiscoverContactCollections;
use App\Models\ContactSource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class SyncContactSourceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public string $sourceId) {}

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('contact-source:'.$this->sourceId))->releaseAfter(60)->expireAfter(90)];
    }

    /**
     * Execute the job.
     */
    public function handle(DiscoverContactCollections $discover): void
    {
        $source = ContactSource::findOrFail($this->sourceId);
        if (! $source->enabled || ! $source->sync_enabled) {
            return;
        }

        $source->update(['last_sync_started_at' => now(), 'last_sync_status' => 'running', 'last_sync_error' => null]);
        foreach ($discover->execute($source)->where('enabled', true) as $collection) {
            SyncContactCollectionJob::dispatch($collection->id);
        }
    }

    public function failed(?Throwable $exception): void
    {
        ContactSource::find($this->sourceId)?->update(['last_sync_status' => 'failed', 'last_sync_error' => 'Contact source synchronization failed.']);
    }
}
