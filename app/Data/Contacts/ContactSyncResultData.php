<?php

namespace App\Data\Contacts;

final readonly class ContactSyncResultData
{
    /**
     * @param  list<ExternalContactData>  $contacts
     * @param  list<string>  $deletedRemoteIds
     */
    public function __construct(
        public array $contacts,
        public array $deletedRemoteIds,
        public ContactSyncCursorData $nextCursor,
        public bool $requiresFullSync = false,
        public bool $hasMore = false,
        public ?string $fullRunId = null,
        public bool $fullRunComplete = false,
    ) {}
}
