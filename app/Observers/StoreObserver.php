<?php

namespace App\Observers;

use App\Models\Store;
use Illuminate\Support\Facades\Cache;

/**
 * Class StoreObserver
 *
 * This observer invalidates the store catalogue cache whenever a store is saved
 * or deleted.
 */
class StoreObserver
{
    /**
     * Invalidate the cache after a store is saved.
     *
     * @param  Store  $store  The store model instance.
     */
    public function saved(Store $store): void
    {
        Cache::forget('catalog:stores');
    }

    /**
     * Invalidate the cache after a store is deleted.
     *
     * @param  Store  $store  The store model instance.
     */
    public function deleted(Store $store): void
    {
        Cache::forget('catalog:stores');
    }
}
