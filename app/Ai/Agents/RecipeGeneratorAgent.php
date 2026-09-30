<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\UsesRuntimeConfiguration;
use HomeSide\AiAgents\Concerns\UsesProviderCapabilities;
use HomeSide\AiAgents\Concerns\UsesRuntimeInstructions;
use HomeSide\AiAgents\Configuration\Capability;
use HomeSide\AiAgents\Contracts\AcceptsProviderCapabilities;
use HomeSide\AiAgents\Contracts\AcceptsRuntimeConfiguration;
use HomeSide\AiAgents\Contracts\AcceptsRuntimeInstructions;
use HomeSide\AiAgents\Contracts\AgentMetadata;
use HomeSide\AiAgents\Contracts\DomainAgent;
use HomeSide\AiAgents\Enums\PrivacyLevel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Agent de generación de recetas con structured output.
 *
 * Produce recetas estructuradas usando el schema JSON del SDK
 * en lugar de parsing manual de JSON desde texto libre.
 *
 * The user's existing recipes are handed to the model as plain context (see
 * GenerateRecipeWithAi), not as a tool: combining tool-calling with a
 * json_schema response_format makes some OpenAI-compatible endpoints echo the
 * tool definition into the structured payload instead of invoking it.
 *
 * Key: recipes.recipe_generator
 * Module: recipes
 */
class RecipeGeneratorAgent implements AcceptsProviderCapabilities, AcceptsRuntimeConfiguration, AcceptsRuntimeInstructions, Agent, AgentMetadata, DomainAgent, HasProviderOptions, HasStructuredOutput
{
    use Promptable, UsesProviderCapabilities, UsesRuntimeConfiguration, UsesRuntimeInstructions;

    public function instructions(): string
    {
        $defaultInstructions = <<<'PROMPT'
Eres un chef experto y director creativo que genera recetas completas y detalladas.

IMPORTANTE: El usuario NO te está describiendo una receta. El usuario te da un prompt libre como "una lasaña boloñesa" o "postre fácil con chocolate". Tú debes INTERPRETAR esa intención y crear la receta completa.

Debes generar una receta con TODOS los campos posibles rellenados de forma coherente entre sí.

REGLAS CRÍTICAS:

1. COOKLANG EN PASOS: La descripción de CADA paso DEBE usar sintaxis Cooklang para referenciar todos los elementos utilizados:
   - Ingredientes: @nombre{cantidad%unidad} o @nombre si es genérico.
   - Utensilios: #nombre.
   - Temporizadores: ~{duración} o pueden tener nombre ~nombre{duración}, por ejemplo ~cocción{20%minutos}.
   - Si cualquiera de los ingredientes o utennsilios tienes mas de una palabra se puede indicar con {} al final de las palabras, por ejemplo @harina de maiz{200g}, @huevos duros{}, #cuchara supera{}
   Ejemplo obligatorio de formato: "Calentar @aceite{2%cucharadas} en la #sartén durante ~precalentar{5 minutos}."
   No escribas los ingredientes, utensilios ni temporizadores como texto plano cuando se usen en un paso.

2. TIEMPOS COHERENTES: prep_time_seconds + cook_time_seconds DEBE ser igual a total_time_seconds. Usa segundos en los campos numéricos.

3. SECCIONES: Si la receta tiene preparación previa (marinado, reposo o preparación de ingredientes antes de la cocción principal), crea al menos 2 secciones y asigna los pasos, ingredientes y utensilios correspondientes con section_id.

4. INGREDIENTES EN PASOS: El array "ingredients" de cada paso DEBE contener exclusivamente los client_id de los ingredientes usados en ese paso, por ejemplo ["ing-1"]. No incluyas nombres ni referencias Cooklang en ese array.

5. UTENSILIOS EN PASOS: El array "cookware" de cada paso DEBE contener exclusivamente los client_id de los utensilios usados en ese paso, por ejemplo ["cook-1"]. No incluyas valores como "#sartén" en ese array; el prefijo # pertenece únicamente a description.

6. TEMPORIZADORES EN PASOS: Cada temporizador utilizado debe aparecer tanto en description con ~nombre{duración} como en el array "timers" con client_id, name, duration_seconds y order.

7. CANTIDADES: Usa cantidades realistas y precisas. Unidades en español: g, kg, ml, l, cdta, cucharada, taza, unidades, etc.

8. TODOS LOS CAMPOS: Rellena name, description, servings, difficulty, cuisine, cooking_method, recipe_category, tags y notes.

9. client_id: Usa valores descriptivos, únicos y consistentes como "ing-1", "step-1", "cook-1", "section-1" y "timer-1".

10. OPCIONAL: Marca ingredientes como optional: true solamente si son prescindibles.

11. PRODUCTOS: Asocia product_id solamente cuando el ingrediente coincida con un producto del catálogo proporcionado; en caso contrario usa null.

12. RECETAS EXISTENTES — ENLACE OBLIGATORIO: Cuando el mensaje incluya una lista de recetas del usuario con su ruta, y alguno de los componentes de la receta que vas a generar (salsa, masa, base, guarnición, relleno, caldo, postre...) coincida con una de esas recetas, DEBES enlazarla. Esto es obligatorio cuando hay coincidencia.

   a) En el paso, escribe el componente ÚNICAMENTE como referencia Cooklang con la ruta EXACTA de la lista:

