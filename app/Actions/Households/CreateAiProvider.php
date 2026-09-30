<?php

namespace App\Actions\Households;

use App\Data\Households\CreateAiProviderData;
use App\Models\Household;
use App\Models\User;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\Models\AiProviderModel;
use Illuminate\Support\Facades\DB;

/**
 * Creates a household AI provider, ensuring no other provider exists for
 * the same module.
 */
final class CreateAiProvider
{
    /**
     * @param  CreateAiProviderData  $data  The provider data
     * @param  Household  $household  The household the provider belongs to
     * @param  User  $user  The user creating the provider
     * @return AiProvider The AiProvider value.
     */
    public function execute(CreateAiProviderData $data, Household $household, User $user): AiProvider
    {
        return DB::transaction(function () use ($data, $household, $user): AiProvider {
            $provider = new AiProvider([
                'household_id' => $household->id,
                'name' => $data->name,
                'type' => $data->driver->value,
                'driver' => $data->driver->value,
                'base_url' => $data->base_url,
                'model' => $data->model,
                'module' => $data->module->value,
                'enabled' => $data->enabled,
                'configuration' => $data->configuration,
                'created_by' => $user->id,
                'privacy_level' => $data->privacy_level->value,
                'fallback_policy' => $data->fallback_policy->value,
                // Catalog-aligned spec columns.
                'family' => $data->family,
                'description' => $data->description,
                'attachment' => $data->attachment,
                'reasoning' => $data->reasoning,
                'reasoning_options' => $data->reasoning_options,
                'tool_call' => $data->tool_call,
                'structured_output' => $data->structured_output,
                'temperature' => $data->temperature,
                'open_weights' => $data->open_weights,
                'modalities_input' => $data->modalities_input,
                'modalities_output' => $data->modalities_output,
                'context_window' => $data->context_window,
                'max_input_tokens' => $data->max_input_tokens,
                'max_output_tokens' => $data->max_output_tokens,
                'cost_input' => $data->cost_input,
                'cost_output' => $data->cost_output,
                'cost_cache_read' => $data->cost_cache_read,
                'cost_cache_write' => $data->cost_cache_write,
            ]);

            $provider->api_key = $data->api_key;
            $provider->save();
            $provider->setModules($data->modules);

            // Crear el AiProviderModel inicial con el modelo configurado
            AiProviderModel::create([
                'ai_provider_id' => $provider->id,
                'model' => $data->model,
                'display_name' => $data->model,
                'enabled' => true,
                'is_default' => true,
            ]);

            return $provider;
        });
    }
}
