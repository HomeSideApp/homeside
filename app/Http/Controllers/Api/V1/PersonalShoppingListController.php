<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ShoppingLists\CreateShoppingList;
use App\Actions\ShoppingLists\DeleteShoppingList;
use App\Actions\ShoppingLists\GetShoppingList;
use App\Actions\ShoppingLists\UpdateShoppingList;
use App\Data\ShoppingLists\CreateShoppingListData;
use App\Data\ShoppingLists\UpdateShoppingListData;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteShoppingListRequest;
use App\Http\Requests\IndexPersonalShoppingListsRequest;
use App\Http\Requests\StoreListRequest;
use App\Http\Requests\UpdateListRequest;
use App\Http\Requests\ViewShoppingListRequest;
use App\Http\Resources\ShoppingLists\ShoppingListResource;
use App\Models\ShoppingList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Handles the authenticated user's private shopping lists.
 */
final class PersonalShoppingListController extends Controller
{
    public function index(IndexPersonalShoppingListsRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $query = ShoppingList::query()
            ->where('created_by', $this->authenticatedUser($request)->id)
            ->whereNull('household_id');

        if ($search = $validated['search'] ?? null) {
            $query->where('name', 'like', "%{$search}%");
        }

        $lists = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($validated['perPage'] ?? 15)
            ->withQueryString();

        return ShoppingListResource::collection($lists);
    }

    public function show(ShoppingList $list, GetShoppingList $action, ViewShoppingListRequest $request): ShoppingListResource
    {
        $data = $action->execute($list, $this->authenticatedUser($request)->id);

        return new ShoppingListResource($data['list']);
    }

    public function store(StoreListRequest $request, CreateShoppingList $action): JsonResponse
    {
        $data = CreateShoppingListData::fromArray($request->validated());
        $list = $action->execute($data, $this->authenticatedUser($request));

        return ShoppingListResource::make($list)
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateListRequest $request, ShoppingList $list, UpdateShoppingList $action): ShoppingListResource
    {
        $data = UpdateShoppingListData::fromArray($request->validated());

        return new ShoppingListResource($action->execute($list, $data));
    }

    public function destroy(ShoppingList $list, DeleteShoppingList $action, DeleteShoppingListRequest $request): Response
    {
        $action->execute($list);

        return response()->noContent();
    }
}
