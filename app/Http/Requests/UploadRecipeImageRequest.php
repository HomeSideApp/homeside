<?php

namespace App\Http\Requests;

class UploadRecipeImageRequest extends ManageRecipeRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=6000,max_height=6000']];
    }
}
