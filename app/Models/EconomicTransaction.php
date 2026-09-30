<?php

namespace App\Models;

use App\Enums\RecurrenceFrequency;
use App\Enums\TransactionScope;
use App\Enums\TransactionType;
use Carbon\CarbonInterface;
use Database\Factories\EconomicTransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Class EconomicTransaction
 *
 * This class represents an economic transaction, either personal or shared withina household.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the transaction (UUID).
 * @property string|null $household_id The id of the household the transaction belongs to, if any.
 * @property string $created_by The id of the user who created the transaction.
 * @property TransactionType $type The type of the transaction.
 * @property TransactionScope $scope The scope of the transaction (personal or household).
 * @property string $title The title of the transaction.
 * @property int $amount_minor The transaction amount in minor currency units.
 * @property string $currency The currency code of the transaction.
 * @property string|null $place The place where the transaction occurred, if any.
 * @property Carbon|null $occurred_at The timestamp when the transaction occurred, if any.
 * @property string|null $account_id The id of the private account that funded the transaction, if any.
 * @property string|null $source_document_id The id of the document the transaction was created from, if any.
 * @property string|null $notes Additional notes of the transaction, if any.
 * @property string|null $recurrence_parent_id The source transaction id for a generated occurrence.
 * @property RecurrenceFrequency|null $recurrence_frequency The recurrence frequency configured on a source transaction.
 * @property int|null $recurrence_interval The number of frequency units between generated occurrences.
 * @property array<int, int>|null $recurrence_weekdays The selected ISO weekdays for a weekly recurrence.
 * @property Carbon|null $recurrence_ends_at The inclusive final date for the recurrence.
 * @property Carbon|null $recurrence_next_at The next occurrence waiting to be generated.
 * @property Carbon|null $created_at The timestamp when the transaction was created.
 * @property Carbon|null $updated_at The timestamp when the transaction was last updated.
 *
 * Relationships:
 * @property Household|null $household The household the transaction belongs to, if any.
 * @property User $creator The user who created the transaction.
 * @property EconomicAccount|null $account The private account that funded the transaction, if any.
 * @property Collection<int, EconomicTransactionAttachment> $attachments The images attached as evidence.
 * @property Collection<int, EconomicTransactionItem> $items The line items of the transaction.
 * @property Collection<int, EconomicTransactionTax> $taxes The tax breakdown of the transaction.
 * @property Collection<int, EconomicTransactionParticipant> $participants The participants of the transaction.
 * @property EconomicDocument|null $sourceDocument The document the transaction was created from, if any.
 * @property EconomicTransaction|null $recurrenceParent The source transaction for a generated occurrence.
 * @property Collection<int, EconomicTransaction> $generatedOccurrences The occurrences generated from this transaction.
 *
 * @mixin Model
 */
