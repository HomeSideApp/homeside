<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\AiProviderModule;
use App\Models\User;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\Providers\AiProviderTester;
use HomeSide\AiAgents\Providers\ProviderTestData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verifies the personal AI provider API boundary.
 */
final class UserAiProviderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_user_is_unauthenticated(): void
    {
        $this->getJson(route('api.v1.me.ai-providers.index'))
            ->assertUnauthorized();
    }

    public function test_lists_only_authenticated_users_providers_without_secrets(): void
    {
        $user = User::factory()->create();
        $provider = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
        ]);
        AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => User::factory(),
        ]);
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.me.ai-providers.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $provider->id)
            ->assertJsonMissingPath('data.0.api_key')
            ->assertJsonMissingPath('data.0.api_key');
    }

    public function test_valid_payload_creates_personal_provider_and_returns_201(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.me.ai-providers.store'), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Personal provider');

        $this->assertDatabaseHas('ai_providers', [
            'household_id' => null,
            'user_id' => $user->id,
            'module' => AiProviderModule::General->value,
        ]);
    }

    public function test_personal_provider_can_serve_multiple_modules_and_select_a_module_default(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $payload = $this->validPayload();
        unset($payload['module']);
        $payload['modules'] = ['recipes', 'economy'];

        $response = $this->postJson(route('api.v1.me.ai-providers.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.modules', ['economy', 'recipes']);

        $provider = AiProvider::findOrFail($response->json('data.id'));
        $this->postJson(route('api.v1.me.ai-providers.default', $provider), ['module' => 'economy'])
            ->assertOk()
            ->assertJsonPath('data.default_modules', ['economy']);

        $this->assertSame(['economy', 'recipes'], $provider->assignedModules());
    }

    public function test_rejects_module_and_modules_together(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $payload = $this->validPayload();
        $payload['modules'] = ['recipes'];

        $this->postJson(route('api.v1.me.ai-providers.store'), $payload)
            ->assertUnprocessable();
    }

    public function test_returns_404_when_updating_another_users_provider(): void
    {
        $user = User::factory()->create();
        $foreignProvider = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => User::factory(),
        ]);
        Sanctum::actingAs($user);

        $this->putJson(
            route('api.v1.me.ai-providers.update', $foreignProvider),
            $this->validPayload(),
        )->assertNotFound();
    }

    public function test_valid_payload_updates_owned_provider(): void
    {
        $user = User::factory()->create();
        $provider = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
        ]);
        Sanctum::actingAs($user);
        $payload = $this->validPayload();
        $payload['name'] = 'Updated provider';
        $payload['api_key'] = null;

        $this->putJson(route('api.v1.me.ai-providers.update', $provider), $payload)
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated provider');

        $this->assertSame('Updated provider', $provider->fresh()->name);
    }

    public function test_delete_owned_provider_returns_204(): void
    {
        $user = User::factory()->create();
        $provider = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson(route('api.v1.me.ai-providers.destroy', $provider))
            ->assertNoContent();

        $this->assertModelMissing($provider);
    }

    public function test_marks_owned_provider_as_default(): void
    {
        $user = User::factory()->create();
        $provider = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
            'is_default' => false,
        ]);
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.me.ai-providers.default', $provider))
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertTrue($provider->fresh()->is_default);
    }

    public function test_unsaved_configuration_is_validated_and_tested(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
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

        $this->postJson(route('api.v1.me.ai-providers.test-config'), [
            'type' => 'openai-compatible',
            'base_url' => 'https://ai.example.test/v1',
            'model' => 'test-model',
            'api_key' => 'personal-secret',
        ])->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_stored_owned_provider_can_be_tested(): void
    {
        $user = User::factory()->create();
        $provider = AiProvider::factory()->create([
            'household_id' => null,
            'user_id' => $user->id,
        ]);
        Sanctum::actingAs($user);
        $tester = $this->mock(AiProviderTester::class);
        $tester->shouldReceive('testProvider')
            ->once()
            ->withArgs(fn (AiProvider $testedProvider): bool => $testedProvider->is($provider))
            ->andReturn(ProviderTestData::ok(12, 'ok'));

        $this->postJson(route('api.v1.me.ai-providers.test', $provider))
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'name' => 'Personal provider',
            'driver' => 'openai-compatible',
            'base_url' => 'https://ai.example.test/v1',
            'model' => 'test-model',
            'api_key' => 'personal-secret',
            'module' => AiProviderModule::General->value,
            'enabled' => true,
            'configuration' => [],
            'privacy_level' => 'unknown',
            'fallback_policy' => 'same_privacy_level',
        ];
    }
}
