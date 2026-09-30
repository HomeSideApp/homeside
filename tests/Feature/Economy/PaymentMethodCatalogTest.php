<?php

namespace Tests\Feature\Economy;

use App\Enums\PaymentMethodKind;
use App\Models\EconomicAccount;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodCatalogTest extends TestCase
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

    public function test_seeded_global_methods_are_listed_with_translated_names(): void
    {
        $this->seed(PaymentMethodSeeder::class);
        $user = $this->userWithRole();

        $response = $this->actingAs($user)->get(route('economy.me.payment-methods.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('economy/PaymentMethods/Index')
                ->has('paymentMethods', 10)
                ->where('paymentMethods.0.is_global', true)
                ->whereNot('paymentMethods.0.name', 'app.economy.payment_methods.cash')
        );
    }

    public function test_user_can_create_and_delete_an_own_payment_method(): void
    {
        $user = $this->userWithRole();

        $this->actingAs($user)->post(route('economy.me.payment-methods.store'), [
            'name' => 'Bizum familiar',
            'kind' => PaymentMethodKind::DigitalWallet->value,
        ])->assertRedirect();

        $method = PaymentMethod::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Bizum familiar', $method->name);
        $this->assertSame(PaymentMethodKind::DigitalWallet, $method->kind);

        $this->actingAs($user)
            ->delete(route('economy.me.payment-methods.destroy', $method))
            ->assertRedirect();

        $this->assertDatabaseMissing('payment_methods', ['id' => $method->id]);
    }

    public function test_user_cannot_update_or_delete_a_global_payment_method(): void
    {
        $user = $this->userWithRole();
        $global = PaymentMethod::factory()->global()->create();

        $this->actingAs($user)
            ->put(route('economy.me.payment-methods.update', $global), [
                'name' => 'Secuestrado',
                'kind' => PaymentMethodKind::Cash->value,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('economy.me.payment-methods.destroy', $global))
            ->assertForbidden();

        $this->assertDatabaseHas('payment_methods', [
            'id' => $global->id,
            'name' => $global->name,
        ]);
    }

    public function test_user_cannot_update_or_delete_another_users_payment_method(): void
    {
        $owner = $this->userWithRole();
        $intruder = $this->userWithRole();
        $method = PaymentMethod::factory()->forUser($owner)->create();

        $this->actingAs($intruder)
            ->put(route('economy.me.payment-methods.update', $method), [
                'name' => 'Hackeado',
                'kind' => PaymentMethodKind::Other->value,
            ])
            ->assertForbidden();

        $this->actingAs($intruder)
            ->delete(route('economy.me.payment-methods.destroy', $method))
            ->assertForbidden();
    }

    public function test_payment_method_in_use_cannot_be_deleted(): void
    {
        $user = $this->userWithRole();
        $method = PaymentMethod::factory()->forUser($user)->create();
        EconomicAccount::factory()
            ->forUser($user)
            ->withPaymentMethods([$method])
            ->create();

        $this->actingAs($user)
            ->delete(route('economy.me.payment-methods.destroy', $method))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseHas('payment_methods', ['id' => $method->id]);
    }

    public function test_user_cannot_assign_another_users_payment_method_to_an_account(): void
    {
        $owner = $this->userWithRole();
        $intruder = $this->userWithRole();
        $method = PaymentMethod::factory()->forUser($owner)->create();

        $this->actingAs($intruder)
            ->post(route('economy.me.accounts.store'), [
                'name' => 'Cuenta intrusa',
                'payment_method_ids' => [$method->id],
                'currency' => 'EUR',
            ])
            ->assertSessionHasErrors('payment_method_ids.0');
    }
}
