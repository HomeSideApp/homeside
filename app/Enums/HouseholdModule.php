<?php

namespace App\Enums;

/**
 * Modules that can be enabled for a household.
 */
enum HouseholdModule: string
{
    case ShoppingLists = 'shopping_lists';
    case Recipes = 'recipes';
    case Economy = 'economy';

    /**
     * Get the human-readable label for the module.
     *
     * @return string A string value.
     */
    public function label(): string
    {
        return match ($this) {
            self::ShoppingLists => 'Shopping lists',
            self::Recipes => 'Recipes',
            self::Economy => 'Economy',
        };
    }

    /**
     * Get a short description of what the module provides.
     *
     * @return string A string value.
     */
    public function description(): string
    {
        return match ($this) {
            self::ShoppingLists => 'Manage shared shopping lists',
            self::Recipes => 'Manage recipes and generate lists from them',
            self::Economy => 'Analyse receipts and manage expenses',
        };
    }

    /**
     * Get the icon name for the module.
     *
     * @return string A string value.
     */
    public function icon(): string
    {
        return match ($this) {
            self::ShoppingLists => 'ShoppingCart',
            self::Recipes => 'ChefHat',
            self::Economy => 'Receipt',
        };
    }
}
