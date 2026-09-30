<?php

namespace App\Models;

use App\Enums\PaymentMethodKind;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Class PaymentMethod
 *
 * This class represents a payment method available when creating accounts. It extends the
 * Eloquent Model class and uses the HasFactory and HasUuids traits.
 *
 * Rows with a null `user_id` are part of the global catalogue seeded by the application and
 * can only be managed by administrators; rows with a `user_id` belong to that single user.
 *
 * Properties:
 *
 * @property string $id The unique identifier for the payment method (UUID).
 * @property string|null $user_id The owning user id, or null for a global catalogue entry.
 * @property string $slug The unique slug of the payment method.
 * @property string $name The name of the payment method.
 * @property string|null $icon The icon of the payment method, if any.
 * @property string $color The color of the payment method.
 * @property PaymentMethodKind $kind The payment method category.
 * @property bool $is_active Whether the payment method is active.
 * @property int $sort_order The sort order of the payment method.
 * @property Carbon|null $created_at The timestamp when the payment method was created.
 * @property Carbon|null $updated_at The timestamp when the payment method was last updated.
 *
 * Relationships:
 * @property User|null $owner The user that owns a custom payment method, if any.
 * @property Collection<int, EconomicAccount> $accounts The accounts that accept this payment method.
 *
 * @mixin Model
 */
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id', 'slug', 'name', 'icon', 'color', 'kind', 'is_active', 'sort_order',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string> The model attribute cast definitions keyed by attribute name.
     */
    protected function casts(): array
    {
        return [
            'kind' => PaymentMethodKind::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The user that owns a custom payment method, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The accounts that accept this payment method.
     *
     * @return BelongsToMany<EconomicAccount, $this>
     */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(EconomicAccount::class, 'economic_account_payment_method')
            ->using(EconomicAccountPaymentMethod::class)
            ->withTimestamps();
    }

    /**
     * Determine whether the payment method belongs to the global seeded catalogue.
     *
     * @return bool True when the method is global, false when it belongs to a user.
     */
    public function isGlobal(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Scope the query to the global catalogue entries.
     *
     * @param  Builder<self>  $query  The incoming query builder.
     * @return Builder<self> The query scoped to global payment methods.
     */
    public function scopeGlobalCatalog(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    /**
     * Scope the query to the custom payment methods of a user.
     *
     * @param  Builder<self>  $query  The incoming query builder.
     * @param  string  $userId  The owning user identifier.
     * @return Builder<self> The query scoped to the user payment methods.
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope the query to the payment methods available to a user.
     *
     * @param  Builder<self>  $query  The incoming query builder.
     * @param  string  $userId  The user identifier whose catalogue is resolved.
     * @return Builder<self> The query scoped to global and owned payment methods.
     */
    public function scopeVisibleTo(Builder $query, string $userId): Builder
    {
        return $query->where(fn (Builder $builder) => $builder
            ->whereNull('user_id')
            ->orWhere('user_id', $userId));
    }

    /**
     * Scope the query to the active payment methods.
     *
     * @param  Builder<self>  $query  The incoming query builder.
     * @return Builder<self> The query scoped to active payment methods.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
