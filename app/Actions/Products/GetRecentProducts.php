<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductUsage;
use Illuminate\Support\Collection;

/**
 * Returns the products most recently used by a user.
 */
final class GetRecentProducts
{
    /**
     * @param  string  $userId  The id of the user
     * @return Collection<int, array{id: string, name: string, slug: string, icon: string|null, category: array{id: string, name: string, color: string}|null}>
     */
    public function execute(string $userId): Collection
    {
        return ProductUsage::with(['product' => fn ($query) => $query->withTranslationData(), 'product.category' => fn ($query) => $query->withTranslationData()])
            ->where('user_id', $userId)
            ->orderByDesc('last_used_at')
            ->limit(20)
            ->get()
            ->pluck('product')
            ->filter()
            ->values()
            ->map(fn (Product $product): array => [
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
    }
}
