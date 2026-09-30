<?php

declare(strict_types=1);

namespace App\Images\Strategies;

use App\Images\Contracts\ImageTypeStrategy;
use App\Models\Recipe;
use App\Models\User;

/**
 * Strategy for recipe images (cover and step images).
 */
final class RecipeImage implements ImageTypeStrategy
{
    public function __construct(
        private readonly ?string $stepId = null
    ) {}

    /**
     * {@inheritdoc}
     */
    public function resolvePath(string $uuid): ?string
    {
        $recipe = Recipe::find($uuid);

        if (! $recipe) {
            return null;
        }

        if ($this->stepId) {
            return $recipe->steps()
                ->where('id', $this->stepId)
                ->value('image_path');
        }

        return $recipe->cover_image_path;
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(User $user, string $uuid): bool
    {
        $recipe = Recipe::find($uuid);

        return $recipe && $user->can('view', $recipe);
    }

    /**
     * {@inheritdoc}
     */
    public function disk(): string
    {
        return 'local';
    }
}