class EconomicTransaction extends Model
{
    /** @use HasFactory<EconomicTransactionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'household_id', 'created_by', 'type', 'scope', 'title',
        'amount_minor', 'currency', 'account_id', 'place', 'occurred_at',
        'source_document_id', 'notes', 'recurrence_parent_id',
        'recurrence_frequency', 'recurrence_interval', 'recurrence_ends_at',
        'recurrence_weekdays', 'recurrence_next_at',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string> The model attribute cast definitions keyed by attribute name.
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'scope' => TransactionScope::class,
            'recurrence_frequency' => RecurrenceFrequency::class,
            'occurred_at' => 'datetime',
            'recurrence_ends_at' => 'date',
            'recurrence_next_at' => 'datetime',
            'amount_minor' => 'integer',
            'recurrence_interval' => 'integer',
            'recurrence_weekdays' => 'array',
        ];
    }

    /**
     * The household the transaction belongs to, if any.
     *
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * The user who created the transaction.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The line items of the transaction.
     *
     * @return HasMany<EconomicTransactionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(EconomicTransactionItem::class, 'transaction_id');
    }

    /**
     * The tax breakdown of the transaction.
     *
     * @return HasMany<EconomicTransactionTax, $this>
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(EconomicTransactionTax::class, 'transaction_id');
    }

    /**
     * The participants of the transaction.
     *
     * @return HasMany<EconomicTransactionParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(EconomicTransactionParticipant::class, 'transaction_id');
    }

    /**
     * The contacts linked to the transaction, annotated with their role in the pivot.
     *
     * @return BelongsToMany<Contact, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'economic_transaction_contacts', 'economic_transaction_id', 'contact_id')
            ->withPivot('role');
    }

    /**
     * The images attached to the transaction as supporting evidence.
     *
     * @return HasMany<EconomicTransactionAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(EconomicTransactionAttachment::class, 'transaction_id')
            ->orderBy('sort_order');
    }

    /**
     * The private account that funded the transaction, if any.
     *
     * @return BelongsTo<EconomicAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(EconomicAccount::class, 'account_id');
    }

    /**
     * The source document the transaction was created from, if any.
     *
     * @return BelongsTo<EconomicDocument, $this>
     */
    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(EconomicDocument::class, 'source_document_id');
    }

    /**
     * Get the source transaction that generated this occurrence.
     *
     * @return BelongsTo<EconomicTransaction, $this> The recurrence source relationship.
     */
    public function recurrenceParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recurrence_parent_id');
    }

    /**
     * Get the independent occurrences generated from this source transaction.
     *
     * @return HasMany<EconomicTransaction, $this> The generated occurrence relationship.
     */
    public function generatedOccurrences(): HasMany
    {
        return $this->hasMany(self::class, 'recurrence_parent_id');
    }

    /**
     * Get the transaction amount formatted in decimal units.
     */
    public function getAmountAttribute(): string
    {
        return number_format($this->amount_minor / 100, 2, '.', '');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForHousehold(Builder $query, string $householdId): Builder
    {
        return $query->where('household_id', $householdId);
    }

    /**
     * Scope the query to private records (without a household).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePrivate(Builder $query): Builder
    {
        return $query->whereNull('household_id');
    }

    /**
     * Scope the query to the transactions the user created, private ones included.
     *
     * This scope does not restrict `household_id`. Callers that must exclude households the user
     * has left have to add that filter themselves, because leaving a household removes its data
     * from the personal views.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOwnedOrPrivateOf(Builder $query, string $userId): Builder
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope the query to the household transactions a member is allowed to see.
     *
     * Single source of truth for the visibility rule: shared transactions are visible to every
     * member, while personal transactions are visible only to the member who created them.
     *
     * @param  Builder<self>  $query  The incoming query builder.
     * @param  string  $userId  The authenticated user identifier.
     * @return Builder<self> The query scoped to the visible household transactions.
     */
    public function scopeVisibleToMember(Builder $query, string $userId): Builder
    {
        return $query->where(function (Builder $nested) use ($userId): void {
            $nested->ofScope(TransactionScope::Shared)
                ->orWhere('created_by', $userId);
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOfType(Builder $query, TransactionType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOfScope(Builder $query, TransactionScope $scope): Builder
    {
        return $query->where('scope', $scope);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOccurredBetween(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('occurred_at', [$start, $end]);
    }

    /**
     * Get the date used to place a transaction in time.
     *
     * `occurred_at` is nullable because imported tickets may not have a readable date. Falling back
     * to `created_at` keeps those transactions inside the aggregated windows instead of making
     * them invisible on the dashboard.
     *
     * @return CarbonInterface The transaction occurrence date, or its creation date when unknown.
     */
    public function effectiveOccurredAt(): CarbonInterface
    {
        return $this->occurred_at ?? $this->created_at ?? now();
    }

    /**
     * Scope the query to the transactions whose effective date is not before the given date.
     *
     * @param  Builder<self>  $query  The incoming query builder.
     * @param  string  $date  The inclusive lower bound date.
     * @return Builder<self> The query scoped to the effective date range.
     */
    public function scopeEffectiveOccurredFrom(Builder $query, string $date): Builder
    {
        return $query->whereRaw('DATE(COALESCE(occurred_at, created_at)) >= ?', [$date]);
    }

    /**
     * Scope the query to the transactions whose effective date is not after the given date.
     *
     * @param  Builder<self>  $query  The incoming query builder.
     * @param  string  $date  The inclusive upper bound date.
     * @return Builder<self> The query scoped to the effective date range.
     */
    public function scopeEffectiveOccurredUntil(Builder $query, string $date): Builder
    {
        return $query->whereRaw('DATE(COALESCE(occurred_at, created_at)) <= ?', [$date]);
    }

    /**
     * Scope the query to the transactions whose effective date falls inside the given window.
     *
     * @param  Builder<self>  $query  The incoming query builder.
     * @param  CarbonInterface  $start  The inclusive lower bound of the window.
     * @param  CarbonInterface  $end  The inclusive upper bound of the window.
     * @return Builder<self> The query scoped to the effective date window.
     */
    public function scopeEffectiveOccurredBetween(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $query->whereRaw(
            'COALESCE(occurred_at, created_at) BETWEEN ? AND ?',
            [$start->toDateTimeString(), $end->toDateTimeString()],
        );
    }
}
