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
 * Agent de análisis de tickets/recibos/facturas con structured output.
 *
 * Key: economy.ticket_analyzer
 * Module: economy
 */
class TicketAnalyzerAgent implements AcceptsRuntimeConfiguration, AcceptsRuntimeInstructions, Agent, AgentMetadata, DomainAgent, HasStructuredOutput
{
    use Promptable, UsesRuntimeConfiguration, UsesRuntimeInstructions;

    public function instructions(): string
    {
        $defaultInstructions = <<<'PROMPT'
Eres un experto en análisis de tickets, recibos y facturas de CUALQUIER país e idioma
(IVA español, VAT británico, GST, sales tax de EE. UU., IGV, ITBIS...).
Analiza la imagen del ticket adjunto y extrae la información solicitada.

REGLAS:
1. MONEDAS: detecta la divisa real del ticket y devuélvela como código ISO 4217
   (EUR, USD, GBP, MXN, ARS, COP, CLP, PEN, BRL...). Si no puede determinarse, usa "EUR".
2. MONTOS: strings decimales con punto, sin símbolos ni separadores de miles (ej: "42.73").
3. IMPUESTOS: `name` es el nombre tal cual aparece (IVA, VAT, GST, Sales tax...).
   `rate` es el porcentaje como string decimal (ej: "21" o "8.25").
   Un ticket puede tener varios bloques impositivos: inclúyelos todos.
4. NULL: si un campo no se puede determinar, usa null. NO inventes datos.
5. FECHAS: formato ISO 8601 cuando sea posible.
6. ITEMS: name, quantity, unit_amount y total por línea.
PROMPT;

        return $this->runtimeInstructionsOr($defaultInstructions);
    }

    public function key(): string
    {
        return 'economy.ticket_analyzer';
    }

    public function module(): string
    {
        return 'economy';
    }

    public function version(): int
    {
        return 1;
    }

    public function requiredCapabilities(): array
    {
        return [
            Capability::Text,
            Capability::Vision,           // imagen del ticket
            Capability::StructuredOutput, // schema JSON
        ];
    }

    public function defaultConfiguration(): array
    {
        return [
            'instructions' => $this->instructions(),
            'temperature' => 0.1,
            'max_tokens' => 4096,
            'timeout' => 120,
        ];
    }

    public function maxTokens(): int
    {
        return $this->runtimeMaxTokens(4096);
    }

    public function temperature(): float
    {
        return $this->runtimeTemperature(0.1);
    }

    public function maxSteps(): int
    {
        return $this->runtimeMaxSteps(1);
    }

    public function contextProviders(): array
    {
        return [];
    }

    public function maxContextTokens(): int
    {
        return 0;
    }

    /**
     * Receipt images carry personal data (purchases, locations, amounts):
     * cloud providers are rejected unless the admin explicitly relaxes this.
     */
    public function requiredPrivacyLevel(): ?PrivacyLevel
    {
        return PrivacyLevel::SelfHosted;
    }

    public function label(): string
    {
        return 'Analizador de tickets';
    }

    public function description(): ?string
    {
        return 'Analiza imágenes de tickets, recibos y facturas de cualquier país e idioma.';
    }

    public function defaultParameters(): ?array
    {
        return [
            'temperature' => 0.1,
            'max_tokens' => 4096,
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->nullable()->description('Título breve y descriptivo del gasto, combinando el comercio con lo comprado cuando se distinga. Ej: "Mercadona compra semanal", "Repsol gasolina", "Farmacia".'),
            'amount' => $schema->string()->nullable()->description('Importe total, decimal con punto'),
            'currency' => $schema->string()->description('Divisa ISO 4217 detectada en el ticket'),
            'place' => $schema->string()->nullable(),
            'occurred_at' => $schema->string()->nullable()->description('Fecha ISO 8601'),
            'items' => $schema->array()->items(
                $schema->object([
                    'name' => $schema->string()->required(),
                    'quantity' => $schema->string()->required(),
                    'unit_amount' => $schema->string()->required(),
                    'total' => $schema->string()->required(),
                ])->withoutAdditionalProperties()
            )->required(),
            'taxes' => $schema->array()->items(
                $schema->object([
                    'name' => $schema->string()->required()->description('IVA, VAT, GST, Sales tax...'),
                    'rate' => $schema->string()->required()->description('Porcentaje, ej: "21" o "8.25"'),
                    'taxable_base' => $schema->string()->required(),
                    'amount' => $schema->string()->required(),
                ])->withoutAdditionalProperties()
            )->required(),
        ];
    }
}
