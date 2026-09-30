<?php

namespace App\Actions\Categories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lists all categories ordered by their sort order, eager-loading products.
 */
final class ListCategories
{
    /**
     * @return Collection<int, Category>
     */
    public function execute(): Collection
    {
        return Category::orderBy('sort_order')
            ->withTranslationData()
            ->with(['products' => fn ($query) => $query->withTranslationData()])
            ->get();
    }
}
