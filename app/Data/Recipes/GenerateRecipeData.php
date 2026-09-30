<?php

namespace App\Data\Recipes;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Data transfer object for AI-powered recipe generation.
 */
final readonly class GenerateRecipeData
{
    /**
     * @param  string  $prompt  Prompt libre del usuario
     * @param  string|null  $householdId  ID del household para resolver provider
     * @param  Collection<int, Product>|null  $availableProducts  Productos disponibles para matching
     */
    public function __construct(
        public string $prompt,
        public ?string $householdId = null,
        public ?Collection $availableProducts = null,
    ) {}
}
