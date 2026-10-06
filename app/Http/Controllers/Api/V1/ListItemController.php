<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ShoppingLists\AddListItem;
use App\Actions\ShoppingLists\DeleteListItem;
use App\Actions\ShoppingLists\QuickCreateListItem;
use App\Actions\ShoppingLists\UpdateListItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteListItemRequest;
use App\Http\Requests\QuickCreateListItemRequest;
use App\Http\Requests\StoreListItemRequest;
use App\Http\Requests\UpdateListItemRequest;
use App\Http\Requests\ViewListItemRequest;
use App\Http\Resources\ShoppingLists\ListItemResource;
use Illuminate\Http\JsonResponse;
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
        $list = $request->shoppingList();
        $item = $action->execute($list, $request->validated(), $this->authenticatedUser($request)->id);

        return ListItemResource::make($item)->response()->setStatusCode(201);
    }

    /**
     * Store a newly created list item.
     */
    public function store(StoreListItemRequest $request, AddListItem $action): JsonResponse
    {
        $list = $request->shoppingList();
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
        $list = $request->shoppingList();
        $item = $request->listItem();
        $item = $action->execute($item, $request->validated());

        return new ListItemResource($item);
    }

    /**
     * Delete the given list item.
     */
    public function destroy(DeleteListItemRequest $request, DeleteListItem $action): Response
    {
        $list = $request->shoppingList();
        $item = $request->listItem();
        $action->execute($item);

        return response()->noContent();
    }

    /**
     * Serve the image of the given list item.
     */
    public function image(ViewListItemRequest $request): BinaryFileResponse
    {
        $list = $request->shoppingList();
        $item = $request->listItem();

        if (! $item->image_url || ! Storage::disk('local')->exists($item->image_url)) {
            abort(404);
        }

        return response()->file(
            Storage::disk('local')->path($item->image_url),
            ['Content-Type' => Storage::disk('local')->mimeType($item->image_url)]
        );
    }
}
