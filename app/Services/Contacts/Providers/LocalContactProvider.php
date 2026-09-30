<?php

namespace App\Services\Contacts\Providers;

use App\Contracts\Contacts\ContactProvider;
use App\Data\Contacts\ContactProviderCapabilitiesData;
use App\Data\Contacts\ContactSyncCursorData;
use App\Data\Contacts\ContactSyncResultData;
use App\Models\ContactCollection;
use App\Models\ContactSource;

final class LocalContactProvider implements ContactProvider
{
    public function capabilities(): ContactProviderCapabilitiesData
    {
        return new ContactProviderCapabilitiesData(true, true, false, false, false, true, true);
    }

    public function collections(ContactSource $source): array
    {
        return [];
    }

    public function synchronize(ContactCollection $collection, ?ContactSyncCursorData $cursor): ContactSyncResultData
    {
        return new ContactSyncResultData([], [], new ContactSyncCursorData(null, null));
    }
}
