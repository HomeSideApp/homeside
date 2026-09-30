<?php

namespace App\Data\Households;

use App\Enums\AiProviderModule;
use HomeSide\AiAgents\Enums\AiDriver;
use HomeSide\AiAgents\Enums\FallbackPolicy;
use HomeSide\AiAgents\Enums\PrivacyLevel;

/**
 * Class UpdateAiProviderData
 *
 * This class represents the data required to update a household AI provider.
 * It is a readonly data object that mirrors {@see CreateAiProviderData} but keeps
 * the API key optional, so an unchanged provider can keep its existing key.
 */
final readonly class UpdateAiProviderData
{
    /**
     * Build a new instance from the raw request data.
     *
     * @param  array<string, mixed>  $data  The raw validated request data.
     */
    public static function fromArray(array $data): self
    {
        $driver = isset($data['driver'])
            ? AiDriver::from($data['driver'])
            : AiDriver::fromLegacyType($data['type'] ?? 'openai-compatible');

        $privacyLevel = isset($data['privacy_level'])
            ? PrivacyLevel::from($data['privacy_level'])
            : PrivacyLevel::Unknown;

        $fallbackPolicy = isset($data['fallback_policy'])
            ? FallbackPolicy::from($data['fallback_policy'])
            : FallbackPolicy::SamePrivacyLevel;

        return new self(
            name: (string) $data['name'],
            driver: $driver,
            base_url: (string) $data['base_url'],
            model: (string) $data['model'],
            api_key: isset($data['api_key']) ? (string) $data['api_key'] : null,
            module: AiProviderModule::from($data['module'] ?? $data['modules'][0]),
            modules: array_map(fn (string $module): string => AiProviderModule::from($module)->value, $data['modules'] ?? [$data['module']]),
            enabled: (bool) ($data['enabled'] ?? true),
            configuration: $data['configuration'] ?? [],
            privacy_level: $privacyLevel,
            fallback_policy: $fallbackPolicy,
            // Catalog-aligned spec columns.
            family: $data['family'] ?? null,
            description: $data['description'] ?? null,
            attachment: (bool) ($data['attachment'] ?? false),
            reasoning: (bool) ($data['reasoning'] ?? false),
            reasoning_options: $data['reasoning_options'] ?? null,
            tool_call: (bool) ($data['tool_call'] ?? false),
            structured_output: (bool) ($data['structured_output'] ?? false),
            temperature: (bool) ($data['temperature'] ?? false),
            open_weights: (bool) ($data['open_weights'] ?? false),
            modalities_input: $data['modalities_input'] ?? null,
            modalities_output: $data['modalities_output'] ?? null,
            context_window: isset($data['context_window']) ? (int) $data['context_window'] : null,
            max_input_tokens: isset($data['max_input_tokens']) ? (int) $data['max_input_tokens'] : null,
            max_output_tokens: isset($data['max_output_tokens']) ? (int) $data['max_output_tokens'] : null,
            cost_input: $data['cost_input'] ?? null,
            cost_output: $data['cost_output'] ?? null,
            cost_cache_read: $data['cost_cache_read'] ?? null,
            cost_cache_write: $data['cost_cache_write'] ?? null,
        );
    }

    /**
     * Initialise the data object.
     *
     * @param  array<string, mixed>  $configuration  Additional provider configuration.
     * @param  list<string>|null  $modalities_input  Input modalities (text, image, audio, video).
     * @param  list<string>|null  $modalities_output  Output modalities.
     * @param  list<array<string, mixed>>|null  $reasoning_options  Reasoning configuration options.
     */
    public function __construct(
        public string $name,
        public AiDriver $driver,
        public string $base_url,
        public string $model,
        public ?string $api_key,
        public AiProviderModule $module,
        public bool $enabled,
        public array $configuration,
        public PrivacyLevel $privacy_level = PrivacyLevel::Unknown,
        public FallbackPolicy $fallback_policy = FallbackPolicy::SamePrivacyLevel,
        // Catalog-aligned spec columns.
        public ?string $family = null,
        public ?string $description = null,
        public bool $attachment = false,
        public bool $reasoning = false,
        public ?array $reasoning_options = null,
        public bool $tool_call = false,
        public bool $structured_output = false,
        public bool $temperature = false,
        public bool $open_weights = false,
        public ?array $modalities_input = null,
        public ?array $modalities_output = null,
        public ?int $context_window = null,
        public ?int $max_input_tokens = null,
        public ?int $max_output_tokens = null,
        public ?string $cost_input = null,
        public ?string $cost_output = null,
        public ?string $cost_cache_read = null,
        public ?string $cost_cache_write = null,
        /** @var list<string> */
        public array $modules = [],
    ) {}
}