      CORRECTO: "Servir los @espaguetis{400%g} cubiertos con @./salsas/Salsa Boloñesa Estilo Italiano{4%raciones}."
      INCORRECTO: "Preparar la salsa boloñesa siguiendo la receta ./salsas/Salsa Boloñesa Estilo Italiano."
      INCORRECTO: "Añadir @salsa boloñesa{4%raciones}."   (eso es un ingrediente nuevo, no un enlace)

      La referencia SIEMPRE tiene la forma @ + ruta + {cantidad%unidad}, con la ruta pegada al @ y sin texto alrededor.

   b) PROHIBIDO elaborar la receta enlazada: no describas sus pasos, no listes sus ingredientes y NO añadas ninguno de sus ingredientes al array "ingredients". Sus ingredientes entran solos al comprar o cocinar, porque la referencia se resuelve de forma recursiva.

   c) Declara el enlace en "recipe_references" con esa misma ruta y el id de la lista.

   d) Si ninguna receta coincide, elabora el componente con normalidad.
   e) Si no hay lista de recetas, ignora esta regla.

13. FORMATO: Respeta exactamente el esquema de salida estructurada definido por el agente. No omitas listas: usa un array vacío cuando no correspondan.

EJEMPLO COMPACTO DE COOKLANG Y RELACIONES (es solamente un fragmento, no el esquema completo):

