<?php

namespace App\Models;

use Database\Factories\EconomicTransactionAttachmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class EconomicTransactionAttachment
 *
 * This class represents an image attached to an economic transaction as supporting evidence.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the attachment (UUID).
 * @property string $transaction_id The id of the transaction the attachment belongs to.
 * @property string $uploaded_by The id of the user who uploaded the attachment.
 * @property string $disk The disk where the attachment is stored.
 * @property string $path The path of the attachment on the disk.
 * @property string $original_filename The original filename of the attachment.
 * @property string $mime_type The MIME type of the attachment.
 * @property int $size The size of the attachment in bytes.
 * @property string $sha256 The SHA-256 hash of the attachment.
 * @property int $sort_order The ordering position inside the transaction gallery.
 * @property Carbon|null $created_at The timestamp when the attachment was created.
 * @property Carbon|null $updated_at The timestamp when the attachment was last updated.
 *
 * Relationships:
 * @property EconomicTransaction $transaction The transaction the attachment belongs to.
 * @property User|null $uploader The user who uploaded the attachment.
 *
 * @mixin Model
 */
class EconomicTransactionAttachment extends Model
{
    /** @use HasFactory<EconomicTransactionAttachmentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'transaction_id', 'uploaded_by', 'disk', 'path',
        'original_filename', 'mime_type', 'size', 'sha256', 'sort_order',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string> The model attribute cast definitions keyed by attribute name.
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The transaction the attachment belongs to.
     *
     * @return BelongsTo<EconomicTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(EconomicTransaction::class, 'transaction_id');
    }

    /**
     * The user who uploaded the attachment.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope the query to the attachments of a transaction in display order.
     *
     * @param  Builder<self>  $query  The incoming query builder.
     * @param  string  $transactionId  The transaction identifier.
     * @return Builder<self> The query scoped to the transaction attachments.
     */
    public function scopeForTransaction(Builder $query, string $transactionId): Builder
    {
        return $query->where('transaction_id', $transactionId)->orderBy('sort_order');
    }
}
