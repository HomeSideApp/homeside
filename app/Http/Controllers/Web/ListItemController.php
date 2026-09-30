<?php

namespace App\Http\Controllers\Web;

use App\Actions\ShoppingLists\AddListItem;
use App\Actions\ShoppingLists\DeleteListItem;
use App\Actions\ShoppingLists\UpdateListItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreListItemRequest;
use App\Http\Requests\UpdateListItemRequest;
use App\Models\Household;
use App\Models\ListItem;
use App\Models\ShoppingList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Handles the shopping list items CRUD.
 */
final class ListItemController extends Controller
{
    /**
     * Add an item to a shopping list.
     *
     * @param  StoreListItemRequest  $request  The incoming HTTP request.
     * @param  AddListItem  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function store(StoreListItemRequest $request, AddListItem $action): RedirectResponse
    {
        $list = $this->resolveShoppingList($request);
        $this->ensureListMatchesRouteContext($request, $list);
        $this->authorize('update', $list);
        $action->execute($list, $request->validated(), $this->authenticatedUser($request)->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.list_item_added')]);

        return $this->redirectToList($list);
    }

    /**
     * Update a list item.
     *
     * @param  UpdateListItemRequest  $request  The incoming HTTP request.
     * @param  UpdateListItem  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function update(UpdateListItemRequest $request, UpdateListItem $action): RedirectResponse
    {
        $list = $this->resolveShoppingList($request);
        $this->ensureListMatchesRouteContext($request, $list);
        $item = ListItem::query()->whereKey($request->route('item'))->firstOrFail();
        abort_if($item->list_id !== $list->id, 404);
        $this->authorize('update', $list);
        $action->execute($item, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Elemento actualizado correctamente.']);

        return $this->redirectToList($list);
    }

    /**
     * Delete a list item.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  DeleteListItem  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function destroy(Request $request, DeleteListItem $action): RedirectResponse
    {
        $list = $this->resolveShoppingList($request);
        $this->ensureListMatchesRouteContext($request, $list);
        $item = ListItem::query()->whereKey($request->route('item'))->firstOrFail();
        abort_if($item->list_id !== $list->id, 404);
        $this->authorize('delete', $list);
        $action->execute($item);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Elemento eliminado correctamente.']);

        return $this->redirectToList($list);
    }

    /**
     * Serve an image attached to an item in an accessible list.
     */
    public function image(Request $request): BinaryFileResponse
    {
        $list = $this->resolveShoppingList($request);
        $this->ensureListMatchesRouteContext($request, $list);
        $item = ListItem::query()->whereKey($request->route('item'))->firstOrFail();
        abort_if($item->list_id !== $list->id, 404);
        $this->authorize('view', $list);

        abort_unless($item->image_url && Storage::disk('local')->exists($item->image_url), 404);

        return response()->file(
            Storage::disk('local')->path($item->image_url),
            ['Content-Type' => Storage::disk('local')->mimeType($item->image_url)]
        );
    }

    /**
     * Resolve the shopping list from the route.
     */
    private function resolveShoppingList(Request $request): ShoppingList
    {
        $list = $request->route('list');

        if ($list instanceof ShoppingList) {
            return $list;
        }

        return ShoppingList::query()->whereKey($list)->firstOrFail();
    }

    private function ensureListMatchesRouteContext(Request $request, ShoppingList $list): void
    {
        $household = $request->route('household');
        $householdId = $household instanceof Household ? $household->id : $household;

        if ($householdId === null) {
            abort_unless($list->household_id === null, 404);

            return;
        }

        abort_unless(is_string($householdId) && $list->household_id === $householdId, 404);
    }

    private function redirectToList(ShoppingList $list): RedirectResponse
    {
        if ($list->household_id === null) {
            return to_route('lists.show', $list);
        }

        return to_route('households.lists.show', [$list->household_id, $list]);
    }
}
