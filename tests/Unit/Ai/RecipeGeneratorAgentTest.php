<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Ai\Agents\RecipeGeneratorAgent;
use App\Ai\Contracts\AcceptsRuntimeInstructions;
use App\Ai\Contracts\HomeSideAgent;
use HomeSide\AiAgents\Configuration\Capability;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\ObjectSchema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecipeGeneratorAgentTest extends TestCase
{
    private RecipeGeneratorAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = new RecipeGeneratorAgent;
    }

    #[Test]
    public function implements_agent_interface(): void
    {
        $this->assertInstanceOf(Agent::class, $this->agent);
    }

    #[Test]
    public function implements_has_structured_output(): void
    {
        $this->assertInstanceOf(HasStructuredOutput::class, $this->agent);
    }

    #[Test]
    public function implements_home_side_agent(): void
    {
        $this->assertInstanceOf(HomeSideAgent::class, $this->agent);
    }

    #[Test]
    public function accepts_composed_runtime_instructions(): void
    {
        $this->assertInstanceOf(AcceptsRuntimeInstructions::class, $this->agent);

        $this->agent->useRuntimeInstructions('Instrucciones compuestas del sistema.');

        $this->assertSame(
            'Instrucciones compuestas del sistema.',
            $this->agent->instructions(),
        );
    }

    #[Test]
    public function key_is_correct(): void
    {
        $this->assertSame('recipes.recipe_generator', $this->agent->key());
    }

    #[Test]
    public function module_is_recipes(): void
    {
        $this->assertSame('recipes', $this->agent->module());
    }

    #[Test]
    public function version_is_one(): void
    {
        $this->assertSame(1, $this->agent->version());
    }

    /**
     * Tool-calling is deliberately not used: some OpenAI-compatible endpoints
     * echo the tool definition into the structured payload instead of invoking
     * it. Existing recipes travel as plain prompt context instead.
     */
    #[Test]
    public function does_not_declare_tool_calling(): void
    {
        $this->assertNotInstanceOf(HasTools::class, $this->agent);
        $this->assertSame(1, $this->agent->maxSteps());
        $this->assertNotContains(Capability::Tools, $this->agent->requiredCapabilities());
    }

    #[Test]
    public function required_capabilities_include_text_and_structured_output(): void
    {
        $caps = $this->agent->requiredCapabilities();

        $this->assertContains(Capability::Text, $caps);
        $this->assertContains(Capability::StructuredOutput, $caps);
    }

    #[Test]
    public function default_configuration_has_instructions(): void
    {
        $config = $this->agent->defaultConfiguration();

        $this->assertArrayHasKey('instructions', $config);
        $this->assertNotEmpty($config['instructions']);
    }

    #[Test]
    public function default_configuration_has_timeout(): void
    {
        $config = $this->agent->defaultConfiguration();

        $this->assertArrayHasKey('timeout', $config);
        $this->assertGreaterThanOrEqual(30, $config['timeout']);
    }

    #[Test]
    public function context_providers_include_products(): void
    {
        $this->assertContains('products', $this->agent->contextProviders());
    }

    #[Test]
    public function schema_method_exists_and_is_callable(): void
    {
        $this->assertTrue(method_exists($this->agent, 'schema'));

        $reflection = new \ReflectionMethod($this->agent, 'schema');
        $this->assertSame(JsonSchema::class, $reflection->getParameters()[0]->getType()->getName());
    }

    #[Test]
    public function instructions_are_not_empty(): void
    {
        $instructions = $this->agent->instructions();

        $this->assertNotEmpty((string) $instructions);
    }

    #[Test]
    public function recipe_schema_requires_the_complete_editable_recipe_shape(): void
    {
        $schema = $this->agent->schema(new JsonSchemaTypeFactory);
        $serialized = (new ObjectSchema($schema))->toSchema();

        $this->assertSame([
            'name',
            'description',
            'servings',
            'yield_text',
            'prep_time_seconds',
            'cook_time_seconds',
            'total_time_seconds',
            'difficulty',
            'cuisine',
            'cooking_method',
            'recipe_category',
            'author',
            'notes',
            'tags',
            'sections',
            'ingredients',
            'steps',
            'cookware',
        ], $serialized['required']);

        $this->assertContains('client_id', $serialized['properties']['ingredients']['items']['required']);
        $this->assertContains('client_id', $serialized['properties']['steps']['items']['required']);
        $this->assertContains('ingredients', $serialized['properties']['steps']['items']['required']);
        $this->assertContains('cookware', $serialized['properties']['steps']['items']['required']);
        $this->assertContains('timers', $serialized['properties']['steps']['items']['required']);
        $this->assertContains('path', $serialized['properties']['recipe_references']['items']['required']);
        $this->assertContains('recipe_id', $serialized['properties']['recipe_references']['items']['required']);
    }

    #[Test]
    public function recipe_references_are_optional_so_a_recipe_without_links_still_validates(): void
    {
        $schema = $this->agent->schema(new JsonSchemaTypeFactory);
        $serialized = (new ObjectSchema($schema))->toSchema();

        $this->assertNotContains('recipe_references', $serialized['required']);
    }

    #[Test]
    public function instructions_tell_the_model_to_link_existing_recipes(): void
    {
        $instructions = $this->agent->instructions();

        $this->assertStringContainsString('RECETAS EXISTENTES', $instructions);
        $this->assertStringContainsString('@./salsas/Salsa Boloñesa Estilo Italiano{4%raciones}', $instructions);
        $this->assertStringContainsString('recipe_references', $instructions);
        $this->assertStringContainsString('PROHIBIDO elaborar la receta enlazada', $instructions);
        $this->assertStringNotContainsString('search_recipe_references', $instructions);
    }

    #[Test]
    public function instructions_require_cooklang_and_linked_client_ids(): void
    {
        $instructions = $this->agent->instructions();

        $this->assertStringContainsString('Cooklang', $instructions);
        $this->assertStringContainsString('client_id', $instructions);
        $this->assertStringContainsString('prep_time_seconds + cook_time_seconds', $instructions);
        $this->assertStringContainsString('Calentar @aceite{2%cucharadas} en la #sartén durante ~precalentar{5 minutos}.', $instructions);
        $this->assertStringContainsString('["cook-1"]', $instructions);
        $this->assertStringContainsString('No incluyas valores como "#sartén"', $instructions);
        $this->assertStringNotContainsString('"name": "Nombre de la receta"', $instructions);
        $this->assertStringContainsString('es solamente un fragmento, no el esquema completo', $instructions);
        $this->assertStringContainsString('"ingredients": ["ing-1"]', $instructions);
        $this->assertStringContainsString('"duration_seconds": 300', $instructions);
    }
}
