<?php

namespace App\Data\Households;

use App\Enums\AiProviderModule;

/**
 * Class UpdateModuleAiConfigData
 *
 * This class represents the data required to update the AI configuration of a
 * household module. It is a readonly data object that normalises the raw request
 * payload into the configuration fields.
 */
final readonly class UpdateModuleAiConfigData
{
    /**
     * Build a new instance from the raw request data.
     *
     * @param  array<string, mixed>  $data  The raw validated request data.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            module: AiProviderModule::from($data['module']),
            agent_name: (string) $data['agent_name'],
            label: (string) $data['label'],
            additional_instructions: $data['additional_instructions'] ?? null,
            description: $data['description'] ?? null,
            ai_provider_id: $data['ai_provider_id'] ?? null,
            model: $data['model'] ?? null,
            parameters: $data['parameters'] ?? null,
            enabled: (bool) ($data['enabled'] ?? true),
        );
    }

    /**
     * Initialise the data object.
     *
     * @param  array<string, mixed>|null  $parameters  Additional model parameters.
     */
    public function __construct(
        public AiProviderModule $module,
        public string $agent_name,
        public string $label,
        public ?string $additional_instructions,
        public ?string $description,
        public ?string $ai_provider_id,
        public ?string $model,
        public ?array $parameters,
        public bool $enabled,
    ) {}
}
