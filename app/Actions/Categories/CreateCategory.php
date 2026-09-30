<?php

namespace App\Actions\Categories;

use App\Data\Categories\CreateCategoryData;
use App\Models\Category;
use Illuminate\Support\Str;

/**
 * Creates a new category from the given data.
 */
final class CreateCategory
{
    /**
     * @param  CreateCategoryData  $data  The data for the new category
     * @return Category The Category value.
     */
    public function execute(CreateCategoryData $data): Category
    {
        return Category::create([
            'name' => $data->name,
            'color' => $data->color,
            'is_active' => $data->isActive ?? true,
            'slug' => Str::slug($data->name),
        ]);
    }
}
