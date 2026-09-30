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
        $slug = Str::slug($name);

        return Product::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'created_by' => $userId,
            ]
        );
    }
}
