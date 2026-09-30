<?php

namespace App\Http\Requests;

use App\Models\CryptoAsset;
use App\Models\EconomicAccount;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEconomicAccountRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may update the routed account.
     *
     * @return bool True when the account belongs to the authenticated user.
     */
    public function authorize(): bool
    {
        $account = $this->route('account');

        if (! $account instanceof EconomicAccount) {
            return false;
        }

        return $this->user()?->can('update', $account) ?? false;
    }

    /**
     * Define the validation rules for updating a private economic account.
     *
     * @return array<string, mixed> The Laravel validation rules keyed by request field.
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['required', 'string', 'max:80'],
            'payment_method_ids' => ['required', 'array', 'min:1'],
            'payment_method_ids.*' => [
                'required',
                'uuid',
                Rule::exists('payment_methods', 'id')->where(
                    fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $userId),
                ),
            ],
            'currency' => ['required', 'string', 'regex:/^[A-Za-z0-9]{2,10}$/'],
            'crypto_asset_id' => ['nullable', 'uuid', Rule::exists('crypto_assets', 'id')->where('is_active', true)],
            'decimal_places' => ['sometimes', 'integer', 'min:0', 'max:18'],
            'initial_balance' => ['nullable', 'numeric', 'min:-999999999', 'max:999999999'],
            'initial_balance_minor' => ['nullable', 'integer'],
            'initial_balance_at' => ['nullable', 'date'],
            'icon' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'last_four_digits' => ['nullable', 'string', 'max:4', 'regex:/^[0-9]+$/'],
            'include_in_totals' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Define validation callbacks that enforce account level invariants.
     *
     * @return array<int, callable(Validator): void> The callbacks executed after the base rules.
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $methodIds = array_filter((array) $this->input('payment_method_ids', []), 'is_string');

            if ($methodIds === []) {
                return;
            }

            $hasCryptoMethod = PaymentMethod::query()
                ->whereIn('id', $methodIds)
                ->get()
                ->contains(fn (PaymentMethod $method): bool => $method->kind->isCrypto());

            if (! $hasCryptoMethod) {
                return;
            }

            if (! is_string($this->input('crypto_asset_id'))) {
                $validator->errors()->add(
                    'crypto_asset_id',
                    __('app.validation.crypto_asset_required'),
                );

                return;
            }

            $asset = CryptoAsset::query()->whereKey($this->input('crypto_asset_id'))->first();

            if ($asset !== null && strtoupper((string) $this->input('currency')) !== $asset->symbol) {
                $validator->errors()->add(
                    'currency',
                    __('app.validation.crypto_currency_mismatch'),
                );
            }
        }];
    }

    /**
     * Define the translated attribute names used in validation messages.
     *
     * @return array<string, string> The attribute labels keyed by request field.
     */
    public function attributes(): array
    {
        return [
            'name' => __('app.economy.account.name'),
            'payment_method_ids' => __('app.economy.account.payment_method'),
            'currency' => __('app.economy.account.currency'),
            'initial_balance' => __('app.economy.account.initial_balance'),
        ];
    }
}
