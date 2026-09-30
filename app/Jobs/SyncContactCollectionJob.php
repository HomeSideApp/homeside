<?php

namespace App\Jobs;

use App\Actions\Contacts\SyncContactCollection;
use App\Models\ContactCollection;
use App\Models\ContactSource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class SyncContactCollectionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public string $collectionId) {}

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('contact-collection:'.$this->collectionId))->releaseAfter(60)->expireAfter(330)];
    }

    /**
     * Execute the job.
     */
    public function handle(SyncContactCollection $sync): void
    {
        $collection = ContactCollection::with('source', 'syncState')->findOrFail($this->collectionId);
        $hasMore = $sync->execute($collection);
        if ($hasMore) {
            self::dispatch($collection->id)->delay(now()->addSeconds(2));

            return;
        }
        $collection->source->update(['last_synced_at' => now(), 'last_sync_status' => 'completed', 'last_sync_error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        $collection = ContactCollection::find($this->collectionId);
        if ($collection !== null) {
            $source = ContactSource::find($collection->contact_source_id);
            if ($source !== null && $source->last_sync_status !== 'reconnect_required') {
                $source->update(['last_sync_status' => 'failed', 'last_sync_error' => 'Contact collection synchronization failed.']);
            }
        }
    }
}
