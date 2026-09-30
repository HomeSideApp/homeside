<?php

namespace App\Actions\Categories;

use App\Data\Categories\UpdateCategoryData;
use App\Models\Category;
use Illuminate\Support\Str;

/**
 * Updates the name, color, active state and slug of a category.
 */
final class UpdateCategory
{
    /**
     * @param  Category  $category  The category to update
     * @param  UpdateCategoryData  $data  The data for the update
     * @return Category The Category value.
     */
    public function execute(Category $category, UpdateCategoryData $data): Category
    {
        $category->update([
            'name' => $data->name,
            'color' => $data->color,
            'is_active' => $data->isActive ?? $category->is_active,
            'slug' => Str::slug($data->name),
        ]);

        return $category;
    }
}
