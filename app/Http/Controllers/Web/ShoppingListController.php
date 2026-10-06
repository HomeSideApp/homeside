<?php

namespace App\Http\Controllers\Web;

use App\Actions\Icons\ListIcons;
use App\Actions\ShoppingLists\CreateShoppingList;
use App\Actions\ShoppingLists\DeleteShoppingList;
use App\Actions\ShoppingLists\GetShoppingList;
use App\Actions\ShoppingLists\ListShoppingLists;
use App\Actions\ShoppingLists\UpdateShoppingList;
use App\Data\ShoppingLists\CreateShoppingListData;
use App\Data\ShoppingLists\UpdateShoppingListData;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteShoppingListRequest;
use App\Http\Requests\ManageShoppingListRequest;
use App\Http\Requests\StoreListRequest;
use App\Http\Requests\UpdateListRequest;
use App\Http\Requests\ViewShoppingListRequest;
use App\Http\Resources\ShoppingLists\ShoppingListResource;
use App\Models\Household;
use App\Models\ShoppingList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the shopping list CRUD.
 */
final class ShoppingListController extends Controller
{
    /**
     * List the household's shopping lists.
     *
     * @param  Household  $household  The household model instance.
     * @param  ListShoppingLists  $action  The action responsible for the operation.
     * @return Response The HTTP response.
     */
    public function index(Household $household, ListShoppingLists $action, Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        $filters = [
            'search' => '',
            'scope' => 'household',
            'household' => $household->id,
        ];

        return Inertia::render('lists/Index', [
            'can' => ['createPersonalList' => $user->getPermissionRouteNames()->contains('households.lists.create')],
            'lists' => $action->execute($user, $filters),
            'households' => [$household->only(['id', 'name', 'color', 'image_url'])],
            'activeHouseholdId' => $user->active_household_id,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the shopping list creation form.
     *
     * @param  Household  $household  The household model instance.
     * @return Response The HTTP response.
     */
    public function create(Household $household): Response
    {
        return Inertia::render('lists/Create', [
            'household' => $household,
        ]);
    }

    /**
     * Create a new shopping list.
     *
     * @param  StoreListRequest  $request  The incoming HTTP request.
     * @param  Household  $household  The household model instance.
     * @param  CreateShoppingList  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function store(StoreListRequest $request, Household $household, CreateShoppingList $action): RedirectResponse
    {
        $data = CreateShoppingListData::fromArray($request->validated());
        $list = $action->execute($data, $this->authenticatedUser($request), $household);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lista creada correctamente.']);

        return to_route('households.lists.show', [$household, $list]);
    }

    /**
     * Show a shopping list.
     *
     * @param  Household  $household  The household model instance.
     * @param  ShoppingList  $list  The shopping list model instance.
     * @param  GetShoppingList  $action  The action responsible for the operation.
     * @return Response The HTTP response.
     */
    public function show(
        Household $household,
        ShoppingList $list,
        GetShoppingList $action,
        ListIcons $listIcons,
        ViewShoppingListRequest $request,
    ): Response {
        $data = $action->execute($list, $this->authenticatedUser($request)->id);

        return Inertia::render('lists/Show', [
            ...$data,
            'list' => ShoppingListResource::make($data['list'])->response($request)->getData(true)['data'],
            'household' => $household,
            'icons' => Inertia::optional(fn () => $listIcons->execute())->once(),
        ]);
    }

    /**
     * Show the shopping list editing form.
     *
     * @param  Household  $household  The household model instance.
     * @param  ShoppingList  $list  The shopping list model instance.
     * @return Response The HTTP response.
     */
    public function edit(Household $household, ShoppingList $list, ManageShoppingListRequest $request): Response
    {

        return Inertia::render('lists/Edit', [
            'list' => $list,
            'household' => $household,
        ]);
    }

    /**
     * Update a shopping list.
     *
     * @param  UpdateListRequest  $request  The incoming HTTP request.
     * @param  Household  $household  The household model instance.
     * @param  ShoppingList  $list  The shopping list model instance.
     * @param  UpdateShoppingList  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function update(UpdateListRequest $request, Household $household, ShoppingList $list, UpdateShoppingList $action): RedirectResponse
    {
        $data = UpdateShoppingListData::fromArray($request->validated());
        $action->execute($list, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lista actualizada correctamente.']);

        return to_route('households.lists.show', [$household, $list]);
    }

    /**
     * Delete a shopping list.
     *
     * @param  Household  $household  The household model instance.
     * @param  ShoppingList  $list  The shopping list model instance.
     * @param  DeleteShoppingList  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function destroy(Household $household, ShoppingList $list, DeleteShoppingList $action, DeleteShoppingListRequest $request): RedirectResponse
    {
        $action->execute($list);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lista eliminada correctamente.']);

        return to_route('households.lists.index', $household);
    }
}
