<?php

namespace App\Actions\ShoppingLists;

use App\Models\ListItem;
use App\Models\ShoppingList;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a shopping list and tombstones the items it owned.
 */
final class DeleteShoppingList
{
    /**
     * @param  ShoppingList  $list  The list to delete
     */
    public function execute(ShoppingList $list): void
    {
        DB::transaction(function () use ($list): void {
            // Deleting the list alone would leave its items live but unreachable:
            // every read resolves them through the list. Each item goes through
            // Eloquent so the tombstone also advances `updated_at`, which an
            // `updated_at`-ordered delta cursor needs in order to report the delete.
            $list->items()->get()->each(function (ListItem $item): void {
                $item->delete();
            });

            $list->delete();
        });
    }
}
