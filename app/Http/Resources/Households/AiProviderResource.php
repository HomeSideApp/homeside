<?php

namespace App\Http\Resources\Households;

use HomeSide\AiAgents\Models\AiProvider;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AiProvider */
final class AiProviderResource extends JsonResource
{
    /**
     * @param  mixed  $request  The incoming HTTP request.
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'driver' => $this->driver ?? $this->type,
            'base_url' => $this->base_url,
            'model' => $this->model,
            'module' => $this->module,
            'modules' => $this->assignedModules(),
            'default_modules' => $this->defaultModules(),
            'module_label' => __('app.ai_provider_modules.'.$this->module),
            'enabled' => $this->enabled,
            'is_default' => $this->is_default ?? false,
            'configuration' => $this->redactSecrets($this->configuration ?? []),
            'privacy_level' => $this->privacy_level ?? 'unknown',
            'fallback_policy' => $this->fallback_policy ?? 'same_privacy_level',
            'has_api_key' => ! empty($this->api_key),
            'created_at' => $this->created_at?->toISOString(),
            // Catalog-aligned spec columns.
            'family' => $this->family,
            'description' => $this->description,
            'attachment' => $this->attachment ?? false,
            'reasoning' => $this->reasoning ?? false,
            'reasoning_options' => $this->reasoning_options,
            'tool_call' => $this->tool_call ?? false,
            'structured_output' => $this->structured_output ?? false,
            'temperature' => $this->temperature ?? false,
            'open_weights' => $this->open_weights ?? false,
            'modalities_input' => $this->modalities_input,
            'modalities_output' => $this->modalities_output,
            'context_window' => $this->context_window,
            'max_input_tokens' => $this->max_input_tokens,
            'max_output_tokens' => $this->max_output_tokens,
            'cost_input' => $this->cost_input,
            'cost_output' => $this->cost_output,
            'cost_cache_read' => $this->cost_cache_read,
            'cost_cache_write' => $this->cost_cache_write,
        ];
    }

    /** @param array<string, mixed> $configuration */
    private function redactSecrets(array $configuration): array
    {
        return collect($configuration)
            ->reject(fn (mixed $value, string|int $key): bool => preg_match('/key|secret|token|password/i', (string) $key) === 1)
            ->map(fn (mixed $value): mixed => is_array($value) ? $this->redactSecrets($value) : $value)
            ->all();
    }
}
