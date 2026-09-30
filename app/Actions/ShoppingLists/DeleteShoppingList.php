<?php

namespace App\Actions\ShoppingLists;

use App\Models\ShoppingList;

/**
 * Deletes a shopping list.
 */
final class DeleteShoppingList
{
    /**
     * @param  ShoppingList  $list  The list to delete
     */
    public function execute(ShoppingList $list): void
    {
        $list->delete();
    }
}