{
  "ingredients": [
    {"client_id": "ing-1", "name": "aceite", "quantity": 2, "unit": "cucharadas", "preparation": null, "notes": null, "optional": false, "section_id": null, "order": 1, "product_id": null}
  ],
  "cookware": [
    {"client_id": "cook-1", "name": "Sartén", "type": "tool", "quantity": 1, "unit": null, "section_id": null, "order": 1}
  ],
  "steps": [
    {"client_id": "step-1", "description": "Calentar @aceite{2%cucharadas} en la #sartén durante ~precalentar{5 minutos}.", "section_id": null, "order": 1, "ingredients": ["ing-1"], "cookware": ["cook-1"], "timers": [{"client_id": "timer-1", "name": "precalentar", "duration_seconds": 300, "order": 1}]}
  ],
  "recipe_references": [
    {"path": "./Salsas/Boloñesa", "recipe_id": "id-devuelto-por-search_recipe_references"}
  ]
}
PROMPT;

        return $this->runtimeInstructionsOr($defaultInstructions);
    }

    public function key(): string
    {
        return 'recipes.recipe_generator';
    }

    public function module(): string
    {
        return 'recipes';
    }

    public function version(): int
    {
        return 1;
    }

    public function requiredCapabilities(): array
    {
        return [
            Capability::Text,
            Capability::StructuredOutput,
        ];
    }

    public function defaultConfiguration(): array
    {
        return [
            'instructions' => $this->instructions(),
            'temperature' => 0.7,
            // Base budget for the recipe JSON. When the resolved model
            // reasons, effectiveMaxTokens() doubles it because thinking
            // consumes the same budget before any content is emitted.
            'max_tokens' => 8192,
            'max_steps' => 1,
            'timeout' => 120,
        ];
    }

    /**
     * Límite de tokens para el SDK (resuelto por TextGenerationOptions::forAgent).
     * Adaptado al modo reasoning del provider resuelto por el manager.
     */
    public function maxTokens(): int
    {
        return $this->effectiveMaxTokens($this->runtimeMaxTokens(8192));
    }

    /**
     * Opciones específicas del provider: reenvía el reasoning_effort si el
     * provider lo declara (vía UsesProviderCapabilities).
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return $this->capabilityProviderOptions();
    }

    public function temperature(): float
    {
        return $this->runtimeTemperature(0.7);
    }

    public function maxSteps(): int
    {
        return $this->runtimeMaxSteps(1);
    }

    public function contextProviders(): array
    {
        return ['products'];
    }

    public function maxContextTokens(): int
    {
        return 4096;
    }

    public function requiredPrivacyLevel(): ?PrivacyLevel
    {
        return null;
    }

    public function label(): string
    {
        return 'Generador de recetas';
    }

    public function description(): ?string
    {
        return 'Genera recetas completas y detalladas a partir de un prompt libre del usuario.';
    }

    public function defaultParameters(): ?array
    {
        return [
            'temperature' => 0.7,
            'max_tokens' => 4096,
        ];
    }

    /**
     * Schema JSON para structured output del SDK.
     *
     * Define la estructura exacta de la respuesta de receta.
     */
    public function schema(JsonSchema $schema): array
    {
        $ingredientSchema = $schema->object([
            'client_id' => $schema->string()->required()->description('Identificador único, por ejemplo ing-1'),
            'name' => $schema->string()->required()->description('Nombre del ingrediente'),
            'quantity' => $schema->number()->nullable()->required()->description('Cantidad numérica'),
            'unit' => $schema->string()->nullable()->required()->description('Unidad de medida en español'),
            'preparation' => $schema->string()->nullable()->required()->description('Preparación previa del ingrediente'),
            'notes' => $schema->string()->nullable()->required()->description('Notas adicionales'),
            'optional' => $schema->boolean()->required()->description('Indica si el ingrediente es opcional'),
            'section_id' => $schema->string()->nullable()->required()->description('client_id de la sección asociada'),
            'order' => $schema->integer()->required()->description('Orden del ingrediente'),
            'product_id' => $schema->string()->nullable()->required()->description('ID del producto del catálogo si existe una coincidencia'),
        ])->withoutAdditionalProperties();

        $timerSchema = $schema->object([
            'client_id' => $schema->string()->required()->description('Identificador único, por ejemplo timer-1'),
            'name' => $schema->string()->required()->description('Nombre del temporizador'),
            'duration_seconds' => $schema->integer()->required()->description('Duración en segundos'),
            'order' => $schema->integer()->required()->description('Orden del temporizador dentro del paso'),
        ])->withoutAdditionalProperties();

        $stepSchema = $schema->object([
            'client_id' => $schema->string()->required()->description('Identificador único, por ejemplo step-1'),
            'description' => $schema->string()->required()->description('Instrucción con referencias Cooklang'),
            'section_id' => $schema->string()->nullable()->required()->description('client_id de la sección asociada'),
            'order' => $schema->integer()->required()->description('Orden del paso'),
            'ingredients' => $schema->array()->items($schema->string())->required()->description('client_id de los ingredientes usados'),
            'cookware' => $schema->array()->items($schema->string())->required()->description('client_id de los utensilios usados'),
            'timers' => $schema->array()->items($timerSchema)->required()->description('Temporizadores del paso'),
        ])->withoutAdditionalProperties();

        $cookwareSchema = $schema->object([
            'client_id' => $schema->string()->required()->description('Identificador único, por ejemplo cook-1'),
            'name' => $schema->string()->required()->description('Nombre del utensilio'),
            'type' => $schema->string()->required()->description('Tipo de utensilio'),
            'quantity' => $schema->integer()->nullable()->required()->description('Cantidad necesaria'),
            'unit' => $schema->string()->nullable()->required()->description('Unidad, si corresponde'),
            'section_id' => $schema->string()->nullable()->required()->description('client_id de la sección asociada'),
            'order' => $schema->integer()->required()->description('Orden del utensilio'),
        ])->withoutAdditionalProperties();

        $sectionSchema = $schema->object([
            'client_id' => $schema->string()->required()->description('Identificador único, por ejemplo section-1'),
            'name' => $schema->string()->required()->description('Nombre de la sección'),
            'order' => $schema->integer()->required()->description('Orden de la sección'),
        ])->withoutAdditionalProperties();

        $recipeReferenceSchema = $schema->object([
            'path' => $schema->string()->required()->description('Ruta Cooklang usada en el paso, por ejemplo ./Salsas/Boloñesa'),
            'recipe_id' => $schema->string()->nullable()->required()->description('ID devuelto por search_recipe_references para esa ruta'),
        ])->withoutAdditionalProperties();

        return [
            'name' => $schema->string()->required()->description('Nombre de la receta'),
            'description' => $schema->string()->required()->description('Descripción breve y apetitosa'),
            'servings' => $schema->integer()->required()->description('Número de porciones'),
            'yield_text' => $schema->string()->nullable()->required()->description('Rendimiento textual'),
            'prep_time_seconds' => $schema->integer()->required()->description('Tiempo de preparación en segundos'),
            'cook_time_seconds' => $schema->integer()->required()->description('Tiempo de cocción en segundos'),
            'total_time_seconds' => $schema->integer()->required()->description('Suma de preparación y cocción en segundos'),
            'difficulty' => $schema->string()->enum(['easy', 'medium', 'hard', 'expert'])->required()->description('Nivel de dificultad'),
            'cuisine' => $schema->string()->required()->description('Tipo de cocina'),
            'cooking_method' => $schema->string()->required()->description('Método principal de cocción'),
            'recipe_category' => $schema->string()->required()->description('Categoría de la receta'),
            'author' => $schema->string()->nullable()->required()->description('Autor o fuente de la receta'),
            'notes' => $schema->string()->required()->description('Notas útiles del chef'),
            'tags' => $schema->array()->items($schema->string())->required()->description('Etiquetas de la receta'),
            'sections' => $schema->array()->items($sectionSchema)->required()->description('Secciones de preparación'),
            'ingredients' => $schema->array()->items($ingredientSchema)->required()->description('Lista de ingredientes'),
            'steps' => $schema->array()->items($stepSchema)->required()->description('Pasos de preparación'),
            'cookware' => $schema->array()->items($cookwareSchema)->required()->description('Utensilios necesarios'),
            'recipe_references' => $schema->array()->items($recipeReferenceSchema)->description('Recetas existentes enlazadas como componente, en lugar de reexplicarlas: solo si hay coincidencia clara'),
        ];
    }
}
