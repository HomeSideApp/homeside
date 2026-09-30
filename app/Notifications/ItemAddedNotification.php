<?php

namespace App\Notifications;

use App\Models\ListItem;
use App\Models\ShoppingList;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Class ItemAddedNotification
 *
 * This notification is sent to a user when an item is added to a shopping list.
 * It uses the Queueable trait and is delivered via the database channel.
 */
class ItemAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ListItem $item,
        public ShoppingList $list,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $productName = $this->item->product_id !== null
            ? $this->item->product()->value('name')
            : null;

        return [
            'list_id' => $this->list->id,
            'list_name' => $this->list->name,
            'item_name' => $productName ?? $this->item->custom_name,
        ];
    }
}
