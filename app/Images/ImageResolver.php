<?php

declare(strict_types=1);

namespace App\Images;

use App\Enums\ImageType;
use App\Images\Contracts\ImageTypeStrategy;
use App\Images\Strategies\GeneratedImage;
use App\Images\Strategies\HouseholdImage;
use App\Images\Strategies\ListItemImage;
use App\Images\Strategies\RecipeImage;

/**
 * Resolves the appropriate image strategy based on image type.
 */
final class ImageResolver
{
    private const STRATEGIES = [
        'household' => HouseholdImage::class,
        'list_item' => ListItemImage::class,
        'recipe' => RecipeImage::class,
        'recipe_step' => RecipeImage::class,
        'generated' => GeneratedImage::class,
    ];

    /**
     * Resolve the image strategy for the given type.
     *
     * @param  ImageType  $type  The image type.
     * @param  string|null  $stepId  Optional step ID for recipe step images.
     *
     * @throws \InvalidArgumentException If the image type is unknown.
     */
    public function resolve(ImageType $type, ?string $stepId = null): ImageTypeStrategy
    {
        $class = self::STRATEGIES[$type->value] ?? null;

        if ($class === null) {
            throw new \InvalidArgumentException("Unknown image type: {$type->value}");
        }

        if ($type === ImageType::RecipeStep) {
            return new $class(stepId: $stepId);
        }

        return new $class;
    }
}
