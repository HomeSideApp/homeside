<?php

namespace App\Actions\Households;

use App\Enums\HouseholdModule as HouseholdModuleEnum;
use App\Enums\SplitType;
use App\Models\Household;
use App\Models\Tag;
use App\Services\HouseholdModuleService;

/**
 * Gathers all data needed to render the household settings screen.
 */
final class GetHouseholdSettings
{
    /**
     * @param  Household  $household  The household model instance.
     * @return array{
     *     household: array{id: string, name: string, description: string|null, color: string|null, image_url: string|null},
     *     modules: array<int, array{module: string, label: string, description: string, icon: string, enabled: bool}>,
     *     tags: array<int, array{id: string, name: string, slug: string, type: string}>,
     *     available_tags: array<int, array{id: string, name: string, slug: string, type: string}>,
     *     features: array{available: array<string, string>, unavailable: array<string, string>},
     *     warnings: array<int, array<string, mixed>>,
     *     default_split_type: string,
     * }
     */
    public function execute(Household $household): array
    {
        // Cargar relaciones
        $household->load(['modules', 'tags']);

        // Obtener módulos con estado
        $modules = HouseholdModuleService::getModulesWithStatus($household)->toArray();

        // Obtener estado actual de módulos para calcular warnings
        $currentModulesState = [];
        foreach ($modules as $module) {
            $currentModulesState[$module['module']] = $module['enabled'];
        }

        // Calcular warnings basados en el estado actual
        $warnings = HouseholdModuleService::getWarnings($currentModulesState);

        // Obtener features disponibles
        $features = HouseholdModuleService::getFeaturesStatus($household);

        // Tags asignados al hogar
        $tags = $household->tags->map(fn (Tag $tag): array => [
            'id' => $tag->id,
            'name' => $tag->localized('name'),
            'slug' => $tag->slug,
            'type' => $tag->type,
        ])->toArray();

        // Tags disponibles (predefinidos no asignados + personalizados existentes no asignados)
        $assignedTagIds = $household->tags->pluck('id')->toArray();
        $availableTags = Tag::whereNotIn('id', $assignedTagIds)
            ->withTranslationData()
            ->get()
            ->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->localized('name'),
                'slug' => $tag->slug,
                'type' => $tag->type,
            ])->toArray();

        return [
            'household' => [
                'id' => $household->id,
                'name' => $household->name,
                'description' => $household->description,
                'color' => $household->color,
                'image_url' => $household->image_url ? route('households.image', $household->id) : null,
            ],
            'modules' => $modules,
            'tags' => $tags,
            'available_tags' => $availableTags,
            'features' => $features,
            'warnings' => $warnings,
            'default_split_type' => $this->defaultSplitType($household),
        ];
    }

    /**
     * Resolve the household default expense split type, falling back to equal shares.
     *
     * @param  Household  $household  The household model instance.
     * @return string The SplitType value configured for the household.
     */
    private function defaultSplitType(Household $household): string
    {
        $economyModule = $household->modules->first(
            fn ($module): bool => $module->module === HouseholdModuleEnum::Economy->value,
        );

        $configured = ($economyModule?->settings ?? [])['default_split_type'] ?? null;

        if ($configured !== null && in_array($configured, array_column(SplitType::cases(), 'value'), true)) {
            return $configured;
        }

        return SplitType::Equal->value;
    }
}
