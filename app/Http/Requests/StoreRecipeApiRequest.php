<?php

namespace App\Http\Requests;

final class StoreRecipeApiRequest extends StoreRecipeRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['cover_image_path'], $rules['steps.*.image_path']);

        return $rules;
    }
}
