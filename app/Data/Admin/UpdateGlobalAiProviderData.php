<?php

namespace App\Data\Admin;

use App\Enums\AiProviderModule;
use HomeSide\AiAgents\Enums\AiDriver;
use HomeSide\AiAgents\Enums\FallbackPolicy;
use HomeSide\AiAgents\Enums\PrivacyLevel;

final readonly class UpdateGlobalAiProviderData
{
    /**
     * @param  array<string, mixed>  $data
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
            api_key: (string) ($data['api_key'] ?? ''),
            module: AiProviderModule::from($data['module'] ?? $data['modules'][0]),
            modules: array_map(fn (string $module): string => AiProviderModule::from($module)->value, $data['modules'] ?? [$data['module']]),
            enabled: (bool) ($data['enabled'] ?? true),
            configuration: $data['configuration'] ?? [],
            privacy_level: $privacyLevel,
            fallback_policy: $fallbackPolicy,
        );
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function __construct(
        public string $name,
        public AiDriver $driver,
        public string $base_url,
        public string $model,
        public string $api_key,
        public AiProviderModule $module,
        public bool $enabled,
        public array $configuration,
        public PrivacyLevel $privacy_level = PrivacyLevel::Unknown,
        public FallbackPolicy $fallback_policy = FallbackPolicy::SamePrivacyLevel,
        /** @var list<string> */
        public array $modules = [],
    ) {}
}
