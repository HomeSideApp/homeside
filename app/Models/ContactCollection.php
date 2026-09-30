<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContactCollection extends Model
{
    use HasUuids;

    protected $fillable = ['contact_source_id', 'remote_id', 'remote_href', 'name', 'description', 'type', 'enabled', 'read_only', 'provider_metadata', 'last_synced_at'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'read_only' => 'boolean', 'provider_metadata' => 'array', 'last_synced_at' => 'datetime'];
    }

    /** @return BelongsTo<ContactSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(ContactSource::class, 'contact_source_id');
    }

    /** @return BelongsToMany<ContactRecord, $this> */
    public function records(): BelongsToMany
    {
        return $this->belongsToMany(ContactRecord::class, 'contact_collection_members');
    }

    /** @return HasOne<ContactSyncState, $this> */
    public function syncState(): HasOne
    {
        return $this->hasOne(ContactSyncState::class);
    }
}
