<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class EconomicTransactionTax
 *
 * This class represents a tax breakdown line of an economic transaction.
 * It extends the Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the tax line (UUID).
 * @property string $transaction_id The id of the economic transaction the tax line belongs to.
 * @property string $name The name of the tax.
 * @property int $rate The tax rate as a percentage.
 * @property int $taxable_base_minor The taxable base of the tax in minor currency units.
 * @property int $tax_amount_minor The tax amount in minor currency units.
 * @property Carbon|null $created_at The timestamp when the tax line was created.
 * @property Carbon|null $updated_at The timestamp when the tax line was last updated.
 *
 * Relationships:
 * @property EconomicTransaction|null $transaction The economic transaction the tax line belongs to.
 *
 * @mixin Model
 */
class EconomicTransactionTax extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'transaction_id', 'name', 'rate', 'taxable_base_minor', 'tax_amount_minor',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'integer',
            'taxable_base_minor' => 'integer',
            'tax_amount_minor' => 'integer',
        ];
    }

    /**
     * The transaction this tax line belongs to.
     *
     * @return BelongsTo<EconomicTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(EconomicTransaction::class, 'transaction_id');
    }
}
