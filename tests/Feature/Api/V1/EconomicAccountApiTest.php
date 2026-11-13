<?php

namespace Tests\Feature\Api\V1;

use App\Models\EconomicAccount;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La API aceptaba `account_id` al crear un movimiento pero no exponía forma de
 * listar cuentas: ningún cliente móvil podía ofrecer la elección.
 */
class EconomicAccountApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        // El middleware `api.permission` resuelve por fila de permiso: sin ella
        // responde 404 "route_permission_not_configured". Los tests no corren seeders.
        $group = PermissionGroup::firstOrCreate(
            ['name' => 'Economía'],
            ['description' => 'Permisos de economía'],
        );

        Permission::firstOrCreate(
            ['route_name' => 'economy.me.accounts.index'],
            [
                'name' => 'economy.me.accounts.index',
                'description' => 'Ver cuentas',
                'permission_group_id' => $group->id,
            ],
        );

        $user = User::factory()->create();
        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->permissions()->sync(Permission::where('route_name', 'economy.me.accounts.index')->pluck('id')->all());
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_it_lists_the_users_own_accounts(): void
    {
        $user = $this->actingAsUser();

        EconomicAccount::factory()->create(['user_id' => $user->id, 'name' => 'Cuenta corriente']);
        EconomicAccount::factory()->create(['user_id' => User::factory()->create()->id, 'name' => 'Ajena']);

        $response = $this->getJson(route('api.v1.economy.me.accounts.index'));

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name');

        $this->assertContains('Cuenta corriente', $names);
        // Las cuentas de otros usuarios no se exponen.
        $this->assertNotContains('Ajena', $names);
    }

    public function test_archived_accounts_are_hidden_unless_asked_for(): void
    {
        $user = $this->actingAsUser();

        EconomicAccount::factory()->create(['user_id' => $user->id, 'name' => 'Vieja', 'archived_at' => now()]);

        $this->assertSame([], $this->getJson(route('api.v1.economy.me.accounts.index'))->json('data'));

        $withArchived = $this->getJson(route('api.v1.economy.me.accounts.index', ['include_archived' => '1']))
            ->json('data');

        $this->assertSame(['Vieja'], array_column($withArchived, 'name'));
    }
}
