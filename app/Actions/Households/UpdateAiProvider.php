<?php

namespace App\Actions\Households;

use App\Data\Households\UpdateAiProviderData;
use HomeSide\AiAgents\Models\AiProvider;

/**
 * Updates a household AI provider, keeping its existing API key when no
 * new one is provided.
 */
final class UpdateAiProvider
{
    /**
     * @param  AiProvider  $provider  The provider to update
     * @param  UpdateAiProviderData  $data  The data for the update
     * @return AiProvider The AiProvider value.
     */
    public function execute(AiProvider $provider, UpdateAiProviderData $data): AiProvider
    {
        $provider->update([
            'name' => $data->name,
            'type' => $data->driver->value,
            'driver' => $data->driver->value,
            'base_url' => $data->base_url,
            'model' => $data->model,
            'module' => $data->module->value,
            'enabled' => $data->enabled,
            'configuration' => $data->configuration,
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
        $provider->setModules($data->modules);

        if ($data->api_key !== null) {
            $provider->api_key = $data->api_key;
            $provider->save();
        }

        $provider->refresh();

        return $provider;
    }
}
