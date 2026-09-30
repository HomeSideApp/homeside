<?php

namespace App\Http\Controllers\Web;

use App\Actions\Products\QuickCreateProduct;
use App\Actions\Products\SearchProducts;
use App\Actions\ShoppingLists\AddListItem;
use App\Actions\ShoppingLists\GetShoppingList;
use App\Http\Controllers\Controller;
use App\Http\Requests\QuickCreateProductRequest;
use App\Models\Household;
use App\Models\ListItem;
use App\Models\ShoppingList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles product search and quick creation within a shopping list.
 */
final class ProductSearchController extends Controller
{
    /**
     * Search products via Inertia partial reload.
     *
     * Renders the same lists/Show component so that Inertia partial
     * reloads work correctly — the client requests only `searchResults`.
     *
     * @param  Household  $household  The household model instance.
     * @param  ShoppingList  $list  The shopping list model instance.
     * @param  SearchProducts  $action  The action responsible for the operation.
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The HTTP response.
     */
    public function search(Household $household, ShoppingList $list, SearchProducts $action, Request $request): Response
    {
        abort_unless($list->household_id === $household->id, 404);

        return $this->searchList($list, $action, $request, $household);
    }

    /**
     * Search products for a personal shopping list.
     */
    public function personalSearch(ShoppingList $list, SearchProducts $action, Request $request): Response
    {
        abort_unless($list->household_id === null, 404);

        return $this->searchList($list, $action, $request);
    }

    private function searchList(
        ShoppingList $list,
        SearchProducts $action,
        Request $request,
        ?Household $household = null,
    ): Response {
        $this->authorize('view', $list);

        $query = $request->string('q')->toString();
        $userId = $this->authenticatedUser($request)->id;

        $products = $action->execute($query, $userId);

        // Apply icon overrides from list items
        if ($query !== '') {
            $iconOverrides = ListItem::where('list_id', $list->id)
                ->whereNotNull('icon')
                ->whereNotNull('product_id')
                ->pluck('icon', 'product_id')
                ->toArray();

            $products = $products->map(fn (array $product) => [
                ...$product,
                'icon' => $iconOverrides[$product['id']] ?? $product['icon'],
            ]);
        }

        $userId = $this->authenticatedUser($request)->id;

        // Wrap non-search props in closures so they are only evaluated
        // when the client does NOT use partial `only: ['searchResults']`.
        return Inertia::render('lists/Show', [
            'list' => fn () => app(GetShoppingList::class)->execute($list, $userId)['list'],
            'categories' => Inertia::optional(fn () => app(GetShoppingList::class)->execute($list, $userId)['categories']),
            'recentProducts' => Inertia::optional(fn () => app(GetShoppingList::class)->execute($list, $userId)['recentProducts']),
            'stores' => Inertia::optional(fn () => app(GetShoppingList::class)->execute($list, $userId)['stores']),
            'household' => $household,
            'searchResults' => $products->values()->all(),
        ]);
    }

    /**
     * Quick-create a product and add it to the list in a single request.
     * Replaces the previous fetch() + router.post() two-step flow.
     *
     * @param  Household  $household  The household model instance.
     * @param  ShoppingList  $list  The shopping list model instance.
     * @param  QuickCreateProductRequest  $request  The incoming HTTP request.
     * @param  QuickCreateProduct  $createAction  The createAction value.
     * @param  AddListItem  $addItemAction  The addItemAction value.
     * @return RedirectResponse The HTTP response.
     */
    public function quickCreate(
        Household $household,
        ShoppingList $list,
        QuickCreateProductRequest $request,
        QuickCreateProduct $createAction,
        AddListItem $addItemAction,
    ): RedirectResponse {
        abort_unless($list->household_id === $household->id, 404);

        return $this->quickCreateForList($list, $request, $createAction, $addItemAction, $household);
    }

    /**
     * Quick-create a product and add it to a personal shopping list.
     */
    public function personalQuickCreate(
        ShoppingList $list,
        QuickCreateProductRequest $request,
        QuickCreateProduct $createAction,
        AddListItem $addItemAction,
    ): RedirectResponse {
        abort_unless($list->household_id === null, 404);

        return $this->quickCreateForList($list, $request, $createAction, $addItemAction);
    }

    private function quickCreateForList(
        ShoppingList $list,
        QuickCreateProductRequest $request,
        QuickCreateProduct $createAction,
        AddListItem $addItemAction,
        ?Household $household = null,
    ): RedirectResponse {
        $this->authorize('view', $list);
        $user = $this->authenticatedUser($request);

        $product = $createAction->execute(
            $request->input('name'),
            $user->id,
        );

        $addItemAction->execute(
            $list,
            ['product_id' => $product->id, 'quantity' => 1],
            $user->id,
        );

        if ($household === null) {
            return to_route('lists.show', $list);
        }

        return to_route('households.lists.show', [$household, $list]);
    }
}
