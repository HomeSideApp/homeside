<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ShoppingLists\AddListItem;
use App\Actions\ShoppingLists\DeleteListItem;
use App\Actions\ShoppingLists\QuickCreateListItem;
use App\Actions\ShoppingLists\UpdateListItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\QuickCreateListItemRequest;
use App\Http\Requests\StoreListItemRequest;
use App\Http\Requests\UpdateListItemRequest;
use App\Http\Resources\ShoppingLists\ListItemResource;
use App\Models\Household;
use App\Models\ListItem;
use App\Models\ShoppingList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Class ListItemController
 *
 * This controller handles the API endpoints for managing shopping list items, including
 * creating, updating, deleting items and serving their images.
 */
final class ListItemController extends Controller
{
    public function quickCreate(QuickCreateListItemRequest $request, QuickCreateListItem $action): JsonResponse
    {
        $list = $this->resolveShoppingList($request);
        $this->ensureListMatchesRouteContext($request, $list);
        $this->authorize('update', $list);
        $item = $action->execute($list, $request->validated(), $this->authenticatedUser($request)->id);

        return ListItemResource::make($item)->response()->setStatusCode(201);
    }

    /**
     * Store a newly created list item.
     */
    public function store(StoreListItemRequest $request, AddListItem $action): JsonResponse
    {
        $list = $this->resolveShoppingList($request);
        $this->ensureListMatchesRouteContext($request, $list);
        $this->authorize('update', $list);
        $item = $action->execute($list, $request->validated(), $this->authenticatedUser($request)->id);

        return ListItemResource::make($item)
            ->response()
            ->setStatusCode($item->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Update the given list item.
     */
    public function update(UpdateListItemRequest $request, UpdateListItem $action): ListItemResource
    {
        $list = $this->resolveShoppingList($request);
        $this->ensureListMatchesRouteContext($request, $list);
        $item = ListItem::query()->whereKey($request->route('item'))->firstOrFail();
        abort_if($item->list_id !== $list->id, 404);
        $this->authorize('update', $list);
        $item = $action->execute($item, $request->validated());

        return new ListItemResource($item);
    }

    /**
     * Delete the given list item.
     */
    public function destroy(Request $request, DeleteListItem $action): Response
    {
        $list = $this->resolveShoppingList($request);
        $this->ensureListMatchesRouteContext($request, $list);
        $item = ListItem::query()->whereKey($request->route('item'))->firstOrFail();
        abort_if($item->list_id !== $list->id, 404);
        $this->authorize('delete', $list);
        $action->execute($item);

        return response()->noContent();
    }

    /**
     * Serve the image of the given list item.
     */
    public function image(Request $request): BinaryFileResponse
    {
        $list = $this->resolveShoppingList($request);
        $this->ensureListMatchesRouteContext($request, $list);
        $item = ListItem::query()->whereKey($request->route('item'))->firstOrFail();
        $this->authorize('view', $list);

        if ($item->list_id !== $list->id) {
            abort(404);
        }

        if (! $item->image_url || ! Storage::disk('local')->exists($item->image_url)) {
            abort(404);
        }

        return response()->file(
            Storage::disk('local')->path($item->image_url),
            ['Content-Type' => Storage::disk('local')->mimeType($item->image_url)]
        );
    }

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
}
