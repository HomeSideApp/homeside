<?php

namespace App\Actions\ShoppingLists;

use App\Models\ListItem;
use App\Models\ProductUsage;
use App\Models\ShoppingList;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
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
        // An offline client mints the item id locally and the request validated
        // it as a UUID; the merge below cannot stand in for it, because the row
        // it returns would carry a different identity.
        $clientId = is_string($data['id'] ?? null) && $data['id'] !== '' ? $data['id'] : null;
        unset($data['id']);

        return DB::transaction(function () use ($list, $data, $userId, $clientId): ListItem {
            ShoppingList::query()->whereKey($list->id)->lockForUpdate()->firstOrFail();

            if ($clientId === null && ! empty($data['product_id'])) {
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

            $item = new ListItem($data);
            $this->assignClientId($item, $clientId);

            try {
                $list->items()->save($item);
            } catch (UniqueConstraintViolationException) {
                // The pre-check cannot see a row another device committed in
                // between, so the unique index is the referee for that race.
                throw $this->idTaken();
            }

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
     * Keep the client id, refusing one that a row already owns.
     */
    private function assignClientId(ListItem $model, ?string $clientId): void
    {
        if ($clientId === null) {
            return;
        }

        // A tombstoned row still occupies its id, so the pre-check has to see it.
        if ($model::withTrashed()->whereKey($clientId)->exists()) {
            throw $this->idTaken();
        }

        $model->id = $clientId;
    }

    private function idTaken(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'message' => 'This id is already used by another record.',
            'code' => 'id_taken',
            'errors' => ['id' => ['This id is already used by another record.']],
        ], 422));
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
