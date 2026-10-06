<?php

namespace App\Http\Requests;

use HomeSide\AiAgents\AgentRegistry;
use HomeSide\AiAgents\Models\AiConversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiConversationRequest extends FormRequest
{
    public const array AGENT_ALIASES = [
        'assistant' => 'assistant.homeside',
        'recipes' => 'recipes.recipe_generator',
        'economy' => 'economy.ticket_analyzer',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can('create', AiConversation::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->routeIs('api.v1.*')
            ? ['agent' => ['required', Rule::in([...array_keys(self::AGENT_ALIASES), ...app(AgentRegistry::class)->all()])]]
            : [];
    }
}
