<?php

namespace App\Data\Economy;

use App\Enums\PaymentMethodKind;

/**
 * Class PaymentMethodData
 *
 * Immutable payload used to create or update a payment method. Global catalogue entries are
 * created by the seeder, so user supplied payloads never carry an owner identifier.
 */
final readonly class PaymentMethodData
{
    /**
     * Create immutable data for a payment method.
     *
     * @param  string  $name  The user-facing payment method name.
     * @param  PaymentMethodKind  $kind  The payment method category.
     * @param  string|null  $icon  The optional Lucide icon name.
     * @param  string|null  $color  The optional badge color in hexadecimal notation.
     * @param  bool  $is_active  Whether the payment method can be used for new accounts.
     * @param  int  $sort_order  The ordering position inside the user catalogue.
     * @return void This constructor does not return a value.
     */
    public function __construct(
        public string $name,
        public PaymentMethodKind $kind,
        public ?string $icon = null,
        public ?string $color = null,
        public bool $is_active = true,
        public int $sort_order = 0,
    ) {}

    /**
     * Build payment method data from a validated request payload.
     *
     * @param  array{name: string, kind: string, icon?: string|null, color?: string|null, is_active?: bool|int|string, sort_order?: int|string|null}  $data  The validated values keyed by request field.
     * @return self The normalized immutable payment method data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            kind: PaymentMethodKind::from($data['kind']),
            icon: $data['icon'] ?? null,
            color: $data['color'] ?? null,
            is_active: filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOL),
            sort_order: (int) ($data['sort_order'] ?? 0),
        );
    }

    /**
     * Export the data as an attribute array for persistence.
     *
     * @param  string|null  $userId  The owner identifier, or null for a global catalogue entry.
     * @return array<string, mixed> The persistable attribute array.
     */
    public function toAttributes(?string $userId = null): array
    {
        return [
            'user_id' => $userId,
            'name' => $this->name,
            'kind' => $this->kind,
            'icon' => $this->icon,
            'color' => $this->color ?? '#9E9E9E',
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
