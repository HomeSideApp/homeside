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
use App\Http\Requests\StoreListRequest;
use App\Http\Requests\UpdateListRequest;
use App\Models\ShoppingList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the unified list catalog and private shopping-list CRUD.
 */
final class PersonalShoppingListController extends Controller
{
    public function index(ListShoppingLists $action, Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', Rule::in(['all', 'personal', 'household'])],
            'household' => ['nullable', 'uuid'],
        ]);
        $user = $this->authenticatedUser($request);
        $filters = [
            'search' => trim($validated['search'] ?? ''),
            'scope' => $validated['scope'] ?? 'all',
            'household' => $validated['household'] ?? null,
        ];
        $households = $user->households_enabled
            ? $user->households()
                ->select(['households.id', 'households.name', 'households.color', 'households.image_url'])
                ->whereHas('modules', fn ($query) => $query
                    ->where('module', 'shopping_lists')
                    ->where('enabled', true))
                ->orderBy('households.name')
                ->get()
            : collect();

        return Inertia::render('lists/Index', [
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
        Request $request,
    ): Response {
        $this->ensurePersonal($list);
        $this->authorize('view', $list);

        return Inertia::render('lists/Show', [
            ...$action->execute($list, $this->authenticatedUser($request)->id),
            'household' => null,
            'icons' => Inertia::optional(fn () => $listIcons->execute())->once(),
        ]);
    }

    public function edit(ShoppingList $list): Response
    {
        $this->ensurePersonal($list);
        $this->authorize('manage', $list);

        return Inertia::render('lists/Edit', [
            'list' => $list,
            'household' => null,
        ]);
    }

    public function update(UpdateListRequest $request, ShoppingList $list, UpdateShoppingList $action): RedirectResponse
    {
        $this->ensurePersonal($list);
        $this->authorize('update', $list);
        $data = UpdateShoppingListData::fromArray($request->validated());
        $action->execute($list, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.list_updated')]);

        return to_route('lists.show', $list);
    }

    public function destroy(ShoppingList $list, DeleteShoppingList $action): RedirectResponse
    {
        $this->ensurePersonal($list);
        $this->authorize('delete', $list);
        $action->execute($list);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.list_deleted')]);

        return to_route('lists.index');
    }

    private function ensurePersonal(ShoppingList $list): void
    {
        abort_unless($list->household_id === null, 404);
    }
}
