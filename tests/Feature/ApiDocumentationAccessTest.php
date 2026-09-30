<?php

namespace Tests\Feature;

use App\Http\Requests\ConfirmEconomicImportApiRequest;
use App\Http\Requests\UpdateEconomicTransactionApiRequest;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ApiDocumentationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('scramble.docs.ui'))
            ->assertRedirect(route('login'));

        $this->get(route('scramble.docs.document'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->createDocumentationPermission();

        $this->actingAs($user)
            ->get(route('scramble.docs.ui'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('scramble.docs.document'))
            ->assertForbidden();
    }

    public function test_user_with_permission_can_view_documentation(): void
    {
        $user = User::factory()->create();
        $permission = $this->createDocumentationPermission();
        $role = Role::create(['name' => 'Documentation', 'slug' => 'documentation']);
        $role->givePermissionTo([$permission]);
        $user->assignRole($role);

        $this->actingAs($user)
            ->get(route('scramble.docs.ui'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('scramble.docs.document'))
            ->assertOk()
            ->assertJsonPath('openapi', '3.1.0');
    }

    public function test_mobile_contract_operation_ids_are_published_and_unique(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)
            ->get(route('scramble.docs.document'))
            ->assertOk()
            ->assertJsonPath('openapi', '3.1.0');

        $operationIds = collect($response->json('paths'))
            ->flatMap(fn (array $operations): array => array_values($operations))
            ->pluck('operationId')
            ->filter()
            ->values();

        foreach ([
            'v1.households.invitations.index',
            'v1.households.configuration.show',
            'v1.households.dashboard',
            'v1.households.economy.overview',
            'v1.economy.me.overview',
            'v1.households.lists.items.quick-create',
            'v1.recipes.image.store',
            'v1.assistant.conversations.index',
            'v1.me.two-factor.setup',
            'v1.admin.ai-usage.index',
        ] as $operationId) {
            $this->assertContains($operationId, $operationIds);
        }

        $this->assertSame($operationIds->count(), $operationIds->unique()->count());
    }

    public function test_mobile_contract_documents_idempotency_and_proposal_variants(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)
            ->get(route('scramble.docs.document'))
            ->assertOk()
            ->assertJsonPath('components.schemas.AiProposalResource.discriminator.propertyName', 'type')
            ->assertJsonCount(2, 'components.schemas.AiProposalResource.oneOf')
            ->assertJsonPath('components.schemas.AssistantAddShoppingItemsProposal.properties.type.const', 'add_shopping_items')
            ->assertJsonPath('components.schemas.AssistantAddShoppingItemsProposal.properties.payload.type', 'object')
            ->assertJsonPath('components.schemas.AssistantCreateRecipeProposal.properties.type.const', 'create_recipe')
            ->assertJsonPath('components.schemas.AssistantCreateRecipeProposal.properties.payload.properties.recipe.$ref', '#/components/schemas/StoreRecipeApiRequest');

        $quickCreateParameters = collect($response->json('paths./households/{household}/lists/{list}/items/quick-create.post.parameters'));
        $idempotencyKey = $quickCreateParameters->firstWhere('name', 'Idempotency-Key');

        $this->assertIsArray($idempotencyKey);
        $this->assertSame('header', $idempotencyKey['in']);
        $this->assertTrue($idempotencyKey['required']);
        $this->assertSame(255, $idempotencyKey['schema']['maxLength']);

        $recipeGenerationParameters = collect($response->json('paths./recipes/ai-generate.post.parameters'));
        $optionalIdempotencyKey = $recipeGenerationParameters->firstWhere('name', 'Idempotency-Key');

        $this->assertIsArray($optionalIdempotencyKey);
        $this->assertArrayNotHasKey('required', $optionalIdempotencyKey);
    }

    public function test_seeder_assigns_documentation_permission_to_admin_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $permission = Permission::query()
            ->where('route_name', 'scramble.docs.ui')
            ->firstOrFail();
        $admin = Role::query()->where('slug', 'admin')->firstOrFail();
        $user = Role::query()->where('slug', 'user')->firstOrFail();
        $adminGroupId = PermissionGroup::query()->where('name', 'Admin')->value('id');

        $this->assertSame($adminGroupId, $permission->permission_group_id);
        $this->assertTrue($admin->hasPermissionTo('view api documentation'));
        $this->assertFalse($user->hasPermissionTo('view api documentation'));
    }

    public function test_economy_request_rules_can_be_documented_without_route_context(): void
    {
        $updateRules = (new UpdateEconomicTransactionApiRequest)->rules();
        $confirmationRules = (new ConfirmEconomicImportApiRequest)->rules();

        $this->assertArrayHasKey('participants.*.household_member_id', $updateRules);
        $this->assertArrayHasKey('participants.*.household_member_id', $confirmationRules);
    }

    private function createDocumentationPermission(): Permission
    {
        $permissionGroup = PermissionGroup::create(['name' => 'Admin']);

        return Permission::create([
            'name' => 'view api documentation',
            'route_name' => 'scramble.docs.ui',
            'description' => 'Ver documentación de la API',
            'permission_group_id' => $permissionGroup->id,
        ]);
    }
}
