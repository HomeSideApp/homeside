<?php

namespace App\Services\Recipes;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Matches free-text ingredient names against the product catalog.
 *
 * Single source of truth for ingredient-to-product matching across the app:
 * importers (Cooklang/JSON-LD) and the AI recipe normalizer all delegate here.
 */
class IngredientMatcher
{
    public const MATCHED = 'MATCHED';

    public const SUGGESTED = 'SUGGESTED';

    public const UNMATCHED = 'UNMATCHED';

    /**
     * Minimum confidence (0-1) for a suggestion to be considered a candidate.
     */
    public const DEFAULT_MIN_SCORE = 0.5;

    /**
     * Match an ingredient name against available products.
     *
     * @param  string  $ingredientName  The ingredient name to match.
     * @param  Collection<int, Product>  $products  Pre-loaded product collection.
     * @return array{status: string, product_id: string|null, product_name: string|null, suggestions: array<int, array{id: string, name: string, score: float}>}
     */
    public function match(string $ingredientName, Collection $products): array
    {
        $normalized = $this->normalize($ingredientName);

        if ($normalized === '') {
            return [
                'status' => self::UNMATCHED,
                'product_id' => null,
                'product_name' => null,
                'suggestions' => [],
            ];
        }

        // 1. Exact match against product name
        foreach ($products as $product) {
            if ($this->normalize($product->name) === $normalized) {
                return [
                    'status' => self::MATCHED,
                    'product_id' => (string) $product->getKey(),
                    'product_name' => $product->name,
                    'suggestions' => [],
                ];
            }
        }

        // 2. Suggest similar products
        $suggestions = $this->findSimilarProducts($normalized, $products);

        if ($suggestions->isNotEmpty()) {
            return [
                'status' => self::SUGGESTED,
                'product_id' => null,
                'product_name' => null,
                'suggestions' => $suggestions->values()->toArray(),
            ];
        }

        return [
            'status' => self::UNMATCHED,
            'product_id' => null,
            'product_name' => null,
            'suggestions' => [],
        ];
    }

    /**
     * Match a batch of ingredient names and build import candidates.
     *
     * Candidates below the confidence threshold are discarded, and the top
     * suggestion becomes the candidate product when no exact match exists.
     *
     * @param  array<int, string>  $ingredientNames  The ingredient names to match.
     * @param  Collection<int, Product>  $products  Pre-loaded product collection.
     * @param  float  $minScore  Minimum confidence (0-1) to keep a candidate.
     * @return array<int, array{ingredient: string, status: string, product_id: string|null, product_name: string|null, score: float}>
     */
    public function matchMany(array $ingredientNames, Collection $products, float $minScore = self::DEFAULT_MIN_SCORE): array
    {
        $candidates = [];

        foreach ($ingredientNames as $name) {
            if (trim($name) === '') {
                continue;
            }

            $match = $this->match($name, $products);

            $score = $match['product_id'] !== null ? 1.0 : ($match['suggestions'][0]['score'] ?? 0.0);

            if ($score < $minScore) {
                continue;
            }

            $candidates[] = [
                'ingredient' => $name,
                'status' => $match['status'],
                'product_id' => $match['product_id'] ?? $match['suggestions'][0]['id'] ?? null,
                'product_name' => $match['product_name'] ?? $match['suggestions'][0]['name'] ?? null,
                'score' => $score,
            ];
        }

        return $candidates;
    }

    /**
     * Load the product catalog used for matching.
     *
     * @return Collection<int, Product>
     */
    public function productsForMatching(int $limit = 200): Collection
    {
        return Product::query()->limit($limit)->get(['id', 'name']);
    }

    /**
     * Find similar products with a similarity score.
     *
     * @return Collection<int, array{id: string, name: string, score: float}>
     */
    private function findSimilarProducts(string $normalized, Collection $products): Collection
    {
        $results = [];

        foreach ($products as $product) {
            $score = $this->similarity($normalized, $this->normalize($product->name));

            if ($score > 0) {
                $results[] = [
                    'id' => (string) $product->getKey(),
                    'name' => $product->name,
                    'score' => $score,
                ];
            }
        }

        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        return collect($results)->take(5);
    }

    /**
     * Score a product name against an ingredient name (0-1).
     *
     * A full containment between both texts is a perfect score; otherwise
     * words are compared one by one using fuzzy similarity.
     */
    private function similarity(string $ingredient, string $productName): float
    {
        if ($ingredient === '' || $productName === '') {
            return 0.0;
        }

        if (str_contains($ingredient, $productName) || str_contains($productName, $ingredient)) {
            return 1.0;
        }

        $ingredientWords = array_values(array_filter(preg_split('/\s+/', $ingredient) ?: [], fn ($w) => $w !== ''));
        $productWords = preg_split('/\s+/', $productName) ?: [];

        if ($ingredientWords === []) {
            return 0.0;
        }

        $matchCount = 0;

        foreach ($ingredientWords as $iw) {
            foreach ($productWords as $pw) {
                if ($iw === $pw || similar_text($iw, $pw) / max(strlen($iw), strlen($pw)) > 0.9) {
                    $matchCount++;
                    break;
                }
            }
        }

        if ($matchCount === 0) {
            return 0.0;
        }

        return round($matchCount / count($ingredientWords), 2);
    }

    /**
     * Normalize a text for comparison.
     */
    private function normalize(string $text): string
    {
        return trim(mb_strtolower(
            preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text,
            'UTF-8',
        ));
    }
}
