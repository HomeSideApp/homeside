<?php

namespace Tests\Feature;

use App\Actions\Contacts\SaveLocalContact;
use App\Data\Contacts\ContactData;
use App\Models\Contact;
use App\Models\ContactLabel;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WithHousehold;
use Tests\TestCase;

/**
 * Verifies the contact integration across household invitations and member listings.
 */
final class HouseholdContactLinkTest extends TestCase
{
    use RefreshDatabase, WithHousehold;

    private User $user;

    /**
     * Seed permissions and authenticate a base user.
     *
     * @return void This setup method does not return a value.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('user');
        Role::where('slug', 'user')->firstOrFail()->syncPermissions(Permission::all());
    }

    /**
     * Create a private contact for a user holding an email address.
     *
     * @param  User  $owner  The user owning the address book
     * @param  string  $email  The email stored on the contact
     * @return Contact The persisted contact
     */
    private function contactWithEmail(User $owner, string $email): Contact
    {
        return app(SaveLocalContact::class)->execute(ContactData::fromArray([
            'type' => 'person',
            'display_name' => $email,
            'emails' => [['value' => $email, 'type' => 'home', 'preferred' => true]],
        ]), $owner);
    }

    /**
     * Confirm that inviting an unknown email creates a local contact for the inviter.
     *
     * @return void This test method does not return a value.
     */
    public function test_inviting_a_new_email_creates_a_local_contact_for_the_inviter(): void
    {
        Notification::fake();
        $household = $this->createHouseholdForUser($this->user);

        $this->actingAs($this->user)
            ->post(route('households.invite', $household), ['email' => 'nuevo@example.com'])
            ->assertSessionHasNoErrors();

        $invitation = HouseholdInvitation::where('email', 'nuevo@example.com')->firstOrFail();
        $this->assertNotNull($invitation->contact_id);

        $contact = Contact::findOrFail($invitation->contact_id);
        $this->assertSame($this->user->id, $contact->user_id);
        $this->assertSame('nuevo@example.com', $contact->display_name);
        $this->assertDatabaseHas('contact_emails', ['value' => 'nuevo@example.com']);
    }

    /**
     * Confirm that inviting an email already saved as contact reuses it instead of duplicating.
     *
     * @return void This test method does not return a value.
     */
    public function test_inviting_an_existing_contact_email_reuses_the_contact(): void
    {
        Notification::fake();
        $household = $this->createHouseholdForUser($this->user);
        $contact = $this->contactWithEmail($this->user, 'ana@example.com');

        $this->actingAs($this->user)
            ->post(route('households.invite', $household), ['email' => 'ana@example.com'])
            ->assertSessionHasNoErrors();

        $invitation = HouseholdInvitation::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame($contact->id, $invitation->contact_id);
        $this->assertSame(1, Contact::where('user_id', $this->user->id)->count());
    }

    /**
     * Confirm that accepting the invitation stores the contact anchor on the member row.
     *
     * @return void This test method does not return a value.
     */
    public function test_accepting_an_invitation_links_the_member_contact(): void
    {
        Notification::fake();
        $household = $this->createHouseholdForUser($this->user);

        $invited = User::factory()->create();
        $invited->assignRole('user');

        $this->actingAs($this->user)
            ->post(route('households.invite', $household), ['email' => $invited->email])
            ->assertSessionHasNoErrors();

        $invitation = HouseholdInvitation::where('email', $invited->email)->firstOrFail();

        $this->actingAs($invited)
            ->post(route('households.invitations.accept', $invitation))
            ->assertRedirect();

        $member = HouseholdMember::where('household_id', $household->id)
            ->where('user_id', $invited->id)
            ->firstOrFail();

        $this->assertSame($invitation->contact_id, $member->contact_id);
    }

