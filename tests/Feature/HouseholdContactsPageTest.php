<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Verifies the household address book page rendered as a standard table.
 */
final class HouseholdContactsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    /**
     * Prepare an admin member with full contacts permissions and an active household.
     *
     * @return void This setup method does not return a value.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Role::where('slug', 'user')->firstOrFail()->syncPermissions(Permission::all());

        $this->user = User::factory()->create();
        $this->user->assignRole('user');
        $this->household = Household::factory()->create();
        HouseholdMember::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);
    }

    /**
     * Confirm the page only lists the contacts shared with the household.
     *
     * @return void This test method does not return a value.
     */
    public function test_household_contacts_page_lists_only_household_contacts(): void
    {
        $shared = Contact::create([
            'household_id' => $this->household->id,
            'type' => 'person',
            'display_name' => 'Compartido',
        ]);
        Contact::create([
            'user_id' => $this->user->id,
            'type' => 'person',
            'display_name' => 'Personal',
        ]);
        Contact::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'person',
            'display_name' => 'Ajeno',
        ]);

        $this->actingAs($this->user)
            ->get(route('households.contacts.index', $this->household))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('households/contacts/Index')
                ->where('household.id', $this->household->id)
                ->has('contacts', 1)
                ->where('contacts.0.id', $shared->id)
                ->where('contacts.0.display_name', 'Compartido'));
    }

    /**
     * Confirm the search query narrows the household contact list.
     *
     * @return void This test method does not return a value.
     */
    public function test_household_contacts_search_filters_the_table(): void
    {
        Contact::create([
            'household_id' => $this->household->id,
            'type' => 'person',
            'display_name' => 'Ada Lovelace',
        ]);
        Contact::create([
            'household_id' => $this->household->id,
            'type' => 'person',
            'display_name' => 'Grace Hopper',
        ]);

        $this->actingAs($this->user)
            ->get(route('households.contacts.index', $this->household).'?search=Ada')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('households/contacts/Index')
                ->has('contacts', 1)
                ->where('contacts.0.display_name', 'Ada Lovelace')
                ->where('filters.search', 'Ada'));
    }

    /**
     * Confirm a user outside the household is redirected to the selection page.
     *
     * @return void This test method does not return a value.
     */
    public function test_non_member_is_redirected_to_household_selection(): void
    {
        $stranger = User::factory()->create();
        $stranger->assignRole('user');

        $this->actingAs($stranger)
            ->get(route('households.contacts.index', $this->household))
            ->assertRedirect(route('households.select'));
    }

    /**
     * Confirm a member without the contacts view permission cannot open the page.
     *
     * @return void This test method does not return a value.
     */
    public function test_member_without_contacts_permission_is_redirected(): void
    {
        $mate = User::factory()->create();
        $mate->assignRole('user');
        HouseholdMember::create([
            'household_id' => $this->household->id,
            'user_id' => $mate->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);
        $mate->update(['active_household_id' => $this->household->id]);
        Role::where('slug', 'user')->firstOrFail()->syncPermissions(
            Permission::all()->where('route_name', '!=', 'contacts.view')
        );

        $this->actingAs($mate)
            ->get(route('households.contacts.index', $this->household))
            ->assertForbidden();
    }

    /**
     * Confirm creating a contact from the household page returns to that page.
     *
     * @return void This test method does not return a value.
     */
    public function test_storing_a_household_contact_returns_to_the_household_page(): void
    {
        $this->actingAs($this->user)
            ->post(route('contacts.index').'?back=household', [
                'household_id' => $this->household->id,
                'type' => 'person',
                'display_name' => 'Nuevo del hogar',
                'emails' => [['value' => 'nuevo@example.com', 'type' => 'home', 'preferred' => true]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('households.contacts.index', $this->household));

        $this->assertDatabaseHas('contacts', [
            'household_id' => $this->household->id,
            'display_name' => 'Nuevo del hogar',
        ]);
    }
}
