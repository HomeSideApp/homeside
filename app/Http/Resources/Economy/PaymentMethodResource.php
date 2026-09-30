<?php

namespace App\Http\Resources\Economy;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentMethod */
final class PaymentMethodResource extends JsonResource
{
    /**
     * Transform a payment method into its public API representation.
     *
     * Global catalogue entries expose their translation key while custom methods expose the
     * literal name supplied by the user.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return array<string, mixed> The serialized payment method.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => str_starts_with($this->name, 'app.') ? __($this->name) : $this->name,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'color' => $this->color,
            'kind' => $this->kind->value,
            'is_global' => $this->isGlobal(),
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'accounts_count' => $this->whenCounted('accounts'),
        ];
    }
}
