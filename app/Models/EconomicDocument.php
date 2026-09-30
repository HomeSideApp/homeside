<?php

namespace App\Models;

use Database\Factories\EconomicDocumentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class EconomicDocument
 *
 * This class represents a document (e.g. a receipt) uploaded for economic analysis.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the document (UUID).
 * @property string|null $household_id The id of the household the document belongs to, if any.
 * @property string $uploaded_by The id of the user who uploaded the document.
 * @property string $disk The disk where the document is stored.
 * @property string $path The path of the document on the disk.
 * @property string $original_filename The original filename of the document.
 * @property string $mime_type The MIME type of the document.
 * @property int $size The size of the document in bytes.
 * @property string $sha256 The SHA-256 hash of the document.
 * @property string|null $ai_path The path of the document used for AI analysis, if any.
 * @property array<string, mixed>|null $processing_meta The adjustments applied to the AI version, if any.
 * @property Carbon|null $created_at The timestamp when the document was created.
 * @property Carbon|null $updated_at The timestamp when the document was last updated.
 *
 * Relationships:
 * @property Household|null $household The household the document belongs to, if any.
 * @property User|null $uploader The user who uploaded the document.
 * @property Collection<int, EconomicImport> $imports The imports created from this document.
 * @property Collection<int, EconomicTransaction> $transactions The transactions created from this document.
 *
 * @mixin Model
 */
class EconomicDocument extends Model
{
    /** @use HasFactory<EconomicDocumentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'household_id', 'uploaded_by', 'disk', 'path',
        'original_filename', 'mime_type', 'size', 'sha256', 'ai_path', 'processing_meta',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'processing_meta' => 'array',
        ];
    }

    /**
     * The household the document belongs to, if any.
     *
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * The user who uploaded the document.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The imports created from this document.
     *
     * @return HasMany<EconomicImport, $this>
     */
    public function imports(): HasMany
    {
        return $this->hasMany(EconomicImport::class, 'document_id');
    }

    /**
     * The transactions created from this document.
     *
     * @return HasMany<EconomicTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(EconomicTransaction::class, 'source_document_id');
    }
}
