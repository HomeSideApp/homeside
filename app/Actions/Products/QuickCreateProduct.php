<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Creates a product from just a name if it does not exist yet.
 */
final class QuickCreateProduct
{
    /**
     * @param  string  $name  The product name
     * @param  string  $userId  The id of the user creating the product
     * @return Product The Product value.
     */
    public function execute(string $name, string $userId): Product
    {
        $existing = Product::query()->visibleToUser($userId)->matchingName($name, exact: true)->first();

        if ($existing !== null) {
            return $existing;
        }

        $slug = Str::slug($name);
        $slugMatch = Product::query()->where('slug', $slug)->first();

        if ($slugMatch !== null) {
            if (! $slugMatch->is_personal || $slugMatch->created_by === $userId) {
                return $slugMatch;
            }

            $slug = Str::limit($slug, 218, '').'-'.Str::uuid();
        }

        return Product::query()->visibleToUser($userId)->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'created_by' => $userId,
            ]
        );
    }
}
