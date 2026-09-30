<?php

namespace App\Actions\ShoppingLists;

use App\Models\Category;
use App\Models\ListItem;
use App\Models\Product;
use App\Models\ProductUsage;
use App\Models\ShoppingList;
use App\Models\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Loads a shopping list with its items, categories and product icon
 * overrides, ready for rendering.
 */
final class GetShoppingList
{
    /**
     * @param  ShoppingList  $list  The list to load
     * @param  string  $userId  The id of the requesting user
     * @return array<string, mixed>
     */
    public function execute(ShoppingList $list, string $userId): array
    {
        $list->load([
            'items.product' => fn ($query) => $query->withTranslationData(),
            'items.product.category' => fn ($query) => $query->withTranslationData(),
            'items.store' => fn ($query) => $query->withTranslationData(),
            'items.category' => fn ($query) => $query->withTranslationData(),
            'items.addedByUser',
        ]);

        // Build icon overrides map from list items with custom icons
        $iconOverrides = $list->items
            ->filter(fn (ListItem $item): bool => $item->icon !== null && $item->product_id !== null)
            ->pluck('icon', 'product_id')
            ->toArray();

        // Query categories fresh — need plain arrays to apply per-list icon overrides safely
        // Exclude personal products from regular categories (they appear in the virtual "Mis productos" category)
        $categories = Category::orderBy('sort_order')
            ->withTranslationData()
            ->with(['products' => function ($query) {
                $query->where('is_personal', false)
                    ->withTranslationData()
                    ->with(['category' => fn ($categoryQuery) => $categoryQuery->withTranslationData()]);
            }])
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'slug' => $category->slug,
                'icon' => $category->icon,
                'color' => $category->color,
                'name' => $category->localized('name'),
                'products' => $category->products->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->localized('name'),
                    'slug' => $product->slug,
                    'icon' => $iconOverrides[$product->id] ?? $product->icon,
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->localized('name'),
                        'color' => $product->category->color,
                    ] : null,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        // Add personal products category at the end
        $personalProducts = $this->getPersonalProducts($list->id, $userId);
        if ($personalProducts->isNotEmpty()) {
            $categories[] = [
                'id' => 'personal',
                'slug' => 'personal-products',
                'icon' => 'user',
                'color' => '#7C3AED',
                'name' => 'Mis productos',
                'products' => $personalProducts->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->localized('name'),
                    'slug' => $product->slug,
                    'icon' => $iconOverrides[$product->id] ?? $product->icon,
                    'is_personal' => true,
                    'category' => null,
                ])->values()->all(),
            ];
        }

        $recentProducts = ProductUsage::with(['product' => fn ($query) => $query->withTranslationData(), 'product.category' => fn ($query) => $query->withTranslationData()])
            ->where('user_id', $userId)
            ->where('list_id', $list->id)
            ->orderByDesc('last_used_at')
            ->limit(20)
            ->get()
            ->pluck('product')
            ->filter()
            ->values()
            ->each(function (Product $product) use ($iconOverrides): void {
                if (isset($iconOverrides[$product->id])) {
                    $product->icon = $iconOverrides[$product->id];
                }
            });

        $stores = Cache::remember('catalog:stores', now()->addMinutes(60), function () {
            return Store::query()->withTranslationData()->get()->values()->all();
        });

        return [
            'list' => $list,
            'categories' => $categories,
            'recentProducts' => $recentProducts,
            'stores' => $stores,
        ];
    }

    /**
     * Get personal products visible to the user in this list.
     *
     * Shows the user's own personal products plus any personal products
     * that have been added to this list by other household members.
     *
     * @return Collection<int, Product>
     */
    /**
     * Get personal products visible to the user in this list.
     *
     * Only shows personal products that are used in recipes shared with the household,
     * or personal products already added to this list.
     *
     * @return Collection<int, Product>
     */
    private function getPersonalProducts(string $listId, string $userId): Collection
    {
        $list = ShoppingList::find($listId);

        return Product::where('is_personal', true)
            ->withTranslationData()
            ->where(function ($query) use ($list, $listId) {
                // Personal products used in recipes shared with this list's household
                if ($list?->household_id) {
                    $query->whereIn('id', function ($subQuery) use ($list) {
                        $subQuery->select('product_id')
                            ->from('recipe_ingredients')
                            ->whereNotNull('product_id')
                            ->whereIn('recipe_id', function ($recipeQuery) use ($list) {
                                $recipeQuery->select('recipe_id')
                                    ->from('household_recipes')
                                    ->where('household_id', $list->household_id);
                            });
                    });
                }

                // Or personal products already in this list
                $query->orWhereIn('id', function ($subQuery) use ($listId) {
                    $subQuery->select('product_id')
                        ->from('list_items')
                        ->where('list_id', $listId)
                        ->whereNotNull('product_id');
                });
            })
            ->get();
    }
}
