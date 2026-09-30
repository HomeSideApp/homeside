<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Searches products by name, returning lightweight results with their
 * category.
 */
final class SearchProducts
{
    /**
     * @param  string  $query  The search text
     * @param  string|null  $userId  The user id to include personal products for
     * @return Collection<int, array{id: string, name: string, slug: string, icon: string|null, is_personal: bool, category: array{id: string, name: string, color: string}|null}>
     */
    public function execute(string $query = '', ?string $userId = null): Collection
    {
        if (strlen($query) < 1) {
            return new Collection;
        }

        return Product::withTranslationData()
            ->with(['category' => fn ($query) => $query->withTranslationData()])
            ->where('name', 'LIKE', "%{$query}%")
            ->where(function ($queryBuilder) use ($userId) {
                // Non-personal products are always visible
                $queryBuilder->where('is_personal', false)
                    // Personal products of the user are also visible
                    ->orWhere(function ($q) use ($userId) {
                        $q->where('is_personal', true);
                        if ($userId !== null) {
                            $q->where('created_by', $userId);
                        }
                    });
            })
            ->limit(20)
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->localized('name'),
                'slug' => $product->slug,
                'icon' => $product->icon,
                'is_personal' => $product->is_personal,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->localized('name'),
                    'color' => $product->category->color,
                ] : null,
            ]);
    }
}
