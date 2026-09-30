<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;

final class HouseholdConfigurationController extends Controller
{
    public function show(): JsonResponse
    {
        $modules = collect(config('household_modules.modules'))
            ->map(fn (array $module, string $key): array => [
                'key' => $key,
                'label' => __($module['label']),
                'description' => __($module['description']),
                'icon' => $module['icon'],
                'default_enabled' => true,
                'dependencies' => array_keys($module['dependencies'] ?? []),
            ])
            ->values();
        $predefinedTags = collect(config('household_modules.predefined_tags', []))
            ->map(fn (string $slug, string $label): array => [
                'key' => $slug,
                'label' => $label,
            ]);
        $availableTags = $predefinedTags
            ->concat(Tag::query()->withTranslationData()->orderBy('name')->get()->map(fn (Tag $tag): array => [
                'key' => $tag->slug,
                'label' => $tag->localized('name'),
            ]))
            ->unique('key')
            ->sortBy('label')
            ->values();

        return response()->json(['data' => [
            'modules' => $modules,
            'available_tags' => $availableTags,
            'features' => [
                'available' => collect(config('household_modules.modules'))->flatMap(fn (array $module) => $module['features'] ?? [])->all(),
                'unavailable' => [],
            ],
        ]]);
    }
}
