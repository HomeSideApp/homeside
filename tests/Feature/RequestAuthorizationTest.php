<?php

namespace Tests\Feature;

use App\Enums\HouseholdRole;
use App\Models\Contact;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use HomeSide\AiAgents\Models\AiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RequestAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([['roles' => []]])]
    #[TestWith([['can_create_households' => 'invalid']])]
    public function test_user_data_editor_cannot_submit_administrative_fields_even_when_invalid(array $fields): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $role = Role::create(['name' => 'Data editor', 'slug' => 'request-data-editor']);
        $role->syncPermissions([Permission::query()->where('route_name', 'admin.users.update')->firstOrFail()]);
        $actor = User::factory()->create();
        $actor->assignRole($role);
        $target = User::factory()->create(['name' => 'Original']);

        $this->actingAs($actor)->patchJson(route('api.v1.admin.users.update', $target), ['name' => 'Changed', ...$fields])->assertForbidden();

        $this->assertSame('Original', $target->refresh()->name);
        $this->assertFalse($target->hasRole('admin'));
    }

    #[TestWith(['en-US', 'api.v1.admin.roles.update', 409, 'System roles cannot be modified.'])]
    #[TestWith(['es-ES', 'api.v1.admin.roles.update', 409, 'No se puede modificar un rol del sistema.'])]
    #[TestWith(['en-US', 'admin.roles.update', 403, 'System roles cannot be modified.'])]
    #[TestWith(['es-ES', 'admin.roles.update', 403, 'No se puede modificar un rol del sistema.'])]
    public function test_system_role_rejection_is_localized_and_precedes_invalid_fields(string $locale, string $routeName, int $status, string $message): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $actor = User::factory()->create(['locale' => $locale]);
        $actor->assignRole('admin');
        $role = Role::query()->where('slug', 'user')->firstOrFail();
        $name = $role->name;

        $this->actingAs($actor)->json($routeName === 'admin.roles.update' ? 'PUT' : 'PATCH', route($routeName, $role), ['name' => []])
            ->assertStatus($status)->assertJsonPath('message', $message);

        $this->assertSame($name, $role->refresh()->name);
    }
}
