<?php

namespace Tests\Unit;

use App\Enums\HouseholdModule;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Services\HouseholdModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HouseholdModuleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Household $household;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['created_by' => $this->user->id]);
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);
    }

    public function test_get_warnings_returns_empty_when_all_modules_enabled(): void
    {
        $warnings = HouseholdModuleService::getWarnings([
            'shopping_lists' => true,
            'recipes' => true,
            'economy' => true,
        ]);

        $this->assertEmpty($warnings);
    }

    public function test_get_warnings_returns_warning_when_recipes_active_but_shopping_lists_disabled(): void
    {
        $warnings = HouseholdModuleService::getWarnings([
            'shopping_lists' => false,
            'recipes' => true,
            'economy' => false,
        ]);

        $this->assertCount(1, $warnings);
        $this->assertEquals('recipes', $warnings[0]['module']);
        $this->assertEquals('shopping_lists', $warnings[0]['missing_dependency']);
    }

    public function test_get_warnings_returns_warnings_for_economy_without_dependencies(): void
    {
        $warnings = HouseholdModuleService::getWarnings([
            'shopping_lists' => false,
            'recipes' => false,
            'economy' => true,
        ]);

        $this->assertCount(2, $warnings);
        $modules = array_column($warnings, 'missing_dependency');
        $this->assertContains('shopping_lists', $modules);
        $this->assertContains('recipes', $modules);
    }

    public function test_get_warnings_returns_empty_when_all_disabled(): void
    {
        $warnings = HouseholdModuleService::getWarnings([
            'shopping_lists' => false,
            'recipes' => false,
            'economy' => false,
        ]);

        $this->assertEmpty($warnings);
    }

    public function test_get_warnings_only_warns_for_active_modules(): void
    {
        // Shopping lists disabled but recipes also disabled - no warning needed
        $warnings = HouseholdModuleService::getWarnings([
            'shopping_lists' => false,
            'recipes' => false,
            'economy' => true,
        ]);

        // Only economy warnings (2), not recipes warnings
        $this->assertCount(2, $warnings);
        $this->assertEmpty(array_filter($warnings, fn ($w) => $w['module'] === 'recipes'));
    }

    public function test_is_module_enabled_defaults_to_true_when_no_record(): void
    {
        $this->assertTrue(HouseholdModuleService::isModuleEnabled(
            $this->household,
            HouseholdModule::ShoppingLists
        ));
    }

    public function test_is_module_enabled_returns_false_when_disabled(): void
    {
        $this->household->modules()->create([
            'module' => HouseholdModule::ShoppingLists->value,
            'enabled' => false,
        ]);

        $this->assertFalse(HouseholdModuleService::isModuleEnabled(
            $this->household,
            HouseholdModule::ShoppingLists
        ));
    }

    public function test_is_module_enabled_returns_true_when_enabled(): void
    {
        $this->household->modules()->create([
            'module' => HouseholdModule::ShoppingLists->value,
            'enabled' => true,
        ]);

        $this->assertTrue(HouseholdModuleService::isModuleEnabled(
            $this->household,
            HouseholdModule::ShoppingLists
        ));
    }

    public function test_get_modules_with_status_returns_all_modules(): void
    {
        $modules = HouseholdModuleService::getModulesWithStatus($this->household);

        $this->assertCount(3, $modules);
        $moduleSlugs = array_column($modules->toArray(), 'module');
        $this->assertContains('shopping_lists', $moduleSlugs);
        $this->assertContains('recipes', $moduleSlugs);
        $this->assertContains('economy', $moduleSlugs);
    }

    public function test_get_features_status_returns_array_structure(): void
    {
        $features = HouseholdModuleService::getFeaturesStatus($this->household);

        $this->assertArrayHasKey('available', $features);
        $this->assertArrayHasKey('unavailable', $features);
        $this->assertIsArray($features['available']);
        $this->assertIsArray($features['unavailable']);
    }

    public function test_get_features_status_with_shopping_lists_disabled(): void
    {
        $this->household->modules()->create([
            'module' => HouseholdModule::ShoppingLists->value,
            'enabled' => false,
        ]);

        // Reload modules
        $this->household->load('modules');

        $features = HouseholdModuleService::getFeaturesStatus($this->household);

        // Shopping lists features should be unavailable
        $this->assertArrayHasKey('create_lists', $features['unavailable']);
        $this->assertArrayHasKey('add_items', $features['unavailable']);
    }
}
