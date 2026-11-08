<?php

namespace Tests\Feature\Saas;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Support\CapabilityRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Covers the Community-owned capability registry.
 *
 * The registry is pure data plus an empirical prefix match against the real
 * `permissions.route_name` rows, so the central property under test is that
 * adding a permission inside an existing area is picked up with no config
 * change at all.
 */
class CapabilityRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_features_returns_every_declared_key_with_resolved_permissions(): void
    {
        $features = app(CapabilityRegistry::class)->features();

        $this->assertSame(
            ['lists', 'recipes', 'economy', 'contacts', 'ai_image_recognition', 'ai_assistant'],
            array_column($features, 'key'),
        );

        $byKey = collect($features)->keyBy('key');

        foreach (['lists', 'recipes', 'economy', 'contacts'] as $key) {
            $this->assertNotEmpty($byKey[$key]['permissions'], "Feature [{$key}] should resolve permissions.");
            foreach ($byKey[$key]['permissions'] as $routeName) {
                $this->assertIsString($routeName);
            }
        }

        // The ai_* features gate metered operations, not permission-backed routes.
        $this->assertSame([], $byKey['ai_image_recognition']['permissions']);
        $this->assertSame([], $byKey['ai_assistant']['permissions']);

        $this->assertSame('shopping_lists', $byKey['lists']['module']);
        $this->assertSame('recipes', $byKey['recipes']['module']);
        $this->assertSame('economy', $byKey['economy']['module']);
        $this->assertNull($byKey['contacts']['module']);

        $this->assertNull($byKey['lists']['metric']);
        $this->assertSame('ai_image_monthly', $byKey['ai_image_recognition']['metric']);
        $this->assertSame('ai_assistant_monthly', $byKey['ai_assistant']['metric']);
    }

    public function test_a_new_permission_under_an_existing_prefix_resolves_without_config_changes(): void
    {
        $registry = app(CapabilityRegistry::class);
        $before = $registry->permissionsOf('lists');

        $this->assertNotEmpty($before);
        $this->assertNotContains('households.lists.brand-new-action', $before);

        // A later migration adds a permission inside the existing lists area.
        Permission::create([
            'name' => 'brand new list action',
            'route_name' => 'households.lists.brand-new-action',
            'description' => 'Added by a later migration',
            'permission_group_id' => PermissionGroup::create(['name' => 'Registry test'])->id,
        ]);

        $this->assertSame(['households.lists.'], config('capabilities.features.lists.permission_prefixes'));

        // The registry memoizes only for the current request, so a later request
        // (simulated by rebinding a fresh request) resolves the index again.
        $this->app->instance('request', Request::create('/'));

        $after = app(CapabilityRegistry::class)->permissionsOf('lists');

        $this->assertNotContains('households.lists.brand-new-action', $before);
        $this->assertContains('households.lists.brand-new-action', $after);
        $this->assertCount(count($before) + 1, $after);
    }

    public function test_metric_returns_the_declared_metric_key_or_null(): void
    {
        $registry = app(CapabilityRegistry::class);

        $this->assertSame('ai_assistant_monthly', $registry->metric('ai_assistant'));
        $this->assertSame('ai_image_monthly', $registry->metric('ai_image_recognition'));
        $this->assertNull($registry->metric('recipes'));
        $this->assertNull($registry->metric('unknown_feature'));
    }

    public function test_unknown_feature_and_quota_keys_fail_closed(): void
    {
        $registry = app(CapabilityRegistry::class);

        $this->assertTrue($registry->hasFeature('recipes'));
        $this->assertTrue($registry->hasQuota('storage_mb'));

        $this->assertFalse($registry->hasFeature('unknown_feature'));
        $this->assertFalse($registry->hasQuota('unknown_quota'));

        $this->assertSame([], $registry->permissionsOf('unknown_feature'));
        $this->assertNull($registry->moduleOf('unknown_feature'));
    }

    public function test_labels_resolve_through_the_translator_in_both_locales(): void
    {
        $labels = [];

        foreach (['en-US', 'es-ES'] as $locale) {
            app()->setLocale($locale);

            $feature = collect(app(CapabilityRegistry::class)->features())->firstWhere('key', 'recipes');
            $quota = collect(app(CapabilityRegistry::class)->quotas())->firstWhere('key', 'households_own');

            $this->assertNotSame('', $feature['label']);
            $this->assertNotSame('capabilities.features.recipes', $feature['label']);
            $this->assertNotSame('', $quota['label']);
            $this->assertNotSame('capabilities.quotas.households_own', $quota['label']);

            $labels[$locale] = $feature['label'];
        }

        $this->assertNotSame($labels['en-US'], $labels['es-ES']);
    }

    public function test_an_empty_config_yields_empty_arrays_without_throwing(): void
    {
        config(['capabilities' => []]);

        $registry = app(CapabilityRegistry::class);

        $this->assertSame([], $registry->features());
        $this->assertSame([], $registry->quotas());
        $this->assertFalse($registry->hasFeature('recipes'));
        $this->assertFalse($registry->hasQuota('storage_mb'));
        $this->assertNull($registry->metric('ai_assistant'));
        $this->assertSame([], $registry->permissionsOf('lists'));
    }
}
