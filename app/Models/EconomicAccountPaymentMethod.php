<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Class EconomicAccountPaymentMethod
 *
 * Pivot model linking an economic account with the payment methods it accepts. It exists as a
 * dedicated class because the project keys every table with UUIDs, so the pivot needs to generate
 * its own identifier instead of relying on an auto-incrementing key.
 *
 * @property string $id The unique identifier for the relation (UUID).
 * @property string $economic_account_id The id of the account.
 * @property string $payment_method_id The id of the accepted payment method.
 *
 * @mixin Pivot
 */
class EconomicAccountPaymentMethod extends Pivot
{
    use HasUuids;

    protected $table = 'economic_account_payment_method';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'economic_account_id', 'payment_method_id',
    ];
}
