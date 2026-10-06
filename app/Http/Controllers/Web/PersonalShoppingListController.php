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
use App\Http\Requests\FilterPersonalShoppingListsRequest;
use App\Http\Requests\ManageShoppingListRequest;
use App\Http\Requests\StoreListRequest;
use App\Http\Requests\UpdateListRequest;
use App\Http\Requests\ViewShoppingListRequest;
use App\Http\Resources\ShoppingLists\ShoppingListResource;
use App\Models\ShoppingList;
use App\Support\HouseholdAccess;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the unified list catalog and private shopping-list CRUD.
 */
final class PersonalShoppingListController extends Controller
{
    public function index(ListShoppingLists $action, FilterPersonalShoppingListsRequest $request): Response
    {
        $validated = $request->validated();
        $user = $this->authenticatedUser($request);
        $filters = [
            'search' => trim($validated['search'] ?? ''),
            'scope' => $validated['scope'] ?? 'all',
            'household' => $validated['household'] ?? null,
        ];
        $access = app(HouseholdAccess::class);
        $households = $user->households_enabled
            ? $user->households()
                ->select(['households.id', 'households.name', 'households.color', 'households.image_url'])
                ->whereIn('households.id', $access->householdIds($user, 'shopping_lists'))
                ->orderBy('households.name')
                ->get()->each(fn ($household) => $household->setAttribute('can_write', $access->allows($user, $household, 'shopping_lists', true)))
            : collect();

        return Inertia::render('lists/Index', [
            'can' => ['createPersonalList' => $user->getPermissionRouteNames()->contains('households.lists.create')],
            'lists' => $action->execute($user, filters: $filters),
            'households' => $households,
            'activeHouseholdId' => $user->households_enabled ? $user->active_household_id : null,
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('lists/Create', [
            'household' => null,
        ]);
    }

    public function store(StoreListRequest $request, CreateShoppingList $action): RedirectResponse
    {
        $data = CreateShoppingListData::fromArray($request->validated());
        $list = $action->execute($data, $this->authenticatedUser($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.list_created')]);

        return to_route('lists.show', $list);
    }

    public function show(
        ShoppingList $list,
        GetShoppingList $action,
        ListIcons $listIcons,
        ViewShoppingListRequest $request,
    ): Response {
        $data = $action->execute($list, $this->authenticatedUser($request)->id);

        return Inertia::render('lists/Show', [
            ...$data,
            'list' => ShoppingListResource::make($data['list'])->response($request)->getData(true)['data'],
            'household' => null,
            'icons' => Inertia::optional(fn () => $listIcons->execute())->once(),
        ]);
    }

    public function edit(ShoppingList $list, ManageShoppingListRequest $request): Response
    {

        return Inertia::render('lists/Edit', [
            'list' => $list,
            'household' => null,
        ]);
    }

    public function update(UpdateListRequest $request, ShoppingList $list, UpdateShoppingList $action): RedirectResponse
    {
        $data = UpdateShoppingListData::fromArray($request->validated());
        $action->execute($list, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.list_updated')]);

        return to_route('lists.show', $list);
    }

    public function destroy(ShoppingList $list, DeleteShoppingList $action, DeleteShoppingListRequest $request): RedirectResponse
    {
        $action->execute($list);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.list_deleted')]);

        return to_route('lists.index');
    }
}
