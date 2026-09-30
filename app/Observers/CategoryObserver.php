<?php

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

/**
 * Class CategoryObserver
 *
 * This observer invalidates the product category cache whenever a category is
 * saved or deleted.
 */
class CategoryObserver
{
    /**
     * Invalidate the cache after a category is saved.
     *
     * @param  Category  $category  The category model instance.
     */
    public function saved(Category $category): void
    {
        Cache::forget('catalog:categories:products');
    }

    /**
     * Invalidate the cache after a category is deleted.
     *
     * @param  Category  $category  The category model instance.
     */
    public function deleted(Category $category): void
    {
        Cache::forget('catalog:categories:products');
    }
}
