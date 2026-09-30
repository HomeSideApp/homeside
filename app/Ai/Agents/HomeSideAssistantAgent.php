<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\GetAvailableIngredientsTool;
use App\Ai\Tools\GetRecipeTool;
use App\Ai\Tools\GetShoppingListItemsTool;
use App\Ai\Tools\GetShoppingListsTool;
use App\Ai\Tools\SearchAccessibleRecipesTool;
use App\Ai\Tools\SendActionProposalTool;
use App\Ai\UsesRuntimeConfiguration;
use HomeSide\AiAgents\Concerns\UsesRuntimeInstructions;
use HomeSide\AiAgents\Configuration\Capability;
use HomeSide\AiAgents\Contracts\AcceptsExecutionContext;
use HomeSide\AiAgents\Contracts\AcceptsRuntimeConfiguration;
use HomeSide\AiAgents\Contracts\AcceptsRuntimeInstructions;
use HomeSide\AiAgents\Contracts\AgentMetadata;
use HomeSide\AiAgents\Contracts\DomainAgent;
use HomeSide\AiAgents\Enums\PrivacyLevel;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use HomeSide\AiAgents\Models\AiRun;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use RuntimeException;

/**
 * Assistant general de HomeSide.
 *
 * Delega por dominio via sub-agents o tools.
 * Nunca muta directamente — genera Action Proposals.
 *
 * Key: assistant.homeside
 * Module: assistant
 */
class HomeSideAssistantAgent implements AcceptsExecutionContext, AcceptsRuntimeConfiguration, AcceptsRuntimeInstructions, Agent, AgentMetadata, Conversational, DomainAgent, HasTools
{
    use Promptable, UsesRuntimeConfiguration, UsesRuntimeInstructions;

    private ?AiExecutionContextData $executionContext = null;

    /**
     * Set the per-request execution context before the agent builds its tools.
     *
     * AiAgentManager invokes it on every run so the tools always operate on
     * the authenticated user's identity.
     *
     * @param  AiExecutionContextData  $context  The execution context carrying the authenticated user, household and conversation identifiers.
     * @return void This method does not return a value.
     */
    public function setExecutionContext(AiExecutionContextData $context): void
    {
        $this->executionContext = $context;
    }

    /** @return list<Message> */
    public function messages(): iterable
    {
        $context = $this->executionContext;

        if ($context?->conversationId === null) {
            return [];
        }

        $runs = AiRun::query()
            ->where('conversation_id', $context->conversationId)
            ->where('user_id', $context->userId)
            ->where('agent', $this->key())
            ->where('status', 'ok')
            ->whereIn('content_mode', ['encrypted', 'plain'])
            ->whereNotNull('user_message')
            ->whereNotNull('reply')
            ->when($context->tenantId !== null, fn ($query) => $query->where('household_id', $context->tenantId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $turns = [];
        $characters = 0;

        foreach ($runs as $run) {
            $userMessage = $run->user_message;
            $reply = $run->reply;

            if (! is_string($userMessage) || ! is_string($reply)
                || str_starts_with($userMessage, 'enc:v1:') || str_starts_with($reply, 'enc:v1:')
                || trim($userMessage) === '' || trim($reply) === '') {
                continue;
            }

            $length = mb_strlen($userMessage) + mb_strlen($reply);

            if ($characters + $length > 8000) {
                continue;
            }

            $turns[] = [new Message('user', $userMessage), new Message('assistant', $reply)];
            $characters += $length;

            if (count($turns) === 10) {
                break;
            }
        }

        return array_merge(...array_reverse($turns));
    }

    public function instructions(): string
    {
        $defaultInstructions = <<<'PROMPT'
Eres HomeSide Assistant, el asistente inteligente de la plataforma HomeSide.
Tu función es ayudar a los usuarios con sus tareas del hogar: recetas, compras, inventario, economía.

CAPACIDADES:
- Buscar y obtener recetas del usuario
- Buscar productos e ingredientes disponibles
- Sugerir crear listas de la compra (como propuestas, no directamente)
- Responder preguntas sobre el inventario y economía del hogar

REGLAS CRÍTICAS:
1. NUNCA mutes datos directamente. Si el usuario quiere agregar algo a una lista o crear una receta,
   genera una Action Proposal usando el tool `send_action_proposal`.
2. Siempre respeta la privacidad: solo muestra datos que el usuario puede ver.
3. Sé conciso y útil. No des explicaciones largas innecesarias.
4. Cuando el usuario pida agregar productos a una lista, genera una propuesta con los items exactos.
5. Cuando el usuario pida crear una receta, genera una propuesta con el contenido completo.
6. Usa los tools de lectura para obtener datos antes de responder.
7. Responde en el idioma del usuario.
PROMPT;

        return $this->runtimeInstructionsOr($defaultInstructions);
    }

    public function key(): string
    {
        return 'assistant.homeside';
    }

    public function module(): string
    {
        return 'assistant';
    }

    public function version(): int
    {
        return 1;
    }

    public function requiredCapabilities(): array
    {
        return [
            Capability::Text,
            Capability::Tools,
        ];
    }

    public function defaultConfiguration(): array
    {
        return [
            'instructions' => $this->instructions(),
            'temperature' => 0.5,
            'max_tokens' => 2048,
            'max_steps' => 5,
            'timeout' => 60,
        ];
    }

    public function maxTokens(): int
    {
        return $this->runtimeMaxTokens(2048);
    }

    public function temperature(): float
    {
        return $this->runtimeTemperature(0.5);
    }

    public function maxSteps(): int
    {
        return $this->runtimeMaxSteps(5);
    }

    public function contextProviders(): array
    {
        return ['user', 'household'];
    }

    public function maxContextTokens(): int
    {
        return 2048;
    }

    public function requiredPrivacyLevel(): ?PrivacyLevel
    {
        return null;
    }

    public function label(): string
    {
        return 'HomeSide Assistant';
    }

    public function description(): ?string
    {
        return 'Asistente general de HomeSide para recetas, compras, inventario y economía.';
    }

    public function defaultParameters(): ?array
    {
        return [
            'temperature' => 0.5,
            'max_tokens' => 2048,
            'max_steps' => 5,
        ];
    }

    /**
     * Tools available to the Assistant.
     *
     * Read-only tools plus the action proposal tool. An execution context is
     * required: an empty context is explicitly rejected so tools are never
     * built without a user identity.
     *
     * @return array<int, Tool> The list of tool instances bound to the execution context.
     *
     * @throws RuntimeException When no authenticated execution context has been set.
     */
    public function tools(): array
    {
        $context = $this->executionContext;

        if ($context === null || $context->userId === '') {
            throw new RuntimeException(__('app.ai_tools.requires_user_context'));
        }

        return [
            new SearchAccessibleRecipesTool($context),
            new GetRecipeTool($context),
            new GetAvailableIngredientsTool($context),
            new GetShoppingListsTool($context),
            new GetShoppingListItemsTool($context),
            new SendActionProposalTool($context),
        ];
    }
}
