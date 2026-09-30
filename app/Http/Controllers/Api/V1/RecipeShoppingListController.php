<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Recipes\AddToShoppingList;
use App\Data\Recipes\AddToListData;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddToListRequest;
use App\Http\Resources\ShoppingLists\ListItemResource;
use App\Models\Household;
use App\Models\Recipe;
use App\Models\ShoppingList;
use Illuminate\Http\JsonResponse;

/**
 * Class RecipeShoppingListController
 *
 * This controller handles the API endpoint for adding a recipe's ingredients to a
 * shopping list.
 */
final class RecipeShoppingListController extends Controller
{
    /**
     * Add the recipe ingredients to the given shopping list.
     */
    public function store(
        Household $household,
        Recipe $recipe,
        ShoppingList $list,
        AddToListRequest $request,
        AddToShoppingList $action,
    ): JsonResponse {
        abort_unless($list->household_id === $household->id, 404);
        $this->authorize('view', $household);
        $this->authorize('view', $recipe);
        $this->authorize('update', $list);
        $data = AddToListData::fromArray($request->validated());

        $items = $action->execute($recipe, $list, $data, $this->authenticatedUser($request));

        return ListItemResource::collection($items)
            ->response()
            ->setStatusCode(201);
    }
}
