<?php

namespace App\Data\Recipes;

/**
 * Data for a timer attached to a recipe step.
 */
final readonly class RecipeTimerData
{
    public function __construct(
        public ?string $id,
        public ?string $client_id,
        public ?string $name,
        public int $duration_seconds,
        public int $order,
    ) {}
}
