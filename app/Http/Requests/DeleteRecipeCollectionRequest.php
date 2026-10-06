<?php

namespace App\Http\Requests;

class DeleteRecipeCollectionRequest extends UpdateRecipeCollectionRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
