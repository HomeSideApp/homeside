<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a product, generating its slug from the name.
 */
final class CreateProduct
{
    /**
     * @param  array<string, mixed>  $data  The product attributes
     * @param  string  $userId  The id of the user creating the product
     * @return Product The Product value.
     */
    public function execute(array $data, string $userId): Product
    {
        return DB::transaction(function () use ($data, $userId) {
            return Product::create([
                ...$data,
                'slug' => Str::slug($data['name']),
                'created_by' => $userId,
            ]);
        });
    }
}
