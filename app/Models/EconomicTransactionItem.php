<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class EconomicTransactionItem
 *
 * This class represents a line item of an economic transaction.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the line item (UUID).
 * @property string $transaction_id The id of the economic transaction the item belongs to.
 * @property string $name The name of the item.
 * @property int $quantity The quantity of the item.
 * @property int $unit_amount_minor The unit amount of the item in minor currency units.
 * @property int $subtotal_minor The subtotal of the item in minor currency units.
 * @property int|null $tax_amount_minor The tax amount of the item in minor currency units, if any.
 * @property int $total_minor The total of the item in minor currency units.
 * @property int $position The position of the item within the transaction.
 * @property Carbon|null $created_at The timestamp when the item was created.
 * @property Carbon|null $updated_at The timestamp when the item was last updated.
 *
 * Relationships:
 * @property EconomicTransaction|null $transaction The economic transaction the item belongs to.
 *
 * @mixin Model
 */
class EconomicTransactionItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'transaction_id', 'name', 'quantity', 'unit_amount_minor',
        'subtotal_minor', 'tax_amount_minor', 'total_minor', 'position',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount_minor' => 'integer',
            'subtotal_minor' => 'integer',
            'tax_amount_minor' => 'integer',
            'total_minor' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * The transaction this item belongs to.
     *
     * @return BelongsTo<EconomicTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(EconomicTransaction::class, 'transaction_id');
    }
}
