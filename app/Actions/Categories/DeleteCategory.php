<?php

namespace App\Actions\Categories;

use App\Models\Category;

/**
 * Deletes a category unless it still has unchecked items in lists.
 */
final class DeleteCategory
{
    /**
     * @param  Category  $category  The category to delete
     * @return bool True when the category was deleted, false when it has pending items
     */
    public function execute(Category $category): bool
    {
        $hasPendingItems = $category->listItems()
            ->where('is_checked', false)
            ->exists();

        if ($hasPendingItems) {
            return false;
        }

        $category->delete();

        return true;
    }
}
