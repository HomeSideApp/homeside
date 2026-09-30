<?php

namespace Tests\Feature\Economy;

use App\Models\Contact;
use App\Models\EconomicTransaction;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Verifies the optional contact linked to a personal economic transaction.
 */
final class TransactionContactLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /**
     * Prepare an authenticated user with personal economy permissions.
     *
     * @return void This setup method does not return a value.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('user');
    }

    /**
     * Build a minimal valid payload for a private transaction.
     *
     * @param  array<string, mixed>  $extra  Additional fields merged into the payload.
     * @return array<string, mixed> The validated payload values.
     */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'type' => 'expense',
            'scope' => 'personal',
            'title' => 'Compra',
            'amount' => '10.00',
            'currency' => 'EUR',
        ], $extra);
    }

    /**
     * Confirm that a visible contact can be linked on creation and appears in the detail page.
     *
     * @return void This test method does not return a value.
     */
    public function test_personal_transaction_can_link_a_visible_contact(): void
    {
        $contact = Contact::create([
            'user_id' => $this->user->id,
            'type' => 'person',
            'display_name' => 'Ana',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('economy.me.store'), $this->payload(['contact_id' => $contact->id]));

        $response->assertSessionHasNoErrors();

        $transaction = EconomicTransaction::query()->latest('created_at')->firstOrFail();

        $this->assertDatabaseHas('economic_transaction_contacts', [
            'economic_transaction_id' => $transaction->id,
            'contact_id' => $contact->id,
            'role' => 'related',
        ]);

        $this->actingAs($this->user)
            ->get(route('economy.me.show', $transaction))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('economy/Show')
                ->where('transaction.contact.id', $contact->id)
                ->where('transaction.contact.display_name', 'Ana'));
    }

    /**
     * Confirm that contacts owned by another user are rejected.
     *
     * @return void This test method does not return a value.
     */
    public function test_personal_transaction_rejects_a_foreign_contact(): void
    {
        $foreignContact = Contact::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'person',
            'display_name' => 'Ajeno',
        ]);

        $this->actingAs($this->user)
            ->post(route('economy.me.store'), $this->payload(['contact_id' => $foreignContact->id]))
            ->assertSessionHasErrors('contact_id');

        $this->assertSame(0, EconomicTransaction::query()->count());
    }

    /**
     * Confirm that another member of the household never sees the linked contact.
     *
     * @return void This test method does not return a value.
     */
    public function test_the_linked_contact_is_only_visible_to_its_creator(): void
    {
        $household = Household::factory()->create();
        $mate = User::factory()->create();
        $mate->assignRole('user');
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $mate->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);
        $this->user->update(['active_household_id' => $household->id]);

        $contact = Contact::create([
            'user_id' => $this->user->id,
            'type' => 'person',
            'display_name' => 'Ana',
        ]);

        $transaction = EconomicTransaction::factory()
            ->forHousehold($household)
            ->createdBy($this->user)
            ->shared()
            ->create(['amount_minor' => 1000]);
        $transaction->contacts()->attach($contact->id, ['role' => 'related']);

        foreach ([$this->user, $mate] as $member) {
            $transaction->participants()->create([
                'household_member_id' => $member->householdMemberships()->firstOrFail()->id,
                'split_type' => 'equal',
                'amount_minor' => 500,
            ]);
        }

        $this->actingAs($mate)
            ->get(route('households.economy.show', [$household, $transaction]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('transaction.contact', null));

        $this->actingAs($this->user)
            ->get(route('households.economy.show', [$household, $transaction]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('transaction.contact.id', $contact->id));
    }

    /**
     * Confirm that clearing the field on update detaches the linked contact.
     *
     * @return void This test method does not return a value.
     */
    public function test_updating_without_contact_unlinks_the_previous_one(): void
    {
        $contact = Contact::create([
            'user_id' => $this->user->id,
            'type' => 'person',
            'display_name' => 'Ana',
        ]);

        $transaction = EconomicTransaction::factory()->private()->createdBy($this->user)->create();
        $transaction->contacts()->attach($contact->id, ['role' => 'related']);

        $this->actingAs($this->user)
            ->put(route('economy.me.update', $transaction), $this->payload([
                'title' => 'Compra actualizada',
                'contact_id' => null,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('economic_transaction_contacts', [
            'economic_transaction_id' => $transaction->id,
            'contact_id' => $contact->id,
        ]);
    }

    /**
     * Confirm that shared participants expose the viewer's contact resolved by the member's email.
     *
     * @return void This test method does not return a value.
     */
    public function test_participants_expose_the_contact_resolved_from_the_viewers_book(): void
    {
        $household = Household::factory()->create();
        $mate = User::factory()->create(['email' => 'mate@example.com']);
        $mate->assignRole('user');
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $this->user->id,
            'role' => 'admin',
            'joined_at' => now(),
        ]);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $mate->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);
        $this->user->update(['active_household_id' => $household->id]);

        $contact = Contact::create([
            'user_id' => $this->user->id,
            'type' => 'person',
            'display_name' => 'Compañero',
        ]);
        $record = $contact->records()->create(['formatted_name' => 'Compañero']);
        DB::table('contact_emails')->insert([
            'contact_record_id' => $record->id,
            'value' => 'mate@example.com',
            'type' => 'home',
            'preferred' => true,
        ]);

        $transaction = EconomicTransaction::factory()
            ->forHousehold($household)
            ->createdBy($this->user)
            ->shared()
            ->create(['amount_minor' => 1000]);
        $transaction->participants()->create([
            'household_member_id' => $mate->householdMemberships()->firstOrFail()->id,
            'split_type' => 'percentage',
            'percentage' => 50,
            'amount_minor' => 500,
        ]);

        $this->actingAs($this->user)
            ->get(route('households.economy.show', [$household, $transaction]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('economy/Show')
                ->where('transaction.participants.0.household_member.contact.display_name', 'Compañero')
                ->where('transaction.participants.0.household_member.contact.email', 'mate@example.com'));
    }
}
