<?php

namespace App\Actions\Contacts;

use App\Data\Contacts\ContactSyncCursorData;
use App\Models\ContactCollection;
use App\Models\ContactRecord;
use App\Services\Contacts\ContactProviderRegistry;
use Illuminate\Support\Facades\DB;

final class SyncContactCollection
{
    public function __construct(private ContactProviderRegistry $registry, private ImportExternalContact $import) {}

    public function execute(ContactCollection $collection): bool
    {
        $source = $collection->source;
        if (! $collection->enabled || $source === null || ! $source->enabled || ! $source->sync_enabled) {
            return false;
        }

        $state = $collection->syncState;
        $cursor = $state === null ? null : new ContactSyncCursorData($state->cursor, $state->cursor_type, $state->provider_state ?? []);
        $result = $this->registry->for($source)->synchronize($collection, $cursor);
        if ($result->requiresFullSync) {
            $result = $this->registry->for($source)->synchronize($collection, null);
        }

        foreach ($result->contacts as $external) {
            $record = $this->import->execute($source, $external);
            $record->collections()->syncWithoutDetaching([$collection->id]);
            if ($result->fullRunId !== null) {
                $record->forceFill(['last_seen_full_sync_id' => $result->fullRunId])->save();
            }
        }

        DB::transaction(function () use ($collection, $result): void {
            if ($result->deletedRemoteIds !== []) {
                $deletedRecords = ContactRecord::query()
                    ->where('contact_source_id', $collection->contact_source_id)
                    ->whereIn('remote_id', $result->deletedRemoteIds)
                    ->whereHas('collections', fn ($query) => $query->whereKey($collection->id))
                    ->get();
                foreach ($deletedRecords as $record) {
                    $record->update(['remote_deleted_at' => now()]);
                    if ($record->contact !== null) {
                        $this->import->clearImportedLabels($record->contact);
                    }
                }
            }

            if ($result->fullRunComplete && $result->fullRunId !== null) {
                $missing = ContactRecord::query()
                    ->where('contact_source_id', $collection->contact_source_id)
                    ->whereHas('collections', fn ($query) => $query->whereKey($collection->id))
                    ->whereNull('remote_deleted_at')
                    ->where(function ($query) use ($result): void {
                        $query->whereNull('last_seen_full_sync_id')
                            ->orWhere('last_seen_full_sync_id', '!=', $result->fullRunId);
                    })->get();
                foreach ($missing as $record) {
                    $record->update(['remote_deleted_at' => now()]);
                    if ($record->contact !== null) {
                        $this->import->clearImportedLabels($record->contact);
                    }
                }
            }

            $collection->syncState()->updateOrCreate(
                ['contact_collection_id' => $collection->id],
                [
                    'contact_source_id' => $collection->contact_source_id,
                    'cursor' => $result->nextCursor->value,
                    'cursor_type' => $result->nextCursor->type,
                    'provider_state' => $result->nextCursor->state,
                    'last_full_sync_at' => $result->fullRunComplete || ($result->fullRunId === null && ($result->requiresFullSync || $collection->syncState === null)) ? now() : $collection->syncState?->last_full_sync_at,
                    'last_incremental_sync_at' => now(),
                ],
            );
            $collection->update(['last_synced_at' => now()]);
        });

        return $result->hasMore;
    }
}
