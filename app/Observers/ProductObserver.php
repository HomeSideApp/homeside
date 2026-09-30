<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * Class ProductObserver
 *
 * This observer invalidates the product category cache whenever a product is
 * saved or deleted.
 */
class ProductObserver
{
    /**
     * Invalidate the cache after a product is saved.
     *
     * @param  Product  $product  The product model instance.
     */
    public function saved(Product $product): void
    {
        Cache::forget('catalog:categories:products');
    }

    /**
     * Invalidate the cache after a product is deleted.
     *
     * @param  Product  $product  The product model instance.
     */
    public function deleted(Product $product): void
    {
        Cache::forget('catalog:categories:products');
    }
}
