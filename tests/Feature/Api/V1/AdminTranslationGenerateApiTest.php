<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use HomeSide\AiAgents\AiAgentManager;
use HomeSide\AiAgents\Execution\AiExecutionResultData;
use HomeSide\AiAgents\Execution\AiUsageData;
use HomeSide\AiAgents\Models\AiAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class AdminTranslationGenerateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = PermissionGroup::create(['name' => 'Admin']);

        // The api.permission middleware strips the `api.v1.` prefix and resolves
        // the web permission name, so both rows are required.
        foreach (['api.v1.admin.translations.generate', 'admin.translations.generate'] as $routeName) {
            Permission::create([
                'name' => $routeName,
                'route_name' => $routeName,
                'description' => $routeName,
                'permission_group_id' => $adminGroup->id,
            ]);
        }

        AiAgent::query()->updateOrCreate(['key' => 'translations.generator'], ['module' => 'translations', 'label' => 'Translation generator', 'platform_prompt' => 'Test prompt.', 'enabled' => true]);
    }

    private function createAdmin(): User
    {
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->givePermissionTo(Permission::all());
        $admin->assignRole($role);

        return $admin;
    }

    private function createRegularUser(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $user->assignRole($role);

        return $user;
    }

    private function aiResult(string $reply): AiExecutionResultData
    {
        return new AiExecutionResultData(
            runId: fake()->uuid(),
            agent: 'translations.generator',
            agentVersion: 1,
            provider: 'test-provider',
            model: 'test-model',
            status: 'ok',
            reply: $reply,
            usage: new AiUsageData(
                inputTokens: 0,
                outputTokens: 0,
                totalTokens: 0,
                latencyMs: 1,
            ),
            structured: true,
        );
    }

    public function test_guest_gets_unauthorized(): void
    {
        $this->postJson(route('api.v1.admin.translations.generate'), [
            'translatable_type' => 'category',
            'locale' => 'es-ES',
        ])->assertUnauthorized();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = $this->createRegularUser();
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.admin.translations.generate'), [
            'translatable_type' => 'category',
            'locale' => 'es-ES',
        ])->assertForbidden();
    }

    public function test_admin_generates_translations_and_gets_the_result_payload(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $tagged = Category::create(['name' => 'Fanta', 'slug' => 'fanta', 'color' => '#00FF00']);

        $agentManager = Mockery::mock(AiAgentManager::class);
        $agentManager->shouldReceive('run')->once()->andReturn($this->aiResult(json_encode([
            'translations' => [
                ['client_id' => $category->id, 'value' => 'Frutas'],
            ],
            'unmatched' => [$tagged->id],
        ], JSON_THROW_ON_ERROR)));
        $this->app->instance(AiAgentManager::class, $agentManager);

        $response = $this->postJson(route('api.v1.admin.translations.generate'), [
            'translatable_type' => 'category',
            'locale' => 'es-ES',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.translated', 1)
            ->assertJsonPath('data.unmatched.0', $tagged->id)
            ->assertJsonPath('data.failed', []);

        $this->assertDatabaseHas('translations', [
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'value' => 'Frutas',
        ]);
    }

    public function test_returns_422_when_no_ai_provider_is_configured(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);
        Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);

        $response = $this->postJson(route('api.v1.admin.translations.generate'), [
            'translatable_type' => 'category',
            'locale' => 'es-ES',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('code', 'translation_generation_failed')
            ->assertJsonStructure(['message', 'code', 'errors']);

        $this->assertDatabaseCount('translations', 0);
    }

    public function test_rejects_invalid_payload(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $this->postJson(route('api.v1.admin.translations.generate'), [
            'translatable_type' => 'rocket',
            'locale' => 'not a locale',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['translatable_type', 'locale']);

        $this->postJson(route('api.v1.admin.translations.generate'), [
            'translatable_type' => 'category',
            'locale' => 'es-ES',
            'ids' => [Str::uuid()->toString(), 'not-a-uuid'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.1']);
    }
}
