<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use HomeSide\AiAgents\Execution\AiExecutionContextData;
use HomeSide\AiAgents\Models\AiActionProposal;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Tool para que el Assistant genere propuestas de acción (human-in-the-loop).
 *
 * El Assistant nunca muta directamente. Genera una propuesta (modelo del
 * paquete, escritura validada) que el usuario acepta o rechaza; la ejecución
 * de la acción queda en AcceptAiActionProposal.
 */
class SendActionProposalTool implements Tool
{
    public function __construct(
        private readonly AiExecutionContextData $context,
    ) {}

    public function description(): string
    {
        return 'Genera una propuesta de acción para que el usuario la revise y acepte. Úsalo cuando el usuario quiera agregar items a una lista, crear una receta, o cualquier mutación.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['add_shopping_items', 'create_recipe'])->description('Tipo de propuesta'),
            'payload' => $schema->string()->description('Payload JSON de la propuesta'),
            'reason' => $schema->string()->nullable()->description('Razón de la propuesta para el usuario'),
        ];
    }

    public function handle(Request $request): string
    {
        $payload = json_decode($request->string('payload')->toString(), true);

        $attributes = [
            'user_id' => $this->context->userId,
            'conversation_id' => $this->context->conversationId,
            'type' => $request->string('type'),
            'payload' => is_array($payload) ? $payload : [],
            'reason' => $request->string('reason'),
        ];

        // The scope virtual attribute only accepts user OR tenant (never
        // both): the owner column resolves via the configured tenant FK
        // while tenancy is enabled, and is simply absent otherwise.
        if ($this->context->tenantId !== null) {
            $attributes['scope'] = ['tenant' => $this->context->tenantId];
        }

        $proposal = AiActionProposal::createValidated($attributes);

        return $proposal->toJson();
    }
}
