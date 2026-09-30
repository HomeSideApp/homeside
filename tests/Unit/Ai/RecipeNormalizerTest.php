<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Ai\Execution\RecipeNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RecipeNormalizerTest extends TestCase
{
    #[Test]
    public function it_rejects_empty_or_incomplete_recipe_content(): void
    {
        $this->assertFalse(RecipeNormalizer::hasRequiredContent([]));
        $this->assertFalse(RecipeNormalizer::hasRequiredContent(['name' => 'Carbonara']));
        $this->assertFalse(RecipeNormalizer::hasRequiredContent([
            'name' => 'Carbonara',
            'ingredients' => [],
            'steps' => [],
        ]));
    }

    #[Test]
    public function it_accepts_recipe_content_with_name_ingredients_and_steps(): void
    {
        $this->assertTrue(RecipeNormalizer::hasRequiredContent([
            'name' => 'Carbonara',
            'ingredients' => [['name' => 'Espaguetis']],
            'steps' => [['description' => 'Cocer la pasta']],
        ]));
    }

    #[Test]
    public function it_preserves_client_ids_and_step_relationships(): void
    {
        $recipe = RecipeNormalizer::normalizeRecipe([
            'name' => 'Tortilla de patatas',
            'ingredients' => [[
                'client_id' => 'ing-1',
                'name' => 'patatas',
            ]],
            'steps' => [[
                'client_id' => 'step-1',
                'description' => 'Freír @patatas{500%g} en la #sartén.',
                'ingredients' => ['ing-1'],
                'cookware' => ['cook-1'],
            ]],
            'cookware' => [[
                'client_id' => 'cook-1',
                'name' => 'Sartén',
            ]],
        ]);

        $this->assertSame('ing-1', $recipe['ingredients'][0]['client_id']);
        $this->assertSame('step-1', $recipe['steps'][0]['client_id']);
        $this->assertSame('cook-1', $recipe['cookware'][0]['client_id']);
        $this->assertSame(['ing-1'], $recipe['steps'][0]['ingredients']);
        $this->assertSame(['cook-1'], $recipe['steps'][0]['cookware']);
    }

    #[Test]
    public function it_accepts_plain_step_descriptions_with_valid_client_id_relationships(): void
    {
        $errors = RecipeNormalizer::validationErrors([
            'name' => 'Pasta',
            'ingredients' => [['client_id' => 'ing-1', 'name' => 'Pasta']],
            'cookware' => [['client_id' => 'cook-1', 'name' => 'Olla']],
            'steps' => [[
                'description' => 'Cocer la pasta en la olla durante diez minutos.',
                'ingredients' => ['ing-1'],
                'cookware' => ['cook-1'],
                'timers' => [['client_id' => 'timer-1', 'name' => 'cocción', 'duration_seconds' => 600]],
            ]],
        ]);

        $this->assertSame([], $errors);
    }

    #[Test]
    public function it_rejects_nonexistent_relationship_ids_without_enforcing_cooklang(): void
    {
        $errors = RecipeNormalizer::validationErrors([
            'name' => 'Pasta',
            'ingredients' => [['client_id' => 'ing-1', 'name' => 'Pasta']],
            'cookware' => [['client_id' => 'cook-1', 'name' => 'Olla']],
            'steps' => [[
                'description' => 'Cocer la pasta en la olla durante diez minutos.',
                'ingredients' => ['ing-404'],
                'cookware' => ['#olla'],
                'timers' => [['client_id' => 'timer-1', 'name' => 'cocción', 'duration_seconds' => 600]],
            ]],
        ]);

        $this->assertCount(2, $errors);
        $this->assertStringContainsString('ingrediente inexistente', $errors[0]);
        $this->assertStringContainsString('utensilio inexistente', $errors[1]);
    }

    #[Test]
    public function it_keeps_the_linked_recipe_references_of_the_agent_response(): void
    {
        $recipe = RecipeNormalizer::normalizeRecipe([
            'name' => 'Espaguetis con salsa boloñesa',
            'ingredients' => [['client_id' => 'ing-1', 'name' => 'espaguetis']],
            'steps' => [[
                'client_id' => 'step-1',
                'description' => 'Mezclar @espaguetis{400%g} con @./Salsas/Boloñesa{4%raciones}.',
            ]],
            'recipe_references' => [
                ['path' => './Salsas/Boloñesa', 'recipe_id' => 'sauce-uuid'],
            ],
        ]);

        $this->assertSame(
            [['path' => './Salsas/Boloñesa', 'recipe_id' => 'sauce-uuid']],
            $recipe['recipe_references'],
        );
    }

    #[Test]
    public function it_normalizes_missing_references_to_an_empty_list(): void
    {
        $recipe = RecipeNormalizer::normalizeRecipe([
            'name' => 'Tortilla',
            'ingredients' => [['client_id' => 'ing-1', 'name' => 'patatas']],
            'steps' => [['client_id' => 'step-1', 'description' => 'Freír @patatas{500%g}.']],
        ]);

        $this->assertSame([], $recipe['recipe_references']);
    }

    #[Test]
    public function it_rejects_a_reference_without_a_path(): void
    {
        $errors = RecipeNormalizer::validationErrors([
            'name' => 'Espaguetis',
            'ingredients' => [['client_id' => 'ing-1', 'name' => 'espaguetis']],
            'steps' => [['client_id' => 'step-1', 'description' => 'Mezclar.', 'ingredients' => ['ing-1']]],
            'recipe_references' => [['recipe_id' => 'sauce-uuid']],
        ]);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('ruta válida', $errors[0]);
    }

    #[Test]
    public function it_accepts_a_reference_with_a_path(): void
    {
        $errors = RecipeNormalizer::validationErrors([
            'name' => 'Espaguetis',
            'ingredients' => [['client_id' => 'ing-1', 'name' => 'espaguetis']],
            'steps' => [['client_id' => 'step-1', 'description' => 'Mezclar.', 'ingredients' => ['ing-1']]],
            'recipe_references' => [['path' => './Salsas/Boloñesa', 'recipe_id' => 'sauce-uuid']],
        ]);

        $this->assertSame([], $errors);
    }
}
