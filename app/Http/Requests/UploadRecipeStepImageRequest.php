<?php

namespace App\Http\Requests;

use App\Models\Recipe;

class UploadRecipeStepImageRequest extends UploadRecipeImageRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        /** @var Recipe $recipe */
        $recipe = $this->route('recipe');
        $recipe->steps()->whereKey($this->route('step'))->firstOrFail();

        return true;
    }
}
