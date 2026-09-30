<?php

namespace App\Actions\ShoppingLists;

use App\Models\ListItem;
use App\Models\ProductUsage;
use App\Models\ShoppingList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Adds an item to a shopping list, inheriting the product image from a
 * previous occurrence and updating product usage tracking.
 */
final class AddListItem
{
    /**
     * @param  ShoppingList  $list  The list to add the item to
     * @param  array<string, mixed>  $data  The item attributes
     * @param  string  $userId  The id of the user adding the item
     * @return ListItem The ListItem value.
     */
    public function execute(ShoppingList $list, array $data, string $userId): ListItem
    {
        return DB::transaction(function () use ($list, $data, $userId): ListItem {
            ShoppingList::query()->whereKey($list->id)->lockForUpdate()->firstOrFail();

            if (! empty($data['product_id'])) {
                $pendingItem = $list->items()
                    ->where('product_id', $data['product_id'])
                    ->where('is_checked', false)
                    ->lockForUpdate()
                    ->oldest()
                    ->first();

                if ($pendingItem !== null) {
                    return $pendingItem->load('product.category', 'store', 'category', 'addedByUser');
                }
            }

            $data['added_by'] = $userId;

            // Inherit the image from the previous item of the same product in this
            // list, so re-adding a purchased product keeps its photo. The file is
            // copied so each item owns its image and replacing one does not break
            // the other's reference.
            if (empty($data['image_url']) && ! empty($data['product_id'])) {
                $previousImage = ListItem::where('list_id', $list->id)
                    ->where('product_id', $data['product_id'])
                    ->whereNotNull('image_url')
                    ->orderByDesc('created_at')
                    ->value('image_url');

                if ($previousImage !== null) {
                    $copiedImage = $this->copyImage($previousImage, $list->id);

                    if ($copiedImage !== null) {
                        $data['image_url'] = $copiedImage;
                    }
                }
            }

            $item = $list->items()->create($data);

            if (! empty($data['product_id'])) {
                ProductUsage::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'product_id' => $data['product_id'],
                        'list_id' => $list->id,
                    ],
                    [
                        'last_used_at' => now(),
                    ]
                );

                ProductUsage::where('user_id', $userId)
                    ->where('product_id', $data['product_id'])
                    ->where('list_id', $list->id)
                    ->increment('usage_count');
            }

            return $item->load('product.category', 'store', 'category', 'addedByUser');
        });
    }

    /**
     * Copy an existing item image so each list item owns its own file.
     */
    private function copyImage(string $previousImage, string $listId): ?string
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($previousImage)) {
            return null;
        }

        $extension = pathinfo($previousImage, PATHINFO_EXTENSION);
        $newPath = 'list-items/'.$listId.'/'.Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');

        return $disk->copy($previousImage, $newPath) ? $newPath : null;
    }
}
