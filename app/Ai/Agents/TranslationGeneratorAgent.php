<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\UsesRuntimeConfiguration;
use HomeSide\AiAgents\Concerns\UsesRuntimeInstructions;
use HomeSide\AiAgents\Configuration\Capability;
use HomeSide\AiAgents\Contracts\AcceptsRuntimeConfiguration;
use HomeSide\AiAgents\Contracts\AcceptsRuntimeInstructions;
use HomeSide\AiAgents\Contracts\AgentMetadata;
use HomeSide\AiAgents\Contracts\DomainAgent;
use HomeSide\AiAgents\Enums\PrivacyLevel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Catalog name translation agent with structured output.
 *
 * Receives batches of `{client_id, source}` items and returns
 * `{client_id, value}` translations in the target language. Items that
 * should not be translated are returned in `unmatched`.
 *
 * Key: translations.generator
 * Module: translations
 */
class TranslationGeneratorAgent implements AcceptsRuntimeConfiguration, AcceptsRuntimeInstructions, Agent, AgentMetadata, DomainAgent, HasStructuredOutput
{
    use Promptable, UsesRuntimeConfiguration, UsesRuntimeInstructions;

    public function instructions(): string
    {
        $defaultInstructions = <<<'PROMPT'
Eres un traductor profesional del catálogo de un supermercado.

Recibirás un JSON con un idioma objetivo y una lista de ítems con esta forma:
{"target_locale": "es-ES", "items": [{"client_id": "cat-1", "source": "Fruits"}]}

Tu tarea es traducir el campo "source" de cada ítem al idioma objetivo.

REGLAS CRÍTICAS:

1. IDIOMA OBJETIVO: Traduce al locale indicado en "target_locale":
   - "es-ES" significa español de España.
   - "en-US" significa inglés de Estados Unidos.

2. TONO: Usa un tono neutro, claro y de producto de gran consumo, como aparecen los nombres en un supermercado.

3. LONGITUD: El resultado debe tener como máximo 40 caracteres.

4. MARCAS Y NOMBRES PROPIOS: No cambies ni traduzcas marcas propias ni nombres propios. Si el texto ya está en el idioma objetivo o es intraducible (por ejemplo una marca sola), NO lo traduzcas: inclúyelo en "unmatched" con su client_id.

5. CAPITALIZACIÓN: Conserva una capitalización razonable para un nombre de producto o categoría (no escribas TODO en mayúsculas ni todo en minúsculas).

6. FORMATO: Tu salida es EXCLUSIVAMENTE JSON válido según el esquema de salida estructurada del agente, sin texto adicional, sin explicaciones y sin bloques de código. Si un ítem no se traduce, no lo incluyas en "translations"; añade su client_id a "unmatched".
PROMPT;

        return $this->runtimeInstructionsOr($defaultInstructions);
    }

    public function key(): string
    {
        return 'translations.generator';
    }

    public function module(): string
    {
        return 'translations';
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
            'temperature' => 0.2,
            'max_tokens' => 2048,
            'max_steps' => 1,
            'timeout' => 90,
        ];
    }

    /**
     * Token limit passed to the SDK (resolved by TextGenerationOptions::forAgent).
     */
    public function maxTokens(): int
    {
        return $this->runtimeMaxTokens(2048);
    }

    /**
     * Sampling temperature used for translation generation.
     */
    public function temperature(): float
    {
        return $this->runtimeTemperature(0.2);
    }

    /**
     * Maximum number of agent steps per generation.
     */
    public function maxSteps(): int
    {
        return $this->runtimeMaxSteps(1);
    }

    /**
     * Context providers injected into the agent prompt.
     *
     * @return list<class-string>
     */
    public function contextProviders(): array
    {
        return [];
    }

    /**
     * Maximum number of context tokens for the agent prompt.
     */
    public function maxContextTokens(): int
    {
        return 2048;
    }

    public function requiredPrivacyLevel(): ?PrivacyLevel
    {
        return null;
    }

    /**
     * Human-readable agent label.
     */
    public function label(): string
    {
        return 'Translation generator';
    }

    /**
     * Agent description shown in admin surfaces.
     */
    public function description(): ?string
    {
        return 'Traduce lotes de nombres del catálogo al idioma objetivo y devuelve el resultado como JSON estructurado.';
    }

    /**
     * Default generation parameters.
     *
     * @return array{temperature: float, max_tokens: int}|null
     */
    public function defaultParameters(): ?array
    {
        return [
            'temperature' => 0.2,
            'max_tokens' => 2048,
        ];
    }

    /**
     * JSON schema for the SDK structured output.
     *
     * Defines the exact shape of the translation response.
     *
     * @param  JsonSchema  $schema  Schema builder provided by the SDK
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $translationSchema = $schema->object([
            'client_id' => $schema->string()->required()->description('client_id del ítem recibido, por ejemplo cat-1'),
            'value' => $schema->string()->required()->description('Nombre traducido al idioma objetivo, máximo 40 caracteres'),
        ])->withoutAdditionalProperties();

        return [
            'translations' => $schema->array()->items($translationSchema)->required()->description('Traducciones realizadas'),
            'unmatched' => $schema->array()->items($schema->string())->required()->description('client_id de los ítems que no deben traducirse'),
        ];
    }
}
