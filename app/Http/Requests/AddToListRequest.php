<?php

namespace App\Http\Requests;

use App\Models\Household;
use App\Models\Recipe;
use App\Models\ShoppingList;
use App\Support\HouseholdAccess;
use Illuminate\Foundation\Http\FormRequest;

class AddToListRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var Recipe $recipe */
        $recipe = $this->route('recipe');
        /** @var ShoppingList $list */
        $list = $this->route('list');
        /** @var Household|null $household */
        $household = $this->route('household');
        if ($household !== null) {
            abort_unless($list->household_id === $household->id, 404);
        }

        return $user !== null && $user->can('view', $recipe) && $user->can('update', $list)
            && ($recipe->owner_id === $user->id || $recipe->householdShares()
                ->whereIn('household_id', app(HouseholdAccess::class)->householdIds($user, 'recipes', true))->exists());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_servings' => ['required', 'integer', 'min:1'],
        ];
    }
}
