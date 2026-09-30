<?php

namespace App\Actions\ShoppingLists;

use App\Data\ShoppingLists\UpdateShoppingListData;
use App\Models\ShoppingList;

/**
 * Updates the name of an existing shopping list.
 */
final class UpdateShoppingList
{
    /**
     * @param  ShoppingList  $list  The list to update
     * @param  UpdateShoppingListData  $data  The data for the update
     * @return ShoppingList The ShoppingList value.
     */
    public function execute(ShoppingList $list, UpdateShoppingListData $data): ShoppingList
    {
        $list->update([
            'name' => $data->name,
        ]);

        $list->refresh();

        return $list;
    }
}
