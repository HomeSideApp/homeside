<?php

namespace App\Contracts\Contacts;

use App\Data\Contacts\ContactProviderCapabilitiesData;
use App\Data\Contacts\ContactSyncCursorData;
use App\Data\Contacts\ContactSyncResultData;
use App\Data\Contacts\ExternalCollectionData;
use App\Models\ContactCollection;
use App\Models\ContactSource;

interface ContactProvider
{
    public function capabilities(): ContactProviderCapabilitiesData;

    /** @return list<ExternalCollectionData> */
    public function collections(ContactSource $source): array;

    public function synchronize(ContactCollection $collection, ?ContactSyncCursorData $cursor): ContactSyncResultData;
}
