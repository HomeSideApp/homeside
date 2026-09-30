<?php

namespace Tests\Feature\Admin;

use App\Enums\TranslationEntityStatus;
use App\Enums\TranslationFieldStatus;
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
use Mockery;
use Tests\TestCase;

class AdminTranslationsAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $adminGroup = PermissionGroup::create(['name' => 'Admin']);

        foreach (['admin.translations', 'admin.translations.generate'] as $routeName) {
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post(route('admin.translations.generate'), [
            'translatable_type' => 'category',
            'locale' => 'es-ES',
        ])->assertRedirect(route('login'));
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = $this->createRegularUser();

        $this->actingAs($user)->post(route('admin.translations.generate'), [
            'translatable_type' => 'category',
            'locale' => 'es-ES',
        ])->assertForbidden();
    }

    public function test_admin_generates_translations_and_gets_success_toast(): void
    {
        $admin = $this->createAdmin();
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);

        $agentManager = Mockery::mock(AiAgentManager::class);
        $agentManager->shouldReceive('run')->once()->andReturn($this->aiResult(json_encode([
            'translations' => [
                ['client_id' => $category->id, 'value' => 'Frutas'],
            ],
            'unmatched' => [],
        ], JSON_THROW_ON_ERROR)));
        $this->app->instance(AiAgentManager::class, $agentManager);

        $response = $this->actingAs($admin)->post(route('admin.translations.generate'), [
            'translatable_type' => 'category',
            'locale' => 'es-ES',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('translations', [
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'field' => 'name',
            'value' => 'Frutas',
            'status' => TranslationFieldStatus::Translated->value,
        ]);

        $status = $category->translationStatus()->forLocale('es-ES')->firstOrFail();
        $this->assertSame(TranslationEntityStatus::Complete, $status->status);
        $this->assertNull($status->published_at);

        $response->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __('app.toast.translations_generated', ['count' => 1]),
        ]);
    }

    public function test_generate_rejects_invalid_type(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->post(route('admin.translations.generate'), [
            'translatable_type' => 'rocket',
            'locale' => 'es-ES',
        ])->assertSessionHasErrors('translatable_type');
    }

    public function test_generate_rejects_invalid_locale(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->post(route('admin.translations.generate'), [
            'translatable_type' => 'category',
            'locale' => 'EN_US!!',
        ])->assertSessionHasErrors('locale');
    }

    public function test_generate_shows_error_toast_when_no_provider_is_configured(): void
    {
        $admin = $this->createAdmin();
        Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);

        $response = $this->actingAs($admin)->post(route('admin.translations.generate'), [
            'translatable_type' => 'category',
            'locale' => 'es-ES',
        ]);

        $response->assertRedirect();

        $response->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => __('app.errors.translation_generate_failed', [
                // The package's NoAiProviderException message propagates.
                'message' => 'No AI provider is configured for module [translations].',
            ]),
        ]);

        $this->assertDatabaseCount('translations', 0);
    }
}
