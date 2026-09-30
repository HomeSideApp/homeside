<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

final class ProviderSpecificationRules
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function attributes(array $validated): array
    {
        $fields = array_filter(array_keys(self::rules()), fn (string $field): bool => ! str_contains($field, '.'));

        return array_intersect_key($validated, array_flip($fields));
    }

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'family' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'attachment' => ['boolean'],
            'reasoning' => ['boolean'],
            'reasoning_options' => ['nullable', 'array'],
            'tool_call' => ['boolean'],
            'structured_output' => ['boolean'],
            'temperature' => ['boolean'],
            'open_weights' => ['boolean'],
            'modalities_input' => ['nullable', 'array'],
            'modalities_input.*' => ['string', Rule::in(['text', 'image', 'audio', 'video'])],
            'modalities_output' => ['nullable', 'array'],
            'modalities_output.*' => ['string', Rule::in(['text', 'image', 'audio', 'video'])],
            'context_window' => ['nullable', 'integer', 'min:1'],
            'max_input_tokens' => ['nullable', 'integer', 'min:1'],
            'max_output_tokens' => ['nullable', 'integer', 'min:1'],
            'cost_input' => ['nullable', 'numeric', 'min:0'],
            'cost_output' => ['nullable', 'numeric', 'min:0'],
            'cost_cache_read' => ['nullable', 'numeric', 'min:0'],
            'cost_cache_write' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
