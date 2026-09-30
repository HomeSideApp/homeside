<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AiProviderModule;
use App\Models\Household;
use App\Models\User;
use HomeSide\AiAgents\Models\AiGlobalSetting;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\Providers\ProviderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithHousehold;
use Tests\TestCase;

class ProviderResolverTest extends TestCase
{
    use RefreshDatabase, WithHousehold;

    private ProviderResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(ProviderResolver::class);
    }

    public function test_resolves_household_provider_for_module(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        $provider = AiProvider::factory()->create([
            'household_id' => $this->household->id,
            'module' => AiProviderModule::Recipes,
            'enabled' => true,
        ]);

        $result = $this->resolver->resolve(AiProviderModule::Recipes->value, null, $this->household->id);

        $this->assertNotNull($result);
        $this->assertSame($provider->id, $result->id);
    }

    public function test_falls_back_to_global_provider(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        $globalProvider = AiProvider::factory()->create([
            'household_id' => null,
            'module' => AiProviderModule::Recipes,
            'enabled' => true,
        ]);

        $result = $this->resolver->resolve(AiProviderModule::Recipes->value, null, $this->household->id);

        $this->assertNotNull($result);
        $this->assertSame($globalProvider->id, $result->id);
    }

    public function test_returns_null_when_no_provider(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        $result = $this->resolver->resolve(AiProviderModule::Recipes->value, null, $this->household->id);

        $this->assertNull($result);
    }

    public function test_household_provider_takes_priority_over_global(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        $globalProvider = AiProvider::factory()->create([
            'household_id' => null,
            'module' => AiProviderModule::Recipes,
            'enabled' => true,
        ]);

        $householdProvider = AiProvider::factory()->create([
            'household_id' => $this->household->id,
            'module' => AiProviderModule::Recipes,
            'enabled' => true,
        ]);

        $result = $this->resolver->resolve(AiProviderModule::Recipes->value, null, $this->household->id);

        $this->assertNotNull($result);
        $this->assertSame($householdProvider->id, $result->id);
    }

    public function test_falls_back_to_general_module_global(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        $generalGlobal = AiProvider::factory()->create([
            'household_id' => null,
            'module' => AiProviderModule::General,
            'enabled' => true,
        ]);

        $result = $this->resolver->resolve(AiProviderModule::Recipes->value, null, $this->household->id);

        $this->assertNotNull($result);
        $this->assertSame($generalGlobal->id, $result->id);
    }

    public function test_resolves_global_provider_for_module(): void
    {
        $globalProvider = AiProvider::factory()->create([
            'household_id' => null,
            'module' => AiProviderModule::Recipes,
            'enabled' => true,
        ]);

        $result = $this->resolver->resolve(AiProviderModule::Recipes->value);

        $this->assertNotNull($result);
        $this->assertSame($globalProvider->id, $result->id);
    }

    public function test_disabled_global_provider_is_not_used(): void
    {
        AiProvider::factory()->create([
            'household_id' => null,
            'module' => AiProviderModule::Recipes,
            'enabled' => false,
        ]);

        $result = $this->resolver->resolve(AiProviderModule::Recipes->value);

        $this->assertNull($result);
    }

    public function test_global_prompt_is_combined(): void
    {
        AiGlobalSetting::updateOrCreate(
            ['module' => null],
            ['extra_prompt' => 'Global instructions.']
        );

        AiGlobalSetting::updateOrCreate(
            ['module' => 'recipes'],
            ['extra_prompt' => 'Recipe-specific instructions.']
        );

        $globalPrompt = AiGlobalSetting::global()->value('extra_prompt');
        $modulePrompt = AiGlobalSetting::forModule('recipes')->value('extra_prompt');

        $this->assertSame('Global instructions.', $globalPrompt);
        $this->assertSame('Recipe-specific instructions.', $modulePrompt);
    }

    public function test_is_default_provider_takes_priority(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        $defaultProvider = AiProvider::factory()->create([
            'household_id' => $this->household->id,
            'module' => AiProviderModule::Recipes,
            'enabled' => true,
            'is_default' => true,
        ]);

        AiProvider::factory()->create([
            'household_id' => $this->household->id,
            'module' => AiProviderModule::Recipes,
            'enabled' => true,
            'is_default' => false,
        ]);

        $result = $this->resolver->resolve(AiProviderModule::Recipes->value, null, $this->household->id);

        $this->assertNotNull($result);
        $this->assertSame($defaultProvider->id, $result->id);
    }

    /**
     * Confirm that a module-specific user provider overrides household and system providers.
     *
     * @return void This test does not return a value.
     */
    public function test_user_provider_takes_priority_over_household_and_system_providers(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        AiProvider::factory()->create([
            'household_id' => null,
            'module' => AiProviderModule::Recipes,
        ]);
        AiProvider::factory()->create([
            'household_id' => $this->household->id,
            'module' => AiProviderModule::Recipes,
        ]);
        $userProvider = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
            'module' => AiProviderModule::Recipes,
        ]);

        $result = $this->resolver->resolve(AiProviderModule::Recipes->value, $user->id, $this->household->id);

        $this->assertNotNull($result);
        $this->assertSame($userProvider->id, $result->id);
    }

    /**
     * Confirm the package's degradation semantics: a household provider
     * serving the REQUESTED module replaces the user's general provider,
     * which only matched through module degradation — the same privacy
     * level, so the policy allows the upgrade.
     *
     * @return void This test does not return a value.
     */
    public function test_household_module_provider_upgrades_degraded_user_general_provider(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        $householdProvider = AiProvider::factory()->create([
            'household_id' => $this->household->id,
            'module' => AiProviderModule::Recipes,
        ]);
        $userProvider = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
            'module' => AiProviderModule::General,
        ]);

        $result = $this->resolver->resolve(AiProviderModule::Recipes->value, $user->id, $this->household->id);

        $this->assertNotNull($result);
        $this->assertSame($householdProvider->id, $result->id);
        $this->assertNotSame($userProvider->id, $result->id);
    }

    /**
     * Confirm that personal operations may use the user's active household provider as fallback.
     *
     * @return void This test does not return a value.
     */
    public function test_personal_context_falls_back_to_active_household_provider(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);

        $householdProvider = AiProvider::factory()->create([
            'household_id' => $this->household->id,
            'module' => AiProviderModule::General,
        ]);

        $result = $this->resolver->resolve(AiProviderModule::Economy->value, $user->id);

        $this->assertNotNull($result);
        $this->assertSame($householdProvider->id, $result->id);
    }

    /**
     * Confirm that an explicit household provider is ignored when the user is not a member.
     *
     * @return void This test does not return a value.
     */
    public function test_inaccessible_household_provider_is_skipped_for_user_context(): void
    {
        $user = User::factory()->create();
        $this->createHouseholdForUser($user);
        $foreignHousehold = Household::factory()->create();
        AiProvider::factory()->create([
            'household_id' => $foreignHousehold->id,
            'module' => AiProviderModule::Economy,
        ]);
        $systemProvider = AiProvider::factory()->create([
            'household_id' => null,
            'module' => AiProviderModule::Economy,
        ]);

        $result = $this->resolver->resolve(AiProviderModule::Economy->value, $user->id, $foreignHousehold->id);

        $this->assertNotNull($result);
        $this->assertSame($systemProvider->id, $result->id);
    }

    /**
     * Confirm that a user-owned provider is never exposed as a system provider.
     *
     * @return void This test does not return a value.
     */
    public function test_user_provider_is_not_resolved_as_system_provider(): void
    {
        $user = User::factory()->create();
        AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
            'module' => AiProviderModule::Recipes,
        ]);

        $result = $this->resolver->resolve(AiProviderModule::Recipes->value);

        $this->assertNull($result);
    }

    /**
     * Confirm that selecting a default provider does not alter another scope or module.
     *
     * @return void This test does not return a value.
     */
    public function test_marking_default_only_updates_the_same_scope_and_module(): void
    {
        $user = User::factory()->create();
        $systemProvider = AiProvider::factory()->create([
            'household_id' => null,
            'module' => AiProviderModule::General,
            'is_default' => true,
        ]);
        $otherModuleProvider = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
            'module' => AiProviderModule::Economy,
            'is_default' => true,
        ]);
        $userProvider = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
            'module' => AiProviderModule::General,
            'is_default' => false,
        ]);

        $userProvider->markAsDefault();

        $this->assertTrue($userProvider->fresh()->is_default);
        $this->assertTrue($systemProvider->fresh()->is_default);
        $this->assertTrue($otherModuleProvider->fresh()->is_default);
    }
}
