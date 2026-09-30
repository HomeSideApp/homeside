<?php

namespace App\Actions\Households;

use App\Data\Households\UpdateHouseholdSettingsData;
use App\Enums\HouseholdModule as HouseholdModuleEnum;
use App\Enums\HouseholdTagType;
use App\Models\Household;
use App\Models\HouseholdModule;
use App\Models\Tag;
use App\Services\HouseholdModuleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Updates the household's basic fields, image, modules and tags in a
 * single transaction.
 */
final class UpdateHouseholdSettings
{
    /**
     * @param  Household  $household  The household model instance.
     * @param  UpdateHouseholdSettingsData  $data  The data object containing the validated input.
     * @return array{household: Household, warnings: array<int, array<string, mixed>>}
     */
    public function execute(Household $household, UpdateHouseholdSettingsData $data): array
    {
        return DB::transaction(function () use ($household, $data) {
            // 1. Actualizar campos básicos
            $updateData = ['name' => $data->name];

            if ($data->description !== null) {
                $updateData['description'] = $data->description;
            }

            if ($data->color !== null) {
                $updateData['color'] = $data->color;
            }

            // 2. Manejar imagen (stored in local/private disk)
            if ($data->removeImage) {
                // Eliminar imagen existente y limpiar referencia
                if ($household->image_url && Storage::disk('local')->exists($household->image_url)) {
                    Storage::disk('local')->delete($household->image_url);
                }
                $updateData['image_url'] = null;
            } elseif ($data->image !== null) {
                // Eliminar imagen anterior si existe
                if ($household->image_url && Storage::disk('local')->exists($household->image_url)) {
                    Storage::disk('local')->delete($household->image_url);
                }

                $path = $data->image->store('households/'.$household->id, 'local');
                $updateData['image_url'] = $path;
            }

            $household->update($updateData);

            // 3. Sincronizar módulos
            if ($data->modules !== null) {
                $this->syncModules($household, $data->modules);
            }

            // 4. Persistir ajustes del módulo de economía (reparto por defecto)
            if ($data->defaultSplitType !== null) {
                $this->syncEconomySettings($household, $data->defaultSplitType);
            }

            // 5. Sincronizar tags
            if ($data->tags !== null) {
                $this->syncTags($household, $data->tags);
            }

            // 6. Calcular advertencias
            $warnings = [];
            if ($data->modules !== null) {
                $warnings = HouseholdModuleService::getWarnings($data->modules);
            }

            $household->refresh();

            return [
                'household' => $household->load(['modules', 'tags']),
                'warnings' => $warnings,
            ];
        });
    }

    /**
     * Sincroniza los módulos del hogar.
     *
     * @param  array<string, bool>  $modules
     */
    private function syncModules(Household $household, array $modules): void
    {
        foreach ($modules as $moduleSlug => $enabled) {
            $household->modules()->updateOrCreate(
                ['module' => $moduleSlug],
                ['enabled' => $enabled]
            );
        }
    }

    /**
     * Persist the economy module settings, keeping any unrelated settings untouched.
     *
     * @param  string  $defaultSplitType  The SplitType value used as the household default.
     */
    private function syncEconomySettings(Household $household, string $defaultSplitType): void
    {
        $module = $household->modules()->firstOrNew(['module' => HouseholdModuleEnum::Economy->value]);
        $settings = array_merge($module->settings ?? [], ['default_split_type' => $defaultSplitType]);
        $module->settings = $settings;
        $module->save();
    }

    /**
     * Sincroniza los tags del hogar.
     * Los tags predefinidos se buscan por slug, los personalizados se crean si no existen.
     *
     * @param  array<int, string>  $tags
     */
    private function syncTags(Household $household, array $tags): void
    {
        $tagIds = [];

        foreach ($tags as $tagName) {
            $slug = Str::slug($tagName);

            // Buscar tag existente por slug
            $tag = Tag::where('slug', $slug)->first();

            if (! $tag) {
                // Determinar si es predefinido o personalizado
                $predefinedSlugs = array_map(
                    fn (int|string $name): string => Str::slug((string) $name),
                    array_keys(config('household_modules.predefined_tags', [])),
                );
                $type = in_array($slug, $predefinedSlugs)
                    ? HouseholdTagType::Predefined
                    : HouseholdTagType::Custom;

                $tag = Tag::create([
                    'name' => $tagName,
                    'slug' => $slug,
                    'type' => $type->value,
                ]);
            }

            $tagIds[] = $tag->id;
        }

        $household->tags()->sync($tagIds);
    }
}
