<?php

namespace App\Actions\ShoppingLists;

use App\Actions\Products\QuickCreateProduct;
use App\Models\ListItem;
use App\Models\ShoppingList;
use Illuminate\Support\Facades\DB;

final class QuickCreateListItem
{
    public function __construct(
        private QuickCreateProduct $createProduct,
        private AddListItem $addListItem,
    ) {}

    /**
     * @param  array{product: array{name: string, category_id?: string|null, icon?: string|null}, item: array<string, mixed>}  $data
     */
    public function execute(ShoppingList $list, array $data, string $userId): ListItem
    {
        return DB::transaction(function () use ($list, $data, $userId): ListItem {
            $product = $this->createProduct->execute($data['product']['name'], $userId);
            $product->fill([
                'category_id' => $data['product']['category_id'] ?? $product->category_id,
                'icon' => $data['product']['icon'] ?? $product->icon,
            ])->save();

            return $this->addListItem->execute($list, [
                ...$data['item'],
                'product_id' => $product->id,
                'category_id' => $data['product']['category_id'] ?? null,
                'icon' => $data['product']['icon'] ?? null,
            ], $userId);
        });
    }
}
