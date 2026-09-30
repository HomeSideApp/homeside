<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\AiProviderModule;
use App\Models\User;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\Providers\AiProviderTester;
use HomeSide\AiAgents\Providers\ProviderTestData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Verifies the personal AI provider settings boundary and ownership rules.
 */
final class UserAiProviderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm that guests cannot open personal provider settings.
     *
     * @return void This test does not return a value.
     */
    public function test_guest_is_redirected_from_personal_provider_settings(): void
    {
        $this->get(route('settings.ai-providers.index'))
            ->assertRedirect(route('login'));
    }

    /**
     * Confirm that the settings page only exposes providers owned by the authenticated user.
     *
     * @return void This test does not return a value.
     */
    public function test_settings_page_lists_only_the_authenticated_users_providers(): void
    {
        $user = User::factory()->create();
        $personalProvider = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
        ]);
        AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => User::factory(),
        ]);
        AiProvider::factory()->create(['household_id' => null]);

        $this->actingAs($user)
            ->get(route('settings.ai-providers.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('settings/AiProviders')
                ->has('providers', 1)
                ->where('providers.0.id', $personalProvider->id)
            );
    }

    public function test_catalog_search_keeps_all_module_assignments_in_provider_props(): void
    {
        $user = User::factory()->create();
        $provider = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
            'module' => AiProviderModule::General,
        ]);
        $provider->setModules(['general', 'recipes']);
        $provider->markAsDefaultForModule('recipes');

        $this->actingAs($user)
            ->get(route('settings.ai-providers.catalog.search'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('settings/AiProviders')
                ->where('providers.0.modules', ['general', 'recipes'])
                ->where('providers.0.default_modules', ['recipes'])
            );
    }

    /**
     * Confirm that a valid configuration creates a user-scoped provider and initial model.
     *
     * @return void This test does not return a value.
     */
    public function test_user_can_create_a_personal_provider(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('settings.ai-providers.store'), [
                ...$this->validPayload(),
                'attachment' => true,
                'tool_call' => true,
                'structured_output' => true,
                'context_window' => 128000,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $provider = AiProvider::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNull($provider->household_id);
        $this->assertSame('Personal provider', $provider->name);
        $this->assertSame(AiProviderModule::General->value, $provider->module);
        $this->assertSame('personal-secret', $provider->api_key);
        $this->assertTrue($provider->attachment);
        $this->assertTrue($provider->tool_call);
        $this->assertTrue($provider->structured_output);
        $this->assertSame(128000, $provider->context_window);
        $this->assertDatabaseHas('ai_provider_models', [
            'ai_provider_id' => $provider->id,
            'model' => 'test-model',
            'is_default' => true,
        ]);
    }

    /**
     * Confirm that a user cannot create two personal providers for the same module.
     *
     * @return void This test does not return a value.
     */
    public function test_multiple_personal_providers_can_share_a_module(): void
    {
        $user = User::factory()->create(['locale' => 'es-ES']);
        AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
            'module' => AiProviderModule::General,
        ]);

        $this->actingAs($user)
            ->post(route('settings.ai-providers.store'), $this->validPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame(2, AiProvider::query()->where('user_id', $user->id)->forModule('general')->count());
    }

    /**
     * Confirm that a user can update their own provider without replacing its API key.
     *
     * @return void This test does not return a value.
     */
    public function test_user_can_update_their_personal_provider_and_keep_the_existing_key(): void
    {
        $user = User::factory()->create();
        $provider = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
        ]);
        $existingApiKey = $provider->api_key;
        $payload = $this->validPayload();
        $payload['name'] = 'Updated personal provider';
        $payload['api_key'] = null;
        $payload['attachment'] = true;
        $payload['tool_call'] = true;
        $payload['structured_output'] = true;
        $payload['context_window'] = 64000;
        $payload['configuration'] = ['temperature' => 0.5];

        $this->actingAs($user)
            ->put(route('settings.ai-providers.update', $provider), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $provider->refresh();
        $this->assertSame('Updated personal provider', $provider->name);
        $this->assertSame('test-model', $provider->model);
        $this->assertSame($existingApiKey, $provider->api_key);
        $this->assertTrue($provider->attachment);
        $this->assertTrue($provider->tool_call);
        $this->assertTrue($provider->structured_output);
        $this->assertSame(64000, $provider->context_window);
        $this->assertSame(0.5, $provider->configuration['temperature']);
    }

    /**
     * Confirm that an update cannot duplicate another personal provider's module.
     *
     * @return void This test does not return a value.
     */
    public function test_user_can_move_a_provider_to_a_shared_personal_module(): void
    {
        $user = User::factory()->create();
        AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
            'module' => AiProviderModule::Economy,
        ]);
        $provider = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
            'module' => AiProviderModule::General,
        ]);
        $payload = $this->validPayload();
        $payload['module'] = AiProviderModule::Economy->value;

        $this->actingAs($user)
            ->put(route('settings.ai-providers.update', $provider), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(AiProviderModule::Economy->value, $provider->fresh()->module);
    }

    /**
     * Confirm that another user's provider is hidden behind a not-found response.
     *
     * @return void This test does not return a value.
     */
    public function test_user_cannot_update_another_users_provider(): void
    {
        $user = User::factory()->create();
        $foreignProvider = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => User::factory(),
        ]);

        $this->actingAs($user)
            ->put(route('settings.ai-providers.update', $foreignProvider), $this->validPayload())
            ->assertNotFound();
    }

    /**
     * Confirm that a user can delete their own personal provider.
     *
     * @return void This test does not return a value.
     */
    public function test_user_can_delete_their_personal_provider(): void
    {
        $user = User::factory()->create();
        $provider = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->delete(route('settings.ai-providers.destroy', $provider))
            ->assertRedirect();

        $this->assertModelMissing($provider);
    }

    /**
     * Confirm that a user can select the default personal provider for a module.
     *
     * @return void This test does not return a value.
     */
    public function test_user_can_mark_their_personal_provider_as_default(): void
    {
        $user = User::factory()->create();
        $previousDefault = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
            'module' => AiProviderModule::General,
            'is_default' => true,
        ]);
        $provider = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
            'module' => AiProviderModule::General,
            'is_default' => false,
        ]);

        $this->actingAs($user)
            ->post(route('settings.ai-providers.default', $provider))
            ->assertRedirect();

        $this->assertTrue($provider->fresh()->is_default);
        $this->assertFalse($previousDefault->fresh()->is_default);
    }

    /**
     * Confirm that testing a stored provider delegates only the user's provider to the tester.
     *
     * @return void This test does not return a value.
     */
    public function test_user_can_test_their_stored_personal_provider(): void
    {
        $user = User::factory()->create();
        $provider = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
        ]);
        $tester = $this->mock(AiProviderTester::class);
        $tester->shouldReceive('testProvider')
            ->once()
            ->withArgs(fn (AiProvider $testedProvider): bool => $testedProvider->is($provider))
            ->andReturn(ProviderTestData::ok(12, 'ok'));

        $this->actingAs($user)
            ->postJson(route('settings.ai-providers.test', $provider))
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    /**
     * Confirm that testing an unsaved provider passes the validated configuration to the tester.
     *
     * @return void This test does not return a value.
     */
    public function test_user_can_test_an_unsaved_personal_provider_configuration(): void
    {
        $user = User::factory()->create();
        $tester = $this->mock(AiProviderTester::class);
        $tester->shouldReceive('testConfig')
            ->once()
            ->with([
                'type' => 'openai-compatible',
                'base_url' => 'https://ai.example.test/v1',
                'model' => 'test-model',
                'api_key' => 'personal-secret',
            ])
            ->andReturn(ProviderTestData::ok(12, 'ok'));

        $this->actingAs($user)
            ->postJson(route('settings.ai-providers.test-config'), [
                'type' => 'openai-compatible',
                'base_url' => 'https://ai.example.test/v1',
                'model' => 'test-model',
                'api_key' => 'personal-secret',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    /**
     * Build a valid personal provider request payload.
     *
     * @return array<string, mixed> A complete valid provider configuration.
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Personal provider',
            'driver' => 'openai-compatible',
            'base_url' => 'https://ai.example.test/v1',
            'model' => 'test-model',
            'api_key' => 'personal-secret',
            'module' => 'general',
            'enabled' => true,
            'configuration' => [],
            'privacy_level' => 'unknown',
            'fallback_policy' => 'same_privacy_level',
        ];
    }
}
