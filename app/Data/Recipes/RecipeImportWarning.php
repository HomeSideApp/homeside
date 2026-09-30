<?php

namespace App\Data\Recipes;

/**
 * A non-fatal warning produced while importing a recipe.
 */
final readonly class RecipeImportWarning
{
    public function __construct(
        public string $code,
        public string $message,
        public string $level,
    ) {}
}
