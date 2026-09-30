<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property array<string, mixed>|null $provider_state */
class ContactSyncState extends Model
{
    protected $fillable = ['contact_source_id', 'contact_collection_id', 'cursor', 'cursor_type', 'provider_state', 'last_full_sync_at', 'last_incremental_sync_at'];

    protected function casts(): array
    {
        return ['provider_state' => 'array', 'last_full_sync_at' => 'datetime', 'last_incremental_sync_at' => 'datetime'];
    }

    /** @return BelongsTo<ContactCollection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(ContactCollection::class, 'contact_collection_id');
    }
}
