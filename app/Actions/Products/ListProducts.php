<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lists all products with their images.
 */
final class ListProducts
{
    /**
     * @return Collection<int, Product>
     */
    public function execute(): Collection
    {
        return Product::with('images')->get();
    }
}
