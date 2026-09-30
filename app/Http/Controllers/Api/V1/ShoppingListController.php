<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ShoppingLists\CreateShoppingList;
use App\Actions\ShoppingLists\DeleteShoppingList;
use App\Actions\ShoppingLists\GetShoppingList;
use App\Actions\ShoppingLists\UpdateShoppingList;
use App\Data\ShoppingLists\CreateShoppingListData;
use App\Data\ShoppingLists\UpdateShoppingListData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreListRequest;
use App\Http\Requests\UpdateListRequest;
use App\Http\Resources\ShoppingLists\ShoppingListResource;
use App\Models\Household;
use App\Models\ShoppingList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Class ShoppingListController
 *
 * This controller handles the API endpoints for managing shopping lists within a household,
 * including listing, retrieving, creating, updating and deleting lists.
 */
final class ShoppingListController extends Controller
{
    /**
     * List the shopping lists of the given household.
     */
    public function index(Household $household, Request $request): AnonymousResourceCollection
    {
        abort_unless($this->authenticatedUser($request)->households_enabled, 403);
        $this->authorize('view', $household);
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $query = ShoppingList::query()
            ->with('household:id,name')
            ->where('household_id', $household->id);

        if ($search = $validated['search'] ?? null) {
            $query->where('name', 'like', "%{$search}%");
        }

        $lists = $query->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($validated['perPage'] ?? 15)
            ->withQueryString();

        return ShoppingListResource::collection($lists);
    }

    /**
     * Return the given shopping list.
     */
    public function show(Household $household, ShoppingList $list, GetShoppingList $action, Request $request): ShoppingListResource
    {
        $this->authorize('view', $list);
        $data = $action->execute($list, $this->authenticatedUser($request)->id);

        return new ShoppingListResource($data['list']);
    }

    /**
     * Store a newly created shopping list.
     */
    public function store(StoreListRequest $request, Household $household, CreateShoppingList $action): JsonResponse
    {
        abort_unless($this->authenticatedUser($request)->households_enabled, 403);
        $this->authorize('view', $household);
        $data = CreateShoppingListData::fromArray($request->validated());
        $list = $action->execute($data, $this->authenticatedUser($request), $household);

        return ShoppingListResource::make($list)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the given shopping list.
     */
    public function update(UpdateListRequest $request, Household $household, ShoppingList $list, UpdateShoppingList $action): ShoppingListResource
    {
        $this->authorize('manage', $list);
        $data = UpdateShoppingListData::fromArray($request->validated());
        $list = $action->execute($list, $data);

        return new ShoppingListResource($list);
    }

    /**
     * Delete the given shopping list.
     */
    public function destroy(Household $household, ShoppingList $list, DeleteShoppingList $action): Response
    {
        $this->authorize('delete', $list);
        $action->execute($list);

        return response()->noContent();
    }
}
