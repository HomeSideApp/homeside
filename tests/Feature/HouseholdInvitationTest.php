<?php

namespace Tests\Feature;

use App\Enums\HouseholdRole;
use App\Enums\InvitationStatus;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\Role;
use App\Models\User;
use App\Notifications\HouseholdInvitationNotification;
use App\Notifications\HouseholdInviteNewUserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class HouseholdInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $group = PermissionGroup::create(['name' => 'Hogares']);
        Permission::create(['name' => 'view households', 'route_name' => 'households.index', 'description' => 'Ver hogares', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'create household form', 'route_name' => 'households.create', 'description' => 'Formulario crear hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'store households', 'route_name' => 'households.store', 'description' => 'Guardar hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'view household', 'route_name' => 'households.show', 'description' => 'Ver un hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'update households', 'route_name' => 'households.update', 'description' => 'Actualizar hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'delete households', 'route_name' => 'households.destroy', 'description' => 'Eliminar hogar', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'invite members', 'route_name' => 'households.invite', 'description' => 'Invitar miembros', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'remove members', 'route_name' => 'households.remove', 'description' => 'Eliminar miembros', 'permission_group_id' => $group->id]);
        Permission::create(['name' => 'switch household', 'route_name' => 'households.switch', 'description' => 'Cambiar hogar activo', 'permission_group_id' => $group->id]);

        // Create roles
        $this->role = Role::create(['name' => 'user', 'slug' => 'user']);
        $this->role->syncPermissions(Permission::all());

        Role::firstOrCreate(['slug' => 'user-invitation'], ['name' => 'User - Invitation', 'is_system' => true]);

        $this->user = User::factory()->create();
        $this->user->assignRole($this->role);
    }

    public function test_existing_user_receives_invitation_email(): void
    {
        Notification::fake();

        $household = Household::factory()->create(['created_by' => $this->user->id]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
            'joined_at' => now(),
        ]);

        $invitedUser = User::factory()->create();

        $response = $this->actingAs($this->user)->postJson(route('api.v1.households.invite', $household), [
            'email' => $invitedUser->email,
        ]);

        $response->assertCreated();

        Notification::assertSentTo(
            $invitedUser,
            HouseholdInvitationNotification::class
        );
    }

    public function test_new_user_receives_new_user_invitation_email(): void
    {
        Notification::fake();

        $household = Household::factory()->create(['created_by' => $this->user->id]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('api.v1.households.invite', $household), [
            'email' => 'newuser@example.com',
        ]);

        $response->assertCreated();

        $newUser = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->hasRole('user-invitation'));

        Notification::assertSentTo(
            $newUser,
            HouseholdInviteNewUserNotification::class
        );
    }

    public function test_pending_invitations_appear_in_shared_props(): void
    {
        $household = Household::factory()->create(['created_by' => $this->user->id]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
            'joined_at' => now(),
        ]);

        HouseholdInvitation::create([
            'household_id' => $household->id,
            'invited_by' => $this->user->id,
            'email' => $this->user->email,
            'status' => InvitationStatus::Pending,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->user)->get(route('households.select'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('households/Select')
            ->has('pendingInvitations', 1)
        );
    }

    public function test_user_can_accept_invitation_from_list(): void
    {
        $household = Household::factory()->create(['created_by' => $this->user->id]);

        $invitation = HouseholdInvitation::create([
            'household_id' => $household->id,
            'invited_by' => $this->user->id,
            'email' => $this->user->email,
            'status' => InvitationStatus::Pending,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->user)->post(route('households.invitations.accept', $invitation));

        $response->assertRedirect();

        $this->assertDatabaseHas('household_members', [
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Member->value,
        ]);

        $this->assertDatabaseHas('household_invitations', [
            'id' => $invitation->id,
            'status' => InvitationStatus::Accepted->value,
        ]);
    }

    public function test_user_can_cancel_invitation(): void
    {
        $household = Household::factory()->create(['created_by' => $this->user->id]);

        $invitation = HouseholdInvitation::create([
            'household_id' => $household->id,
            'invited_by' => $this->user->id,
            'email' => $this->user->email,
            'status' => InvitationStatus::Pending,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->user)->delete(route('households.invitations.cancel', $invitation));

        $response->assertRedirect();

        $this->assertDatabaseHas('household_invitations', [
            'id' => $invitation->id,
            'status' => InvitationStatus::Cancelled->value,
        ]);
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $household = Household::factory()->create(['created_by' => $this->user->id]);

        $invitation = HouseholdInvitation::create([
            'household_id' => $household->id,
            'invited_by' => $this->user->id,
            'email' => $this->user->email,
            'status' => InvitationStatus::Pending,
            'token' => Str::random(64),
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->user)->post(route('households.invitations.accept', $invitation));

        $response->assertStatus(409);
    }

    public function test_already_accepted_invitation_cannot_be_accepted_again(): void
    {
        $household = Household::factory()->create(['created_by' => $this->user->id]);

        $invitation = HouseholdInvitation::create([
            'household_id' => $household->id,
            'invited_by' => $this->user->id,
            'email' => $this->user->email,
            'status' => InvitationStatus::Accepted,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->post(route('households.invitations.accept', $invitation));

        $response->assertStatus(409);
    }

    public function test_first_invitation_sets_active_household(): void
    {
        $this->user->update(['active_household_id' => null]);

        $household = Household::factory()->create(['created_by' => $this->user->id]);

        $invitation = HouseholdInvitation::create([
            'household_id' => $household->id,
            'invited_by' => $this->user->id,
            'email' => $this->user->email,
            'status' => InvitationStatus::Pending,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($this->user)->post(route('households.invitations.accept', $invitation));

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'active_household_id' => $household->id,
        ]);
    }

    public function test_invitation_creates_member_with_member_role(): void
    {
        $household = Household::factory()->create(['created_by' => $this->user->id]);

        $invitation = HouseholdInvitation::create([
            'household_id' => $household->id,
            'invited_by' => $this->user->id,
            'email' => $this->user->email,
            'status' => InvitationStatus::Pending,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->actingAs($this->user)->post(route('households.invitations.accept', $invitation));

        $member = HouseholdMember::where('household_id', $household->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($member);
        $this->assertEquals(HouseholdRole::Member, $member->role);
    }

    public function test_user_can_accept_invitation_via_email_link(): void
    {
        $household = Household::factory()->create(['created_by' => $this->user->id]);

        $invitation = HouseholdInvitation::create([
            'household_id' => $household->id,
            'invited_by' => $this->user->id,
            'email' => $this->user->email,
            'status' => InvitationStatus::Pending,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->user)->get(route('households.accept', $invitation->token));

        $response->assertRedirect();

        $this->assertDatabaseHas('household_members', [
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Member->value,
        ]);

        $this->assertDatabaseHas('household_invitations', [
            'id' => $invitation->id,
            'accepted_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_reinviting_cancels_previous_pending_invitation(): void
    {
        $household = Household::factory()->create(['created_by' => $this->user->id]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => HouseholdRole::Admin,
            'joined_at' => now(),
        ]);

        $invitedUser = User::factory()->create();

        // First invitation
        $firstInvitation = HouseholdInvitation::create([
            'household_id' => $household->id,
            'invited_by' => $this->user->id,
            'email' => $invitedUser->email,
            'status' => InvitationStatus::Pending,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        // Re-invite the same user
        $response = $this->actingAs($this->user)->postJson(route('api.v1.households.invite', $household), [
            'email' => $invitedUser->email,
        ]);

        $response->assertCreated();

        // First invitation should be cancelled
        $this->assertDatabaseHas('household_invitations', [
            'id' => $firstInvitation->id,
            'status' => InvitationStatus::Cancelled->value,
        ]);

        // Only one pending invitation should exist
        $pendingCount = HouseholdInvitation::where('household_id', $household->id)
            ->where('email', $invitedUser->email)
            ->where('status', InvitationStatus::Pending)
            ->count();
        $this->assertEquals(1, $pendingCount);
    }
}
