<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UpdateHouseholdSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected Household $household;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $group = PermissionGroup::create(['name' => 'Hogares']);
        Permission::create(['name' => 'edit households', 'route_name' => 'households.edit', 'description' => 'Editar hogares', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'update households', 'route_name' => 'households.update', 'description' => 'Actualizar hogares', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'edit household settings', 'route_name' => 'households.settings.edit', 'description' => 'Editar configuración del hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'update household settings', 'route_name' => 'households.settings.update', 'description' => 'Actualizar configuración del hogar', 'permission_group_id' => $group->id]);

        // Create role with permissions
        $role = Role::create(['name' => 'admin', 'slug' => 'admin']);
        $role->syncPermissions(['edit households', 'update households', 'edit household settings', 'update household settings']);

        // Create user and assign role
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        // Create household
        $this->household = Household::factory()->create(['created_by' => $this->user->id]);
        HouseholdMember::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);
    }

    public function test_admin_can_update_household_name(): void
    {
        $response = $this->actingAs($this->user)
            ->put("/households/{$this->household->id}/settings", [
                'name' => 'Mi hogar actualizado',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('households', [
            'id' => $this->household->id,
            'name' => 'Mi hogar actualizado',
        ]);
    }

    public function test_admin_can_update_description(): void
    {
        $response = $this->actingAs($this->user)
            ->put("/households/{$this->household->id}/settings", [
                'name' => $this->household->name,
                'description' => 'Una descripción de prueba',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('households', [
            'id' => $this->household->id,
            'description' => 'Una descripción de prueba',
        ]);
    }

    public function test_admin_can_upload_image(): void
    {
        Storage::fake('public');

        $image = UploadedFile::fake()->image('household.jpg', 200, 200);

        $response = $this->actingAs($this->user)
            ->put("/households/{$this->household->id}/settings", [
                'name' => $this->household->name,
                'image' => $image,
            ]);

        $response->assertRedirect();
        $this->assertNotNull($this->household->fresh()->image_url);
    }

    public function test_admin_can_remove_image(): void
    {
        Storage::fake('local');

        // First, set an image on the household
        $this->household->update(['image_url' => 'households/'.$this->household->id.'/test.jpg']);

        $response = $this->actingAs($this->user)
            ->put("/households/{$this->household->id}/settings", [
                'name' => $this->household->name,
                'remove_image' => true,
            ]);

        $response->assertRedirect();
        $this->assertNull($this->household->fresh()->image_url);
    }

    public function test_admin_can_toggle_modules(): void
    {
        $response = $this->actingAs($this->user)
            ->put("/households/{$this->household->id}/settings", [
                'name' => $this->household->name,
                'modules' => [
                    'shopping_lists' => true,
                    'recipes' => false,
                    'economy' => true,
                ],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('household_modules', [
            'household_id' => $this->household->id,
            'module' => 'recipes',
            'enabled' => false,
        ]);

        $this->assertDatabaseHas('household_modules', [
            'household_id' => $this->household->id,
            'module' => 'shopping_lists',
            'enabled' => true,
        ]);
    }

    public function test_admin_can_add_predefined_tags(): void
    {
        Tag::create(['name' => 'Familia', 'slug' => 'familia', 'type' => 'predefined']);
        Tag::create(['name' => 'Pareja', 'slug' => 'pareja', 'type' => 'predefined']);

        $response = $this->actingAs($this->user)
            ->put("/households/{$this->household->id}/settings", [
                'name' => $this->household->name,
                'tags' => ['Familia', 'Pareja'],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('tags', ['slug' => 'familia']);
        $this->assertDatabaseHas('tags', ['slug' => 'pareja']);
        $this->assertCount(2, $this->household->fresh()->tags);
    }

    public function test_admin_can_create_custom_tags(): void
    {
        $response = $this->actingAs($this->user)
            ->put("/households/{$this->household->id}/settings", [
                'name' => $this->household->name,
                'tags' => ['Mi tag personalizado'],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('tags', [
            'slug' => 'mi-tag-personalizado',
            'type' => 'custom',
        ]);
        $this->assertCount(1, $this->household->fresh()->tags);
    }

    public function test_non_member_is_redirected(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->put("/households/{$this->household->id}/settings", [
                'name' => 'Hogar hackeado',
            ]);

        // Middleware redirects non-members before controller can check authorization
        $response->assertRedirect();
    }

    public function test_member_without_permission_is_redirected(): void
    {
        $member = User::factory()->create();
        HouseholdMember::create([
            'user_id' => $member->id,
            'household_id' => $this->household->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($member)
            ->put("/households/{$this->household->id}/settings", [
                'name' => 'Hogar modificado',
            ]);

        // Member gets 403 from permission middleware (no permission)
        $response->assertForbidden();
    }

    public function test_name_is_required(): void
    {
        $response = $this->actingAs($this->user)
            ->put("/households/{$this->household->id}/settings", [
                'name' => '',
            ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_description_max_length(): void
    {
        $response = $this->actingAs($this->user)
            ->put("/households/{$this->household->id}/settings", [
                'name' => $this->household->name,
                'description' => str_repeat('a', 1001),
            ]);

        $response->assertSessionHasErrors('description');
    }

    public function test_edit_settings_page_loads(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/households/{$this->household->id}/settings/edit");

        $response->assertOk();
    }

    public function test_admin_can_set_default_split_type(): void
    {
        $response = $this->actingAs($this->user)
            ->put("/households/{$this->household->id}/settings", [
                'name' => $this->household->name,
                'default_split_type' => 'percentage',
            ]);

        $response->assertRedirect();

        $module = $this->household->fresh()->modules()
            ->where('module', 'economy')
            ->first();

        $this->assertNotNull($module);
        $this->assertSame('percentage', $module->settings['default_split_type'] ?? null);
    }

    public function test_default_split_type_rejects_unknown_values(): void
    {
        $response = $this->actingAs($this->user)
            ->put("/households/{$this->household->id}/settings", [
                'name' => $this->household->name,
                'default_split_type' => 'banana',
            ]);

        $response->assertSessionHasErrors('default_split_type');
    }

    public function test_edit_settings_page_exposes_default_split_type(): void
    {
        $this->household->modules()->updateOrCreate(
            ['module' => 'economy'],
            ['enabled' => true, 'settings' => ['default_split_type' => 'fixed']],
        );

        $this->actingAs($this->user)
            ->get("/households/{$this->household->id}/settings/edit")
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('households/settings/Edit')
                ->where('default_split_type', 'fixed'));
    }
}
