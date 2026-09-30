<?php

namespace App\Actions\Contacts;

use App\Models\ContactCollection;
use App\Models\ContactSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SetContactCollectionEnabled
{
    public function execute(ContactCollection $collection, bool $enabled): void
    {
        DB::transaction(function () use ($collection, $enabled): void {
            ContactSource::query()->whereKey($collection->contact_source_id)->lockForUpdate()->firstOrFail();
            $collection->refresh();
            if (! $enabled && $collection->enabled
                && $collection->source->collections()->where('enabled', true)->count() <= 1) {
                throw ValidationException::withMessages(['enabled' => 'Debe mantenerse al menos una libreta seleccionada.']);
            }

            $collection->update(['enabled' => $enabled]);
        });
    }
}
