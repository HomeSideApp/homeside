<?php

namespace App\Http\Requests;

final class UpdateRecipeApiRequest extends UpdateRecipeRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['cover_image_path'], $rules['steps.*.image_path']);

        return $rules;
    }
}
