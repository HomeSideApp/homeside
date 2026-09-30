<?php

namespace App\Http\Controllers;

use App\Actions\Products\QuickCreateProduct;
use App\Actions\Products\SearchProducts;
use App\Http\Requests\QuickCreateProductRequest;
use App\Models\ListItem;
use App\Models\ProductUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles product search, recent products and quick creation.
 *
 * Serves the lightweight JSON endpoints under /api/products/* used by the
 * web frontend (Wayfinder actions). Business logic lives in the shared
 * SearchProducts / QuickCreateProduct actions.
 */
class ProductSearchController extends Controller
{
    /**
     * Search products by name, applying per-list icon overrides.
     */
    public function search(SearchProducts $action, Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 1) {
            return response()->json([]);
        }

        $userId = $this->authenticatedUser($request)->id;
        $products = $action->execute($query, $userId);

        // Apply icon overrides from list items if list_id is provided
        if ($listId = $request->input('list_id')) {
            $iconOverrides = ListItem::where('list_id', $listId)
                ->whereNotNull('icon')
                ->whereNotNull('product_id')
                ->pluck('icon', 'product_id')
                ->toArray();

            $products = $products->map(fn (array $product) => [
                ...$product,
                'icon' => $iconOverrides[$product['id']] ?? $product['icon'],
            ]);
        }

        return response()->json($products->values()->all());
    }

    /**
     * Return the products most recently used by the user.
     */
    public function recent(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $usages = ProductUsage::with(['product' => fn ($query) => $query->withTranslationData(), 'product.category' => fn ($query) => $query->withTranslationData()])
            ->where('user_id', $user->id)
            ->orderByDesc('last_used_at')
            ->limit(20)
            ->get()
            ->pluck('product')
            ->filter()
            ->values()
            ->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->localized('name'),
                'slug' => $product->slug,
                'icon' => $product->icon,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->localized('name'),
                    'color' => $product->category->color,
                ] : null,
            ]);

        return response()->json($usages);
    }

    /**
     * Quickly create a product from just a name.
     */
    public function quickCreate(QuickCreateProductRequest $request, QuickCreateProduct $action): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $product = $action->execute($request->input('name'), $user->id);

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'icon' => $product->icon,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'color' => $product->category->color,
            ] : null,
        ]);
    }
}
