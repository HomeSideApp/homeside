<?php

namespace App\Data\Admin;

use App\Enums\AiProviderModule;

/**
 * Data for updating the system prompt of a specific module.
 */
final readonly class UpdateModulePromptData
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            module: AiProviderModule::from($data['module']),
            extra_prompt: (string) ($data['extra_prompt'] ?? ''),
        );
    }

    public function __construct(
        public AiProviderModule $module,
        public string $extra_prompt,
    ) {}
}
