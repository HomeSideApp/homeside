<?php

namespace Tests\Feature\Economy;

use App\Enums\HouseholdRole;
use App\Models\EconomicAccount;
use App\Models\EconomicTransaction;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EconomicAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $role = 'user'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_user_can_create_an_account_with_an_opening_balance(): void
    {
        $user = $this->userWithRole();
        $method = PaymentMethod::factory()->global()->create();

        $response = $this->actingAs($user)->post(route('economy.me.accounts.store'), [
            'name' => 'Cuenta nómina',
            'payment_method_ids' => [$method->id],
            'currency' => 'EUR',
            'decimal_places' => 2,
            'initial_balance' => '1500.50',
        ]);

        $response->assertRedirect();

        $account = EconomicAccount::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('Cuenta nómina', $account->name);
        $this->assertSame(150050, $account->initial_balance_minor);
        $this->assertSame('EUR', $account->currency);
    }

    public function test_account_can_hold_several_payment_methods(): void
    {
        $user = $this->userWithRole();
        $card = PaymentMethod::factory()->global()->create(['name' => 'app.economy.payment_methods.debit_card']);
        $bizum = PaymentMethod::factory()->global()->create(['name' => 'app.economy.payment_methods.bizum']);
        $paypal = PaymentMethod::factory()->global()->create(['name' => 'app.economy.payment_methods.paypal']);

        $this->actingAs($user)->post(route('economy.me.accounts.store'), [
            'name' => 'Cuenta corriente',
            'payment_method_ids' => [$card->id, $bizum->id, $paypal->id],
            'currency' => 'EUR',
        ])->assertRedirect();

        $account = EconomicAccount::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertCount(3, $account->paymentMethods);
        $this->assertTrue($account->paymentMethods->contains('id', $bizum->id));

        $response = $this->actingAs($user)->get(route('economy.me.accounts.index'));

        $response->assertInertia(
            fn ($page) => $page->has('accounts.0.payment_methods', 3),
        );
    }

    public function test_updating_an_account_replaces_its_payment_methods(): void
    {
        $user = $this->userWithRole();
        $card = PaymentMethod::factory()->global()->create();
        $paypal = PaymentMethod::factory()->global()->create();

        $account = EconomicAccount::factory()
            ->forUser($user)
            ->withPaymentMethods([$card])
            ->create();

        $this->actingAs($user)
            ->put(route('economy.me.accounts.update', $account), [
                'name' => $account->name,
                'payment_method_ids' => [$paypal->id],
                'currency' => 'EUR',
            ])
            ->assertRedirect();

        $account->refresh()->load('paymentMethods');

        $this->assertCount(1, $account->paymentMethods);
        $this->assertSame($paypal->id, $account->paymentMethods->first()->id);
    }

    public function test_account_requires_at_least_one_payment_method(): void
    {
        $user = $this->userWithRole();

        $this->actingAs($user)
            ->post(route('economy.me.accounts.store'), [
                'name' => 'Sin métodos',
                'payment_method_ids' => [],
                'currency' => 'EUR',
            ])
            ->assertSessionHasErrors('payment_method_ids');
    }

    public function test_account_balance_is_initial_plus_income_minus_expenses(): void
    {
        $user = $this->userWithRole();
        $account = EconomicAccount::factory()->forUser($user)->create([
            'initial_balance_minor' => 10000,
        ]);

        EconomicTransaction::factory()->private()->createdBy($user)->create([
            'account_id' => $account->id,
            'amount_minor' => 2500,
        ]);

        EconomicTransaction::factory()->private()->createdBy($user)->income()->create([
            'account_id' => $account->id,
            'amount_minor' => 5000,
        ]);

        $response = $this->actingAs($user)->get(route('economy.me.accounts.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('economy/Accounts/Index')
                ->where('accounts.0.balance_minor', 12500)
        );
    }

    public function test_archived_accounts_are_hidden_by_default(): void
    {
        $user = $this->userWithRole();
        EconomicAccount::factory()->forUser($user)->archived()->create();

        $response = $this->actingAs($user)->get(route('economy.me.accounts.index'));

        $response->assertInertia(
            fn ($page) => $page->where('accounts', []),
        );
    }

    public function test_user_cannot_view_update_or_delete_another_users_account(): void
    {
        $owner = $this->userWithRole();
        $intruder = $this->userWithRole();
        $account = EconomicAccount::factory()->forUser($owner)->create();

        $this->actingAs($intruder)
            ->get(route('economy.me.accounts.show', $account))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->put(route('economy.me.accounts.update', $account), [
                'name' => 'Hackeada',
                'payment_method_ids' => $account->paymentMethods->pluck('id')->all(),
                'currency' => 'EUR',
            ])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('economy.me.accounts.destroy', $account))
            ->assertForbidden();

        $this->assertDatabaseHas('economic_accounts', [
            'id' => $account->id,
            'name' => $account->name,
        ]);
    }

    public function test_household_member_cannot_see_the_account_of_a_shared_transaction(): void
    {
        $owner = $this->userWithRole();
        $member = User::factory()->create();
        $member->assignRole('user');

        $household = Household::factory()->create(['created_by' => $owner->id]);
        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'role' => HouseholdRole::Admin,
        ]);
        HouseholdMember::factory()->create([
            'household_id' => $household->id,
            'user_id' => $member->id,
            'role' => HouseholdRole::Member,
        ]);

        $account = EconomicAccount::factory()->forUser($owner)->create();

        $transaction = EconomicTransaction::factory()
            ->forHousehold($household)
            ->createdBy($owner)
            ->shared()
            ->create(['account_id' => $account->id]);

        $ownerResponse = $this->actingAs($owner)
            ->get(route('households.economy.show', [$household, $transaction]));
        $ownerResponse->assertInertia(
            fn ($page) => $page->where('transaction.account.id', $account->id),
        );

        $memberResponse = $this->actingAs($member)
            ->get(route('households.economy.show', [$household, $transaction]));
        $memberResponse->assertInertia(
            fn ($page) => $page->where('transaction.account', null),
        );
    }

    public function test_transaction_cannot_reference_an_account_owned_by_another_user(): void
    {
        $owner = $this->userWithRole();
        $other = $this->userWithRole();
        $account = EconomicAccount::factory()->forUser($other)->create();

        $response = $this->actingAs($owner)->post(route('economy.me.store'), [
            'type' => 'expense',
            'scope' => 'personal',
            'title' => 'Gasto ajeno',
            'amount' => '10.00',
            'currency' => 'EUR',
            'account_id' => $account->id,
        ]);

        $response->assertSessionHasErrors('account_id');
    }

    public function test_deleting_an_account_detaches_its_transactions(): void
    {
        $user = $this->userWithRole();
        $account = EconomicAccount::factory()->forUser($user)->create();
        $transaction = EconomicTransaction::factory()->private()->createdBy($user)->create([
            'account_id' => $account->id,
        ]);

        $this->actingAs($user)
            ->delete(route('economy.me.accounts.destroy', $account))
            ->assertRedirect();

        $this->assertDatabaseMissing('economic_accounts', ['id' => $account->id]);
        $this->assertDatabaseHas('economic_transactions', [
            'id' => $transaction->id,
            'account_id' => null,
        ]);
    }
}
