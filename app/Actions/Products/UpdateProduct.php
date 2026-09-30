<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Updates a product, regenerating its slug from the name.
 */
final class UpdateProduct
{
    /**
     * @param  Product  $product  The product to update
     * @param  array<string, mixed>  $data  The product attributes
     * @return Product The Product value.
     */
    public function execute(Product $product, array $data): Product
    {
        $product->update([
            ...$data,
            'slug' => Str::slug($data['name']),
        ]);

        return $product;
    }
}
