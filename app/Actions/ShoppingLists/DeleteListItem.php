<?php

namespace App\Actions\ShoppingLists;

use App\Models\ListItem;

/**
 * Deletes a list item.
 */
final class DeleteListItem
{
    /**
     * @param  ListItem  $item  The item to delete
     */
    public function execute(ListItem $item): void
    {
        $item->delete();
    }
}
