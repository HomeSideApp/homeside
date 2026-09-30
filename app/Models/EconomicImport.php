<?php

namespace App\Models;

use App\Enums\EconomicImportStatus;
use Database\Factories\EconomicImportFactory;
use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class EconomicImport
 *
 * This class represents an import job closed over an economic document, tracking its analysis
 * and review lifecycle.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the import (UUID).
 * @property string|null $household_id The id of the household the import belongs to, if any.
 * @property string $document_id The id of the economic document being imported.
 * @property string $created_by The id of the user who created the import.
 * @property string|null $ai_run_id The id of the AI run used to analyze the document, if any.
 * @property EconomicImportStatus $status The status of the import lifecycle.
 * @property array<string, bool> $requested_sections The extraction sections keyed by section name.
 * @property array<string, mixed>|null $extracted_payload The data extracted by the AI analysis, if any.
 * @property array<string, mixed>|null $review_payload The data reviewed by the user, if any.
 * @property string|null $error_code The error code returned by the analysis, if any.
 * @property string|null $error_message The error message returned by the analysis, if any.
 * @property Carbon|null $started_at The timestamp when the analysis started, if any.
 * @property Carbon|null $finished_at The timestamp when the analysis finished, if any.
 * @property Carbon|null $confirmed_at The timestamp when the import was confirmed, if any.
 * @property Carbon|null $created_at The timestamp when the import was created.
 * @property Carbon|null $updated_at The timestamp when the import was last updated.
 *
 * Relationships:
 * @property Household|null $household The household the import belongs to, if any.
 * @property EconomicDocument|null $document The economic document being imported.
 * @property User|null $creator The user who created the import.
 * @property AiRun|null $aiRun The AI run used to analyze the document, if any.
 *
 * @mixin Model
 */
class EconomicImport extends Model
{
    /** @use HasFactory<EconomicImportFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'household_id', 'document_id', 'created_by', 'ai_run_id',
        'status', 'requested_sections', 'extracted_payload', 'review_payload',
        'error_code', 'error_message', 'started_at', 'finished_at', 'confirmed_at',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EconomicImportStatus::class,
            'requested_sections' => 'array',
            'extracted_payload' => 'array',
            'review_payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return BelongsTo<EconomicDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(EconomicDocument::class, 'document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<AiRun, $this> */
    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }
}
