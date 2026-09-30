<?php

namespace App\Models;

use Database\Factories\EconomicAccountFactory;
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
 * Class EconomicAccount
 *
 * This class represents a private money account owned by a single user. It extends the
 * Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Accounts are always private: even when a household expense references an account, that
 * association is only visible to the transaction creator.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the account (UUID).
 * @property string $user_id The id of the user that owns the account.
 * @property string $name The account name.
 * @property string $currency The account currency code or crypto symbol.
 * @property string|null $crypto_asset_id The crypto asset the account holds, when it is a crypto wallet.
 * @property int $decimal_places The number of minor units per currency unit.
 * @property int $initial_balance_minor The opening balance in minor currency units.
 * @property Carbon|null $initial_balance_at The optional date the opening balance refers to.
 * @property string|null $icon The icon of the account, if any.
 * @property string $color The color of the account.
 * @property string|null $last_four_digits The optional last four digits of the account number.
 * @property bool $include_in_totals Whether the account is included in aggregated totals.
 * @property Carbon|null $archived_at The timestamp when the account was archived, if any.
 * @property Carbon|null $created_at The timestamp when the account was created.
 * @property Carbon|null $updated_at The timestamp when the account was last updated.
 *
 * Relationships:
 * @property User $owner The user that owns the account.
 * @property Collection<int, PaymentMethod> $paymentMethods The payment methods accepted by the account.
 * @property CryptoAsset|null $cryptoAsset The crypto asset the account holds, if any.
 * @property Collection<int, EconomicTransaction> $transactions The transactions assigned to the account.
 *
 * @mixin Model
 */
class EconomicAccount extends Model
{
    /** @use HasFactory<EconomicAccountFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id', 'name', 'currency', 'crypto_asset_id',
        'decimal_places', 'initial_balance_minor', 'initial_balance_at', 'icon',
        'color', 'last_four_digits', 'include_in_totals', 'archived_at',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string> The model attribute cast definitions keyed by attribute name.
     */
    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'initial_balance_minor' => 'integer',
            'initial_balance_at' => 'date',
            'include_in_totals' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * The user that owns the account.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The payment methods accepted by the account.
     *
     * A single account can be charged through several methods (card, Bizum, PayPal, ...), so the
     * relation is many-to-many and drives which account may hold crypto funds.
     *
     * @return BelongsToMany<PaymentMethod, $this>
     */
    public function paymentMethods(): BelongsToMany
    {
        return $this->belongsToMany(PaymentMethod::class, 'economic_account_payment_method')
            ->using(EconomicAccountPaymentMethod::class)
            ->withTimestamps();
    }

    /**
     * Determine whether the account accepts at least one crypto payment method.
     *
     * @return bool True when any of the linked methods is a crypto wallet; otherwise false.
     */
    public function isCryptoAccount(): bool
    {
        return $this->paymentMethods
            ->contains(fn (PaymentMethod $method): bool => $method->kind->isCrypto());
    }

    /**
     * The crypto asset the account holds, if any.
     *
     * @return BelongsTo<CryptoAsset, $this>
     */
    public function cryptoAsset(): BelongsTo
    {
        return $this->belongsTo(CryptoAsset::class, 'crypto_asset_id');
    }

    /**
     * The transactions assigned to the account.
     *
     * @return HasMany<EconomicTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(EconomicTransaction::class, 'account_id');
    }

    /**
     * Determine whether the account is archived.
     *
     * @return bool True when the account has been archived; otherwise false.
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Scope the query to the accounts owned by a user.
     *
     * @param  Builder<self>  $query  The incoming query builder.
     * @param  string  $userId  The owning user identifier.
     * @return Builder<self> The query scoped to the user accounts.
     */
    public function scopeOwnedBy(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope the query to the non archived accounts.
     *
     * @param  Builder<self>  $query  The incoming query builder.
     * @return Builder<self> The query scoped to active accounts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
