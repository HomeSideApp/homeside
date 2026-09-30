<?php

namespace App\Services;

use App\Enums\HouseholdModule;
use App\Models\Household;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * Resolves household module availability, dependencies and features.
 */
final class HouseholdModuleService
{
    /**
     * Calculate the warnings when changing the module state.
     *
     * Only generates warnings when an ACTIVE module depends on another one
     * that is being disabled.
     *
     * @param  array<string, bool>  $newModulesState
     * @return array<int, array{module: string, module_label: string, missing_dependency: string, missing_dependency_label: string, message: string, severity: string, affected_features: array<string>}>
     */
    public static function getWarnings(array $newModulesState): array
    {
        $warnings = [];
        $config = self::modulesConfig();

        foreach ($config as $moduleName => $moduleConfig) {
            // Si este módulo está DESACTIVADO, no importa qué dependencias tenga
            if (! ($newModulesState[$moduleName] ?? true)) {
                continue;
            }

            // Si este módulo ESTÁ ACTIVO, revisamos si alguna de sus dependencias se desactiva
            foreach ($moduleConfig['dependencies'] as $depName => $depInfo) {
                if (! ($newModulesState[$depName] ?? true)) {
                    $warnings[] = [
                        'module' => $moduleName,
                        'module_label' => $moduleConfig['label'],
                        'missing_dependency' => $depName,
                        'missing_dependency_label' => $config[$depName]['label'],
                        'message' => $depInfo['warning'],
                        'severity' => $depInfo['severity'],
                        'affected_features' => $depInfo['affected_features'] ?? [],
                    ];
                }
            }
        }

        return $warnings;
    }

    /**
     * Check if a specific feature is available given the current module
     * state of the household.
     */
    public static function isFeatureAvailable(Household $household, string $feature): bool
    {
        $config = self::modulesConfig();

        foreach ($config as $moduleName => $moduleConfig) {
            if (isset($moduleConfig['features'][$feature])) {
                // Verificar que el módulo esté activo
                if (! $household->isModuleEnabled(HouseholdModule::from($moduleName))) {
                    return false;
                }

                // Verificar dependencias que afectan esta feature
                foreach ($moduleConfig['dependencies'] as $depName => $depInfo) {
                    $affectedFeatures = $depInfo['affected_features'] ?? [];

                    // Si no hay affected_features definidos, la dependencia afecta todas las features
                    if ($affectedFeatures === [] || in_array($feature, $affectedFeatures)) {
                        if (! $household->isModuleEnabled(HouseholdModule::from($depName))) {
                            return false;
                        }
                    }
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Get the available and unavailable features for a household.
     *
     * @return array{available: array<string, string>, unavailable: array<string, string>}
     */
    public static function getFeaturesStatus(Household $household): array
    {
        $config = self::modulesConfig();
        $available = [];
        $unavailable = [];

        foreach ($config as $moduleName => $moduleConfig) {
            foreach ($moduleConfig['features'] as $featureKey => $featureLabel) {
                if (self::isFeatureAvailable($household, $featureKey)) {
                    $available[$featureKey] = $featureLabel;
                } else {
                    $unavailable[$featureKey] = $featureLabel;
                }
            }
        }

        return ['available' => $available, 'unavailable' => $unavailable];
    }

    /**
     * Get all modules with their state for a household.
     *
     * @return Collection<int, array{module: string, label: string, description: string, icon: string, enabled: bool}>
     */
    public static function getModulesWithStatus(Household $household): Collection
    {
        $config = self::modulesConfig();
        $enabledModules = $household->modules->mapWithKeys(
            fn (\App\Models\HouseholdModule $module): array => [$module->module => $module->enabled],
        );

        return collect($config)->map(function ($moduleConfig, $moduleName) use ($enabledModules) {
            return [
                'module' => $moduleName,
                'label' => $moduleConfig['label'],
                'description' => $moduleConfig['description'],
                'icon' => $moduleConfig['icon'],
                'enabled' => $enabledModules->get($moduleName, true),
            ];
        })->values();
    }

    /**
     * Get the state of a module (active by default if there is no record).
     */
    public static function isModuleEnabled(Household $household, HouseholdModule $module): bool
    {
        foreach ($household->modules as $moduleRecord) {
            if ($moduleRecord->module === $module->value) {
                return $moduleRecord->enabled;
            }
        }

        return true;
    }

    /**
     * @return array<string, array{
     *     label: string,
     *     description: string,
     *     icon: string,
     *     dependencies: array<string, array{warning: string, severity: string, affected_features?: array<string>}>,
     *     features: array<string, string>
     * }>
     */
    /**
     * Read the modules configuration.
     *
     * @return array<string, array{
     *     label: string,
     *     description: string,
     *     icon: string,
     *     dependencies: array<string, array{warning: string, severity: string, affected_features?: array<string>}>,
     *     features: array<string, string>
     * }>
     */
    private static function modulesConfig(): array
    {
        return Config::array('household_modules.modules');
    }
}