    /**
     * Confirm that the household page exposes the viewer's contact info for a matched member.
     *
     * @return void This test method does not return a value.
     */
    public function test_household_page_exposes_the_viewer_contact_for_a_member(): void
    {
        $household = $this->createHouseholdForUser($this->user);

        $mate = User::factory()->create(['email' => 'mate@example.com']);
        $mate->assignRole('user');
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $mate->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $contact = $this->contactWithEmail($this->user, 'mate@example.com');
        app(SaveLocalContact::class)->execute(ContactData::fromArray([
            'type' => 'person',
            'display_name' => 'Mi Mate',
            'emails' => [['value' => 'mate@example.com', 'type' => 'home', 'preferred' => true]],
            'phones' => [['value' => '600111222', 'type' => 'mobile', 'preferred' => true]],
        ]), $this->user, $contact);

        $label = ContactLabel::create(['user_id' => $this->user->id, 'name' => 'Amigos']);
        $contact->labels()->attach($label->id, ['manual' => true, 'imported' => false]);

        $this->actingAs($this->user)
            ->get(route('households.show', $household))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('households/Show')
                ->has('household.members', 2)
                ->where('household.members', function ($members) use ($mate): bool {
                    $row = collect($members)->firstWhere('user.id', $mate->id);

                    return $row !== null
                        && $row['contact']['display_name'] === 'Mi Mate'
                        && $row['contact']['phone'] === '600111222'
                        && $row['contact']['email'] === 'mate@example.com'
                        && collect($row['contact']['labels'])->pluck('name')->all() === ['Amigos'];
                }));
    }

    /**
     * Confirm that members without a matching contact in the viewer book expose a null contact.
     *
     * @return void This test method does not return a value.
     */
    public function test_members_without_a_matching_contact_expose_a_null_contact(): void
    {
        $household = $this->createHouseholdForUser($this->user);

        $mate = User::factory()->create(['email' => 'desconocido@example.com']);
        $mate->assignRole('user');
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $mate->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('households.show', $household))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('households/Show')
                ->where('household.members', function ($members) use ($mate): bool {
                    $row = collect($members)->firstWhere('user.id', $mate->id);

                    return $row !== null && $row['contact'] === null;
                }));
    }

    /**
     * Confirm that a member row can be linked and unlinked with the viewer's own contact.
     *
     * @return void This test method does not return a value.
     */
    public function test_member_can_be_linked_and_unlinked_with_a_viewer_contact(): void
    {
        $household = $this->createHouseholdForUser($this->user);
        $member = HouseholdMember::where('household_id', $household->id)
            ->where('user_id', $this->user->id)
            ->firstOrFail();
        $contact = $this->contactWithEmail($this->user, 'amigo@example.com');

        $this->actingAs($this->user)
            ->post(route('households.members.link-contact', [$household, $member]), [
                'contact_id' => $contact->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('household_members', ['id' => $member->id, 'contact_id' => $contact->id]);

        $this->actingAs($this->user)
            ->post(route('households.members.link-contact', [$household, $member]), [
                'contact_id' => null,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('household_members', ['id' => $member->id, 'contact_id' => null]);
    }

    /**
     * Confirm that contacts owned by other users cannot be linked to a member row.
     *
     * @return void This test method does not return a value.
     */
    public function test_linking_a_foreign_contact_is_rejected(): void
    {
        $household = $this->createHouseholdForUser($this->user);
        $member = HouseholdMember::where('household_id', $household->id)
            ->where('user_id', $this->user->id)
            ->firstOrFail();
        $foreignContact = Contact::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'person',
            'display_name' => 'Ajeno',
        ]);

        $this->actingAs($this->user)
            ->post(route('households.members.link-contact', [$household, $member]), [
                'contact_id' => $foreignContact->id,
            ])
            ->assertSessionHasErrors('contact_id');

        $this->assertDatabaseHas('household_members', ['id' => $member->id, 'contact_id' => null]);
    }

    /**
     * Confirm that a regular member cannot relink another member's row.
     *
     * @return void This test method does not return a value.
     */
    public function test_a_regular_member_cannot_relink_another_member(): void
    {
        $household = $this->createHouseholdForUser($this->user);
        $adminMember = HouseholdMember::where('household_id', $household->id)
            ->where('user_id', $this->user->id)
            ->firstOrFail();

        $mate = User::factory()->create();
        $mate->assignRole('user');
        $mate->update(['active_household_id' => $household->id]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $mate->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $this->actingAs($mate)
            ->post(route('households.members.link-contact', [$household, $adminMember]), [
                'contact_id' => null,
            ])
            ->assertForbidden();
    }
}
