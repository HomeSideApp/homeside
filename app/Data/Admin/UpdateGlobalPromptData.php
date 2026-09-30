<?php

namespace App\Data\Admin;

/**
 * Data for updating the global system prompt.
 */
final readonly class UpdateGlobalPromptData
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            extra_prompt: (string) ($data['extra_prompt'] ?? ''),
        );
    }

    public function __construct(
        public string $extra_prompt,
    ) {}
}
