<?php

namespace App\Support;

use App\Models\Permission;
use BackedEnum;

/**
 * Reads the Community-owned capability registry declared in config/capabilities.php.
 *
 * Features declare permission prefixes and this class resolves them against the
 * real `permissions.route_name` rows, so adding a permission inside an existing
 * area needs no config change. Quotas are declarative metered limits.
 *
 * This class is deliberately not registered as a singleton: the resolved index
 * is memoized per request on the request attributes, so long-lived workers and
 * tests never see state leak between requests. A missing or malformed config is
 * tolerated and treated as empty.
 */
final class CapabilityRegistry
{
    /** Request-attribute key holding the permission index resolved for this request. */
    private const PERMISSION_INDEX_KEY = 'capabilities.permission_index';

    /**
     * Resolve every declared feature with its translated label and matched permissions.
     *
     * @return list<array{key: string, label: string, module: string|null, metric: string|null, permissions: list<string>}>
     */
    public function features(): array
    {
        $index = $this->permissionIndex();

        $features = [];
        foreach ($this->featuresConfig() as $key => $feature) {
            if (! is_array($feature)) {
                continue;
            }

            $features[] = [
                'key' => (string) $key,
                'label' => __((string) ($feature['label'] ?? $key)),
                'module' => $this->normalizeModule($feature['module'] ?? null),
                'metric' => $this->normalizeMetric($feature['metric'] ?? null),
                'permissions' => $this->resolvePermissions($feature['permission_prefixes'] ?? [], $index),
            ];
        }

        return $features;
    }

    /**
     * Resolve every declared quota with its translated label.
     *
     * @return list<array{key: string, label: string, unit: string}>
     */
    public function quotas(): array
    {
        $quotas = [];
        foreach ($this->quotasConfig() as $key => $quota) {
            if (! is_array($quota)) {
                continue;
            }

            $quotas[] = [
                'key' => (string) $key,
                'label' => __((string) ($quota['label'] ?? $key)),
                'unit' => (string) ($quota['unit'] ?? ''),
            ];
        }

        return $quotas;
    }

    public function hasFeature(string $key): bool
    {
        return is_array($this->featuresConfig()[$key] ?? null);
    }

    public function hasQuota(string $key): bool
    {
        return is_array($this->quotasConfig()[$key] ?? null);
    }

    public function metric(string $featureKey): ?string
    {
        return $this->normalizeMetric($this->featureConfig($featureKey)['metric'] ?? null);
    }

    /**
     * @return list<string>
     */
    public function permissionsOf(string $featureKey): array
    {
        $prefixes = $this->featureConfig($featureKey)['permission_prefixes'] ?? [];

        return $this->resolvePermissions($prefixes, $this->permissionIndex());
    }

    public function moduleOf(string $featureKey): ?string
    {
        return $this->normalizeModule($this->featureConfig($featureKey)['module'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function featureConfig(string $featureKey): array
    {
        $feature = $this->featuresConfig()[$featureKey] ?? null;

        return is_array($feature) ? $feature : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        $config = config('capabilities');

        return is_array($config) ? $config : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function featuresConfig(): array
    {
        $features = $this->config()['features'] ?? [];

        return is_array($features) ? $features : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function quotasConfig(): array
    {
        $quotas = $this->config()['quotas'] ?? [];

        return is_array($quotas) ? $quotas : [];
    }

    /**
     * The sorted route_name rows, resolved once per request and memoized on the
     * request attributes, matching the convention used by HasPermissionsTrait.
     *
     * @return list<string>
     */
    private function permissionIndex(): array
    {
        $request = request();

        if ($request->attributes->has(self::PERMISSION_INDEX_KEY)) {
            return $request->attributes->get(self::PERMISSION_INDEX_KEY);
        }

        $index = Permission::query()
            ->pluck('route_name')
            ->filter(fn ($routeName): bool => is_string($routeName) && $routeName !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $request->attributes->set(self::PERMISSION_INDEX_KEY, $index);

        return $index;
    }

    /**
     * @param  list<string>  $index
     * @return list<string>
     */
    private function resolvePermissions(mixed $prefixes, array $index): array
    {
        if (! is_array($prefixes) || $prefixes === []) {
            return [];
        }

        $resolved = [];
        foreach ($index as $routeName) {
            foreach ($prefixes as $prefix) {
                if (is_string($prefix) && $prefix !== '' && str_starts_with($routeName, $prefix)) {
                    $resolved[] = $routeName;
                    break;
                }
            }
        }

        return array_values($resolved);
    }

    private function normalizeModule(mixed $module): ?string
    {
        if ($module instanceof BackedEnum) {
            return (string) $module->value;
        }

        return is_string($module) && $module !== '' ? $module : null;
    }

    private function normalizeMetric(mixed $metric): ?string
    {
        return is_string($metric) && $metric !== '' ? $metric : null;
    }
}
