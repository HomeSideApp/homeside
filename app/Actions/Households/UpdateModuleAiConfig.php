<?php

namespace App\Actions\Households;

use App\Data\Households\UpdateModuleAiConfigData;
use App\Models\Household;
use HomeSide\AiAgents\AgentRegistry;
use HomeSide\AiAgents\Models\ModuleAiConfiguration;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Agent;

/**
 * Creates or updates the AI configuration of a household module.
 */
final class UpdateModuleAiConfig
{
    /**
     * @param  Household  $household  The household the configuration belongs to
     * @param  UpdateModuleAiConfigData  $data  The configuration data
     * @return ModuleAiConfiguration The ModuleAiConfiguration value.
     */
    public function execute(Household $household, UpdateModuleAiConfigData $data): ModuleAiConfiguration
    {
        $agentKey = $data->module->value.'.'.$data->agent_name;
        $registry = app(AgentRegistry::class);

        if (! $registry->has($agentKey)) {
            throw ValidationException::withMessages(['agent_name' => 'Unknown AI agent.']);
        }

        $moduleConfig = ModuleAiConfiguration::firstOrNew([
            'household_id' => $household->id,
            'module' => $data->module->value,
            'agent_name' => $data->agent_name,
        ]);

        if (! $moduleConfig->exists) {
            $agent = $registry->get($agentKey);
            $moduleConfig->system_prompt = $agent instanceof Agent ? $agent->instructions() : '';
        }

        $moduleConfig->fill([
            'label' => $data->label,
            'additional_instructions' => $data->additional_instructions,
            'description' => $data->description,
            'ai_provider_id' => $data->ai_provider_id,
            'model' => $data->model,
            'parameters' => $data->parameters,
            'enabled' => $data->enabled,
        ]);
        $moduleConfig->save();

        return $moduleConfig;
    }
}
