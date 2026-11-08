<?php

namespace App\Models;

use App\Enums\SplitType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class EconomicTransactionParticipant
 *
 * This class represents a participant in the split of an economic transaction.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the participant (UUID).
 * @property string $transaction_id The id of the economic transaction the participant belongs to.
 * @property string|null $household_member_id The id of the household member participating in the transaction, or null for external identities.
 * @property string|null $contact_id The optional address book contact linked to an external participant.
 * @property string|null $display_name The optional name of an external participant.
 * @property int $share_count The number of heads this participant represents in an equal split.
 * @property SplitType $split_type The type of split for the participant.
 * @property int $amount_minor The amount assigned to the participant in minor currency units.
 * @property int|null $percentage The percentage assigned to the participant, if applicable.
 * @property Carbon|null $created_at The timestamp when the participant was created.
 * @property Carbon|null $updated_at The timestamp when the participant was last updated.
 *
 * Relationships:
 * @property EconomicTransaction|null $transaction The economic transaction the participant belongs to.
 * @property HouseholdMember|null $householdMember The household member participating in the transaction.
 * @property Contact|null $contact The contact linked to an external participant.
 *
 * @mixin Model
 */
class EconomicTransactionParticipant extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'transaction_id', 'household_member_id', 'contact_id', 'display_name',
        'share_count', 'split_type', 'amount_minor', 'percentage',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'split_type' => SplitType::class,
            'amount_minor' => 'integer',
            'percentage' => 'integer',
            'share_count' => 'integer',
        ];
    }

    /** @return BelongsTo<EconomicTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(EconomicTransaction::class, 'transaction_id');
    }

    /** @return BelongsTo<HouseholdMember, $this> */
    public function householdMember(): BelongsTo
    {
        return $this->belongsTo(HouseholdMember::class, 'household_member_id');
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
