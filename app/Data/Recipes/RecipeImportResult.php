<?php

namespace App\Data\Recipes;

final readonly class RecipeImportResult
{
    /**
     * @param  array<RecipeImportWarning>  $warnings
     * @param  array<array{ingredient: string, status: string, product_id: string|null, product_name: string|null, score: float}>  $candidates
     */
    public function __construct(
        public RecipeData $recipe,
        public array $warnings,
        public ?string $source,
        public array $candidates,
    ) {}
}
