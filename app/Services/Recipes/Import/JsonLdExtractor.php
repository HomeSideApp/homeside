<?php

namespace App\Services\Recipes\Import;

class JsonLdExtractor
{
    /**
     * Extract all JSON-LD Recipe objects from HTML content.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extract(string $html): array
    {
        $dom = new \DOMDocument('UTF-8');
        @$dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);

        $xpath = new \DOMXPath($dom);
        $scripts = $xpath->query('//script[@type="application/ld+json"]');

        if ($scripts === false) {
            return [];
        }

        $recipes = [];

        /** @var \DOMElement $script */
        foreach ($scripts as $script) {
            $json = $script->textContent;
            $decoded = json_decode($json, true);

            if (! is_array($decoded)) {
                continue;
            }

            $found = $this->extractRecipesFromNode($decoded);
            $recipes = array_merge($recipes, $found);
        }

        return $recipes;
    }

    /**
     * Find all @type values from JSON-LD blocks in the HTML.
     *
     * @return array<int, string>
     */
    public function findJsonLdTypes(string $html): array
    {
        $dom = new \DOMDocument('UTF-8');
        @$dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);

        $xpath = new \DOMXPath($dom);
        $scripts = $xpath->query('//script[@type="application/ld+json"]');

        if ($scripts === false) {
            return [];
        }

        $types = [];

        /** @var \DOMElement $script */
        foreach ($scripts as $script) {
            $decoded = json_decode($script->textContent, true);
            if (is_array($decoded)) {
                $extracted = $this->collectTypes($decoded);
                $types = array_merge($types, $extracted);
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * Recursively collect @type values from a JSON-LD node.
     *
     * @param  array<string, mixed>  $node
     * @return array<int, string>
     */
    private function collectTypes(array $node): array
    {
        $types = [];

        $type = $node['@type'] ?? null;
        if (is_string($type)) {
            $types[] = $type;
        } elseif (is_array($type)) {
            foreach ($type as $t) {
                if (is_string($t)) {
                    $types[] = $t;
                }
            }
        }

        if (isset($node['@graph']) && is_array($node['@graph'])) {
            foreach ($node['@graph'] as $item) {
                if (is_array($item)) {
                    $types = array_merge($types, $this->collectTypes($item));
                }
            }
        }

        return $types;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<int, array<string, mixed>>
     */
    private function extractRecipesFromNode(array $node): array
    {
        $recipes = [];

        // Check if this node is a Recipe or contains one
        $type = $node['@type'] ?? null;
        if (is_string($type) && str_contains(strtolower($type), 'recipe')) {
            $recipes[] = $node;
        } elseif (is_array($type)) {
            foreach ($type as $t) {
                if (is_string($t) && str_contains(strtolower($t), 'recipe')) {
                    $recipes[] = $node;
                    break;
                }
            }
        }

        // Check @graph for nested recipes
        if (isset($node['@graph']) && is_array($node['@graph'])) {
            foreach ($node['@graph'] as $item) {
                if (is_array($item)) {
                    $recipes = array_merge($recipes, $this->extractRecipesFromNode($item));
                }
            }
        }

        // Check if this is a container (e.g., WebPage with recipe)
        if (empty($recipes)) {
            // Look for recipe-related keys
            foreach (['recipe', 'mainEntity'] as $key) {
                if (isset($node[$key])) {
                    if (is_array($node[$key])) {
                        if (isset($node[$key]['@type']) && is_string($node[$key]['@type']) && str_contains(strtolower($node[$key]['@type']), 'recipe')) {
                            $recipes[] = $node[$key];
                        } else {
                            $recipes = array_merge($recipes, $this->extractRecipesFromNode($node[$key]));
                        }
                    } elseif (is_string($node[$key])) {
                        // String content — treat as single recipe with text instructions
                        $singleRecipe = [
                            'recipeInstructions' => $node[$key],
                        ];
                        // Copy other fields from parent
                        foreach ($node as $k => $v) {
                            if ($k !== $key) {
                                $singleRecipe[$k] = $v;
                            }
                        }
                        $recipes[] = $singleRecipe;
                    }
                }
            }
        }

        // If this is an array of items
        if (empty($recipes) && isset($node['recipeInstructions']) && is_array($node['recipeInstructions'])) {
            foreach ($node['recipeInstructions'] as $item) {
                if (is_array($item)) {
                    $recipes[] = $item;
                }
            }
        }

        return $recipes;
    }
}
