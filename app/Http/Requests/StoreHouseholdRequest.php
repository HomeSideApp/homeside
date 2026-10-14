<?php

namespace App\Http\Requests;

use App\Enums\HouseholdModule;
use App\Models\Household;
use Illuminate\Foundation\Http\FormRequest;

class StoreHouseholdRequest extends FormRequest
{
    /**
     * Authorize through the HouseholdPolicy `create` ability, the same
     * STRUCTURAL gate the action enforces for non-HTTP callers.
     *
     * Deliberately structural only: a structural denial is a 403, while a
     * quota refusal must fall through to `CreateHousehold`'s in-transaction
     * capacity assertion so the client receives the documented 402. Folding the
     * capacity read in here would answer 403 for an exhausted plan.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('create', Household::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $moduleKeys = array_column(HouseholdModule::cases(), 'value');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000'],
            'remove_image' => ['nullable', 'boolean'],
            'modules' => ['nullable', 'array:'.implode(',', $moduleKeys)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];

        foreach ($moduleKeys as $key) {
            $rules["modules.{$key}"] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'image.image' => __('app.validation.image_required_image'),
            'image.max' => __('app.validation.image_too_large'),
            'tags.*.max' => __('app.validation.tag_too_long'),
        ];
    }
}
