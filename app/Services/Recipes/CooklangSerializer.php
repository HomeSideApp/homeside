<?php

namespace App\Services\Recipes;

use App\Data\Recipes\RecipeCookwareData;
use App\Data\Recipes\RecipeData;
use App\Data\Recipes\RecipeIngredientData;
use App\Data\Recipes\RecipeStepData;
use App\Data\Recipes\RecipeTimerData;

/**
 * Serializa datos de receta estructurados al formato Cooklang.
 *
 * Si el texto de un paso ya contiene sintaxis Cooklang (@, #, ~),
 * se usa directamente. Si es texto plano, se sintetizan las referencias
 * inline desde las relaciones pivot del paso.
 */
class CooklangSerializer
{
    private CooklangParser $parser;

    public function __construct(CooklangParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Serializa una receta completa a formato Cooklang.
     */
    public function serializeRecipe(RecipeData $recipe): string
    {
        $lines = [];

        // YAML frontmatter
        $lines[] = '---';
        $lines[] = 'title: '.$recipe->name;

        if ($recipe->description) {
            $lines[] = 'description: "'.$recipe->description.'"';
        }

        if (! empty($recipe->tags)) {
            $lines[] = 'tags: ['.implode(', ', $recipe->tags).']';
        }

        if ($recipe->servings) {
            $lines[] = 'servings: '.$recipe->servings;
        }

        if ($recipe->prepTimeSeconds) {
            $lines[] = 'prep time: '.$this->formatDuration($recipe->prepTimeSeconds);
        }

        if ($recipe->cookTimeSeconds) {
            $lines[] = 'cook time: '.$this->formatDuration($recipe->cookTimeSeconds);
        }

        if ($recipe->totalTimeSeconds) {
            $lines[] = 'total time: '.$this->formatDuration($recipe->totalTimeSeconds);
        }

        if ($recipe->difficulty) {
            $lines[] = 'difficulty: '.$recipe->difficulty;
        }

        if ($recipe->cuisine) {
            $lines[] = 'cuisine: '.$recipe->cuisine;
        }

        if ($recipe->collectionPath) {
            $lines[] = 'collection: '.$recipe->collectionPath;
        }

        if ($recipe->author) {
            $lines[] = 'author: '.$recipe->author;
        }

        if ($recipe->sourceUrl) {
            $lines[] = 'source: '.$recipe->sourceUrl;
        }

        $lines[] = '---';
        $lines[] = '';

        // Description as note block
        if ($recipe->description) {
            $lines[] = '> '.$recipe->description;
            $lines[] = '';
        }

        // Group steps by section
        $currentSectionId = null;

        foreach ($recipe->steps as $step) {
            // Section header
            if ($step->section_id !== $currentSectionId && $step->section_id !== null) {
                $section = null;
                foreach ($recipe->sections as $candidate) {
                    if ($candidate->client_id === $step->section_id || $candidate->id === $step->section_id) {
                        $section = $candidate;
                        break;
                    }
                }
                if ($section) {
                    $lines[] = '= '.$section->name;
                    $lines[] = '';
                }
                $currentSectionId = $step->section_id;
            }

            // Step content
            $lines[] = $this->serializeStep($step, $recipe->ingredients, $recipe->cookware);
            $lines[] = '';
        }

        // Notes
        if ($recipe->notes) {
            $lines[] = '-- Notes: '.$recipe->notes;
            $lines[] = '';
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Serializa un paso a texto Cooklang.
     *
     * Si el description ya tiene sintaxis Cooklang, se usa directamente.
     * Si es texto plano, se sintetizan referencias inline desde las relaciones.
     *
     * @param  array<RecipeIngredientData>  $ingredients
     * @param  array<RecipeCookwareData>  $cookware
     */
    public function serializeStep(
        RecipeStepData $step,
        array $ingredients,
        array $cookware,
    ): string {
        $text = $step->description;

        // If the text already has Cooklang syntax, use it as-is
        if ($this->parser->hasCooklangSyntax($text)) {
            return $text;
        }

        // Synthesize inline references from relationships
        $ingRefs = [];
        $cwRefs = [];
        $timerRefs = [];

        // Build inline references for ingredients linked to this step
        $stepIngredientIds = $step->ingredients;

        $linkedIngredients = array_filter(
            $ingredients,
            fn (RecipeIngredientData $ingredient): bool => in_array($ingredient->id, $stepIngredientIds, true)
                || in_array($ingredient->client_id, $stepIngredientIds, true),
        );

        foreach ($linkedIngredients as $ing) {
            $ingRefs[] = $this->serializeIngredient($ing);
        }

        // Build inline references for cookware linked to this step
        $stepCookwareIds = array_map(
            fn (RecipeCookwareData $item): ?string => $item->id ?? $item->client_id,
            $step->cookware,
        );

        $linkedCookware = array_filter(
            $cookware,
            fn (RecipeCookwareData $item): bool => in_array($item->id, $stepCookwareIds, true)
                || in_array($item->client_id, $stepCookwareIds, true),
        );

        foreach ($linkedCookware as $cw) {
            $cwRefs[] = $this->serializeCookware($cw);
        }

        // Build inline references for timers
        if ($step->timers) {
            foreach ($step->timers as $timer) {
                $timerRefs[] = $this->serializeTimer($timer);
            }
        }

        // Build the final text: references at the end of the step
        $result = rtrim($text);

        if (! empty($ingRefs)) {
            $result .= ' '.implode(' ', $ingRefs);
        }

        if (! empty($cwRefs)) {
            $result .= ' '.implode(' ', $cwRefs);
        }

        if (! empty($timerRefs)) {
            $result .= ' '.implode(' ', $timerRefs);
        }

        return $result;
    }

    /**
     * Serializa un ingrediente a sintaxis Cooklang.
     *
     * @example @arroz{200%g}, @ajo{3%cloves}(minced), @sal
     */
    public function serializeIngredient(RecipeIngredientData $ingredient): string
    {
        $name = $ingredient->name;

        if ($ingredient->quantity === null && $ingredient->unit === null) {
            return '@'.$name;
        }

        $qtyStr = $ingredient->quantity !== null ? $this->formatQuantity($ingredient->quantity, $ingredient->unit) : '';

        $notes = $ingredient->preparation ? '('.$ingredient->preparation.')' : '';

        return '@'.$name.'{'.$qtyStr.'}'.$notes;
    }

    /**
     * Serializa un utensilio a sintaxis Cooklang.
     *
     * @example #sartén{}, #olla{2%L}
     */
    public function serializeCookware(RecipeCookwareData $cookware): string
    {
        $name = $cookware->name ?? '';

        if ($cookware->quantity === null && $cookware->unit === null) {
            return '#'.$name.'{}';
        }

        $qtyStr = $cookware->quantity !== null
            ? $cookware->quantity.($cookware->unit ? '%'.$cookware->unit : '')
            : '';

        return '#'.$name.'{'.$qtyStr.'}';
    }

    /**
     * Serializa un temporizador a sintaxis Cooklang.
     *
     * @example ~{5%minutes}, ~reposar{10%min}
     */
    public function serializeTimer(RecipeTimerData $timer): string
    {
        $duration = $this->formatDuration($timer->duration_seconds);

        if ($timer->name) {
            return '~'.$timer->name.'{'.$duration.'}';
        }

        return '~{'.$duration.'}';
    }

    /**
     * Formatea una cantidad con unidad en formato Cooklang.
     *
     * @example "200%g", "3%cloves", "1"
     */
    private function formatQuantity(?float $quantity, ?string $unit): string
    {
        if ($quantity === null) {
            return '';
        }

        $qtyStr = $quantity == (int) $quantity ? (string) (int) $quantity : (string) $quantity;

        if ($unit) {
            return $qtyStr.'%'.$unit;
        }

        return $qtyStr;
    }

    /**
     * Formatea segundos a duración Cooklang.
     *
     * @example "5%minutes", "1%hour", "30%seconds"
     */
    public function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return $hours.'%hour'.($hours > 1 ? 's' : '');
        }

        if ($minutes > 0) {
            return $minutes.'%minute'.($minutes > 1 ? 's' : '');
        }

        return $secs.'%second'.($secs !== 1 ? 's' : '');
    }
}
