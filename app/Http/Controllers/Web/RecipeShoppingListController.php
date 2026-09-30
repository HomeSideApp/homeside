<?php

namespace App\Http\Controllers\Web;

use App\Actions\Recipes\AddToShoppingList;
use App\Data\Recipes\AddToListData;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddToListRequest;
use App\Models\Recipe;
use App\Models\ShoppingList;
use Illuminate\Http\RedirectResponse;

/**
 * Adds recipe ingredients to a shopping list.
 */
final class RecipeShoppingListController extends Controller
{
    /**
     * Add a recipe's ingredients to a shopping list.
     *
     * @param  Recipe  $recipe  The recipe model instance.
     * @param  ShoppingList  $list  The shopping list model instance.
     * @param  AddToListRequest  $request  The incoming HTTP request.
     * @param  AddToShoppingList  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function store(
        Recipe $recipe,
        ShoppingList $list,
        AddToListRequest $request,
        AddToShoppingList $action
    ): RedirectResponse {
        $data = AddToListData::fromArray($request->validated());

        $action->execute($recipe, $list, $data, $this->authenticatedUser($request));

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('app.toast.ingredients_added_to_list'),
        ]);
    }
}
