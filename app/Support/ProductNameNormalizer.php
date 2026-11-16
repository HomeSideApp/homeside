<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Builds the comparison key used to group product names that only differ in
 * case, accents or surrounding/repeated whitespace.
 *
 * Accents are transliterated so "Café con leche" and "cafe  CON leche" share
 * one key. `IngredientMatcher` uses its own fuzzy normaliser, which keeps
 * accents, so this helper is the exact (grouping) counterpart.
 */
final class ProductNameNormalizer
{
    public static function normalize(string $name): string
    {
        return Str::squish(Str::lower(Str::ascii($name)));
    }
}
