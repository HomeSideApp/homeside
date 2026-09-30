<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $group = PermissionGroup::create(['name' => 'Listas']);
        Permission::create(['name' => 'view lists', 'route_name' => 'lists.index', 'description' => 'Ver listas', 'permission_group_id' => $group->id]);

        $role = Role::create(['name' => 'user', 'slug' => 'user']);
        $role->syncPermissions(['view lists']);
    }

    public function test_user_with_2fa_can_request_otp(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);
        $user->assignRole('user');

        $response = $this->postJson(route('api.v1.auth.login'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'temp_token']);
    }

    public function test_user_without_2fa_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);
        $user->assignRole('user');

        $response = $this->postJson(route('api.v1.auth.login'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertForbidden();
    }

    public function test_invalid_credentials_returns_401(): void
    {
        $response = $this->postJson(route('api.v1.auth.login'), [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertUnauthorized();
    }

    public function test_validation_error_returns_422(): void
    {
        $response = $this->postJson(route('api.v1.auth.login'), [
            'email' => '',
            'password' => '',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
