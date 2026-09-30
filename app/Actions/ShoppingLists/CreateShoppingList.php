<?php

namespace App\Actions\ShoppingLists;

use App\Data\ShoppingLists\CreateShoppingListData;
use App\Models\Household;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a shopping list for a user, optionally scoped to a household.
 */
final class CreateShoppingList
{
    /**
     * @param  CreateShoppingListData  $data  The data for the new list
     * @param  User  $actor  The user creating the list
     * @param  Household|null  $household  The household the list belongs to
     * @return ShoppingList The ShoppingList value.
     */
    public function execute(CreateShoppingListData $data, User $actor, ?Household $household = null): ShoppingList
    {
        return DB::transaction(function () use ($data, $actor, $household) {
            return ShoppingList::create([
                'name' => $data->name,
                'created_by' => $actor->id,
                'household_id' => $household?->id,
            ]);
        });
    }
}
