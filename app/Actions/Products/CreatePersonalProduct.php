<?php

namespace App\Actions\Products;

use App\Data\Products\CreatePersonalProductData;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a personal (user-owned) product for use in recipes.
 */
final class CreatePersonalProduct
{
    /**
     * @param  CreatePersonalProductData  $data  The product data
     * @param  string  $userId  The id of the user creating the product
     */
    public function execute(CreatePersonalProductData $data, string $userId): Product
    {
        return DB::transaction(function () use ($data, $userId) {
            return Product::create([
                'name' => $data->name,
                'slug' => Str::slug($data->name).'-'.Str::random(5),
                'category_id' => $data->category_id,
                'icon' => $data->icon,
                'is_personal' => true,
                'created_by' => $userId,
            ]);
        });
    }
}
