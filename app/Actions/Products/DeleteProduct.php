<?php

namespace App\Actions\Products;

use App\Models\Product;

/**
 * Deletes a product unless it still has unchecked items in lists.
 */
final class DeleteProduct
{
    /**
     * @param  Product  $product  The product to delete
     * @return bool True when the product was deleted, false when it has pending items
     */
    public function execute(Product $product): bool
    {
        $hasPendingItems = $product->listItems()
            ->where('is_checked', false)
            ->exists();

        if ($hasPendingItems) {
            return false;
        }

        $product->delete();

        return true;
    }
}
