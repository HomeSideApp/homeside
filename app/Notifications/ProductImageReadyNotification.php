<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Class ProductImageReadyNotification
 *
 * This notification is sent to a user when a product image is ready.
 * It uses the Queueable trait and is delivered via the database channel.
 */
class ProductImageReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Product $product,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
        ];
    }
}
