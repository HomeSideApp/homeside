<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum representing the different types of images in the application.
 */
enum ImageType: string
{
    case Household = 'household';
    case ListItem = 'list_item';
    case Recipe = 'recipe';
    case RecipeStep = 'recipe_step';
    case Generated = 'generated';
}
