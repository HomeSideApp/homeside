<?php

namespace App\Actions\Recipes;

use App\Data\Recipes\AddToListData;
use App\Models\ListItem;
use App\Models\Recipe;
use App\Models\ShoppingList;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Adds a recipe's ingredients, scaled to the requested servings, into a
 * shopping list.
 */
final class AddToShoppingList
{
    /**
     * @param  Recipe  $recipe  The recipe to add
     * @param  ShoppingList  $list  The destination shopping list
     * @param  AddToListData  $data  The scaling data
     * @param  User  $user  The user adding the ingredients
     * @return Collection<int, ListItem>
     */
    public function execute(Recipe $recipe, ShoppingList $list, AddToListData $data, User $user): Collection
    {
        $factor = $recipe->servings > 0
            ? $data->target_servings / $recipe->servings
            : 1;

        /** @var Collection<int, ListItem> $items */
        $items = new Collection;

        DB::transaction(function () use ($recipe, $list, $user, $factor, $items) {
            $this->addRecipeIngredients($recipe, $list, $user, $factor, $items, []);
        });

        return $items->load('product');
    }

    /**
     * @param  Collection<int, ListItem>  $items
     * @param  array<string, true>  $ancestors
     */
    /**
     * Recursively add the ingredients of a recipe, following its references,
     * while guarding against reference cycles.
     *
     * @param  Collection<int, ListItem>  $items
     * @param  array<string, true>  $ancestors
     */
    private function addRecipeIngredients(
        Recipe $recipe,
        ShoppingList $list,
        User $user,
        float $factor,
        Collection $items,
        array $ancestors,
    ): void {
        if (isset($ancestors[$recipe->id])) {
            return;
        }

        $ancestors[$recipe->id] = true;
        $recipe->loadMissing(['ingredients', 'references.referencedRecipe']);

        foreach ($recipe->ingredients as $ingredient) {
            if ($ingredient->optional) {
                continue;
            }

            $items->push(ListItem::create([
                'list_id' => $list->id,
                'product_id' => $ingredient->product_id,
                'custom_name' => $ingredient->product_id ? null : $ingredient->name,
                'quantity' => $ingredient->quantity !== null ? round($ingredient->quantity * $factor, 2) : null,
                'unit' => $ingredient->unit,
                'is_checked' => false,
                'notes' => $ingredient->notes,
                'added_by' => $user->id,
                'source_type' => 'recipe',
                'source_id' => $recipe->id,
                'original_quantity' => $ingredient->quantity,
                'original_unit' => $ingredient->unit,
                'scaled_servings' => $recipe->servings !== null ? max(1, (int) round($recipe->servings * $factor)) : null,
            ]));
        }

        foreach ($recipe->references as $reference) {
            $referencedRecipe = $reference->referencedRecipe;
            if ($referencedRecipe === null) {
                continue;
            }

            $referenceFactor = match (mb_strtolower($reference->unit ?? '')) {
                'serving', 'servings', 'porción', 'porciones' => $referencedRecipe->servings > 0
                    ? ($reference->quantity ?? $referencedRecipe->servings) / $referencedRecipe->servings
                    : 1,
                default => $reference->quantity ?? 1,
            };

            $this->addRecipeIngredients(
                $referencedRecipe,
                $list,
                $user,
                $factor * $referenceFactor,
                $items,
                $ancestors,
            );
        }
    }
}
