<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Household;
use App\Models\HouseholdInviteLink;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WithHousehold;
use Tests\TestCase;

/**
 * Verifies the reusable household invite links (create, register, limits).
 */
final class HouseholdInviteLinkTest extends TestCase
{
    use RefreshDatabase, WithHousehold;

    private User $user;

    /**
     * Seed permissions and authenticate the household admin.
     *
     * @return void This setup method does not return a value.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Role::firstOrCreate(['slug' => 'user-invitation'], ['name' => 'User - Invitation', 'is_system' => true]);

        $this->user = User::factory()->create();
        $this->user->assignRole('user');
        Role::where('slug', 'user')->firstOrFail()->syncPermissions(Permission::all());

        $this->createHouseholdForUser($this->user);
    }

    /**
     * Create an invite link directly with the given limits.
     *
     * @param  array<string, mixed>  $attributes  The link attributes
     * @return HouseholdInviteLink The persisted link
     */
    private function makeLink(array $attributes = []): HouseholdInviteLink
    {
        return HouseholdInviteLink::create(array_merge([
            'household_id' => $this->household->id,
            'created_by' => $this->user->id,
            'token' => 'token-'.str()->random(40),
        ], $attributes));
    }

    /**
     * Confirm that an admin can create an unlimited invite link and open its page.
     *
     * @return void This test method does not return a value.
     */
    public function test_admin_can_create_an_invite_link_and_open_its_page(): void
    {
        $this->actingAs($this->user)
            ->post(route('households.invite-links.store', $this->household), [])
            ->assertSessionHasNoErrors();

        $link = HouseholdInviteLink::firstOrFail();
        $this->assertNull($link->expires_at);
        $this->assertNull($link->max_uses);
        $this->assertSame(0, $link->uses_count);

        $this->get(route('invite-links.show', $link->token))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('auth/SetPassword')
                ->where('mode', 'link')
                ->where('householdName', $this->household->name));
    }

    /**
     * Confirm that registering through a link creates a verified member and consumes one use.
     *
     * @return void This test method does not return a value.
     */
    public function test_registration_through_a_link_creates_a_member_and_consumes_a_use(): void
    {
        $link = $this->makeLink();

        $this->post(route('invite-links.store', $link->token), [
            'token' => $link->token,
            'name' => 'Rafael Ortega',
            'email' => 'rafael@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect();

        $user = User::where('email', 'rafael@example.com')->firstOrFail();
        $this->assertSame('Rafael Ortega', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('user-invitation'));

        $member = HouseholdMember::where('household_id', $this->household->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        $this->assertNotNull($member->id);

        $this->assertSame(1, $link->fresh()->uses_count);

        // The link creator's address book gets the new member as contact
        $this->assertDatabaseHas('contact_emails', ['value' => 'rafael@example.com']);
        $contact = Contact::where('user_id', $this->user->id)->first();
        $this->assertNotNull($contact);
    }

    /**
     * Confirm that the settings page exposes the household invite links.
     *
     * @return void This test method does not return a value.
     */
    public function test_settings_page_exposes_invite_links(): void
    {
        $link = $this->makeLink(['max_uses' => 5]);

        $this->actingAs($this->user)
            ->get(route('households.settings.edit', $this->household))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('households/settings/Edit')
                ->has('invite_links', 1)
                ->where('invite_links.0.id', $link->id)
                ->where('invite_links.0.max_uses', 5));
    }

    /**
     * Confirm that a link stops accepting registrations once max_uses is reached.
     *
     * @return void This test method does not return a value.
     */
    public function test_registration_is_rejected_when_max_uses_is_reached(): void
    {
        $link = $this->makeLink(['max_uses' => 1, 'uses_count' => 1]);

        $this->post(route('invite-links.store', $link->token), [
            'token' => $link->token,
            'name' => 'Otro',
            'email' => 'otro@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'otro@example.com']);
    }

    /**
     * Confirm that an expired link stops accepting registrations.
     *
     * @return void This test method does not return a value.
     */
    public function test_registration_is_rejected_when_the_link_expired(): void
    {
        $link = $this->makeLink(['expires_at' => now()->subDay()]);

        $this->post(route('invite-links.store', $link->token), [
            'token' => $link->token,
            'email' => 'caducado@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertStatus(422);

        $this->get(route('invite-links.show', $link->token))->assertNotFound();
    }

    /**
     * Confirm that revoking a link blocks both the page and new registrations.
     *
     * @return void This test method does not return a value.
     */
    public function test_revoked_link_blocks_registrations(): void
    {
        $link = $this->makeLink();

        $this->actingAs($this->user)
            ->delete(route('households.invite-links.destroy', [$this->household, $link]))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($link->fresh()->revoked_at);

        $this->get(route('invite-links.show', $link->token))->assertNotFound();

        $this->post(route('invite-links.store', $link->token), [
            'token' => $link->token,
            'email' => 'revocado@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertStatus(422);
    }

    /**
     * Confirm that a token mismatch between route and payload is rejected.
     *
     * @return void This test method does not return a value.
     */
    public function test_registration_rejects_a_mismatched_token(): void
    {
        $link = $this->makeLink();

        $this->post(route('invite-links.store', $link->token), [
            'token' => 'another-token',
            'email' => 'spoofed@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'spoofed@example.com']);
    }

    /**
     * Confirm that a duplicate email is rejected during link registration.
     *
     * @return void This test method does not return a value.
     */
    public function test_registration_rejects_a_duplicated_email(): void
    {
        $link = $this->makeLink();
        $existing = User::factory()->create();

        $this->post(route('invite-links.store', $link->token), [
            'token' => $link->token,
            'email' => $existing->email,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertSessionHasErrors('email');
    }

    /**
     * Confirm the API lifecycle: create, public registration and reuse rejection.
     *
     * @return void This test method does not return a value.
     */
    public function test_api_invite_link_lifecycle(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('api.v1.households.invite-links.store', $this->household), [
                'max_uses' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.max_uses', 1);

        $link = HouseholdInviteLink::firstOrFail();

        $this->getJson(route('api.v1.invite-links.show', $link->token))
            ->assertOk()
            ->assertJsonPath('data.id', $link->id);

        $this->postJson(route('api.v1.invite-links.store', $link->token), [
            'token' => $link->token,
            'name' => 'API User',
            'email' => 'api-user@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'api-user@example.com')
            ->assertJsonPath('data.household.id', $this->household->id);

        $this->postJson(route('api.v1.invite-links.store', $link->token), [
            'token' => $link->token,
            'email' => 'second@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertStatus(422);

        $this->actingAs($this->user)
            ->deleteJson(route('api.v1.households.invite-links.destroy', [$this->household, $link]))
            ->assertNoContent();

        $this->assertNotNull($link->fresh()->revoked_at);
    }
}
