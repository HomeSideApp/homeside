<?php

namespace App\Concerns;

use App\Models\Household;
use App\Models\ListItem;
use App\Models\ShoppingList;

trait AuthorizesShoppingListRoute
{
    public function shoppingList(): ShoppingList
    {
        $list = $this->route('list');
        if (! $list instanceof ShoppingList) {
            $list = ShoppingList::query()->whereKey($list)->firstOrFail();
            $this->route()?->setParameter('list', $list);
        }

        $household = $this->route('household');
        $householdId = $household instanceof Household ? $household->id : $household;
        abort_unless($list->household_id === $householdId, 404);

        return $list;
    }

    public function listItem(): ListItem
    {
        $item = $this->route('item');
        if (! $item instanceof ListItem) {
            $item = ListItem::query()->whereKey($item)->firstOrFail();
            $this->route()?->setParameter('item', $item);
        }

        abort_unless($item->list_id === $this->shoppingList()->id, 404);

        return $item;
    }
}
