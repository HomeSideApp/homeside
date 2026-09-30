<?php

namespace Tests\Feature\Settings;

use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_appearance_settings_live_in_the_profile_page(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get('/settings/appearance')
            ->assertRedirect(route('profile.edit'));
    }

    public function test_profile_page_shares_the_persisted_checkbox_values(): void
    {
        $user = User::factory()->create([
            'households_enabled' => true,
            'shares_personal_products' => true,
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('auth.user.households_enabled', true)
                ->where('auth.user.shares_personal_products', true)
            );
    }

    public function test_profile_page_shares_the_household_management_permission(): void
    {
        $group = PermissionGroup::create(['name' => 'Hogares']);
        $permission = Permission::create([
            'name' => 'view households',
            'route_name' => 'households.index',
            'description' => 'Ver hogares',
            'permission_group_id' => $group->id,
        ]);
        $role = Role::create(['name' => 'User', 'slug' => 'user']);
        $role->permissions()->attach($permission);

        $user = User::factory()->create(['households_enabled' => true]);
        $user->assignRole($role);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('auth.user.households_enabled', true)
                ->where('auth.permissions', fn (Collection $permissions): bool => $permissions->contains('households.index'))
            );
    }

    public function test_shares_personal_products_can_be_enabled(): void
    {
        $user = User::factory()->create(['shares_personal_products' => false]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
                'shares_personal_products' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue($user->refresh()->shares_personal_products);
    }

    public function test_default_economy_destination_must_be_a_household_the_user_belongs_to(): void
    {
        $user = User::factory()->create();
        $own = Household::factory()->create(['created_by' => $user->id]);
        HouseholdMember::create([
            'user_id' => $user->id,
            'household_id' => $own->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $foreign = Household::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => 'es-ES',
                'active_household_id' => $foreign->id,
            ])
            ->assertSessionHasErrors('active_household_id');

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => 'es-ES',
                'active_household_id' => $own->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($own->id, $user->refresh()->active_household_id);
    }

    public function test_default_economy_destination_accepts_the_private_account(): void
    {
        $user = User::factory()->create(['active_household_id' => null]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => 'es-ES',
                'active_household_id' => null,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($user->refresh()->active_household_id);
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'locale' => 'en-US',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
                'locale' => 'en-US',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_households_can_be_disabled_without_removing_memberships(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['created_by' => $user->id]);
        HouseholdMember::factory()->create([
            'user_id' => $user->id,
            'household_id' => $household->id,
        ]);
        $user->update(['active_household_id' => $household->id]);

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
                'households_enabled' => false,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertFalse($user->households_enabled);
        $this->assertNull($user->active_household_id);
        $this->assertTrue($user->isMemberOf($household));
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
