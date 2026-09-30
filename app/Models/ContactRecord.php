<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactRecord extends Model
{
    use HasUuids;

    protected $fillable = ['contact_id', 'contact_source_id', 'source_order', 'remote_id', 'remote_uid', 'remote_href', 'remote_version', 'remote_etag', 'formatted_name', 'given_name', 'family_name', 'additional_name', 'nickname', 'organization', 'job_title', 'birthday', 'notes', 'remote_created_at', 'remote_updated_at', 'remote_deleted_at', 'provider_metadata'];

    protected function casts(): array
    {
        return ['birthday' => 'date', 'remote_created_at' => 'datetime', 'remote_updated_at' => 'datetime', 'remote_deleted_at' => 'datetime', 'provider_metadata' => 'array'];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<ContactSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(ContactSource::class, 'contact_source_id');
    }

    /** @return BelongsToMany<ContactCollection, $this> */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(ContactCollection::class, 'contact_collection_members');
    }
}
