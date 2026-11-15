<?php

namespace App\Actions\ShoppingLists;

use App\Models\ListItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;

/**
 * Updates a list item, resizing and converting any uploaded image to WebP.
 */
final class UpdateListItem
{
    /**
     * @param  ListItem  $item  The item to update
     * @param  array<string, mixed>  $data  The item attributes
     * @return ListItem The ListItem value.
     */
    public function execute(ListItem $item, array $data): ListItem
    {
        if (isset($data['image_url']) && $data['image_url'] instanceof UploadedFile) {
            $path = Image::fromUpload($data['image_url'])
                // Phones record rotation in EXIF.
                ->orient()
                // Proportional fit inside 1024x1024. `resize(1024)` forced the
                // width and left the height alone: a 3000x4000 portrait photo
                // came out as 1024x4000 — squashed, and still half a megabyte.
                ->scale(1024, 1024)
                ->toWebp()
                ->quality(80)
                ->store('list-items/'.$item->list_id, 'local');

            if ($path) {
                if ($item->image_url && Storage::disk('local')->exists($item->image_url)) {
                    Storage::disk('local')->delete($item->image_url);
                }
                $data['image_url'] = $path;
            } else {
                unset($data['image_url']);
            }
        }

        $item->update($data);

        $item->refresh();

        return $item->load('product.category', 'store', 'category', 'addedByUser');
    }
}
