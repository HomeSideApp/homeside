<?php

namespace Database\Seeders;

use App\Enums\PaymentMethodKind;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

/**
 * Class PaymentMethodSeeder
 *
 * Seeds the global payment method catalogue available to every user. These entries have a
 * null `user_id` and are managed exclusively from the administration panel.
 */
class PaymentMethodSeeder extends Seeder
{
    /**
     * Seed the global payment method catalogue.
     *
     * The name is stored as a translation key so each locale can render it through the
     * application translator instead of a hardcoded language.
     *
     * @return void This seeder method does not return a value.
     */
    public function run(): void
    {
        $methods = [
            ['slug' => 'cash', 'name' => 'app.economy.payment_methods.cash', 'kind' => PaymentMethodKind::Cash, 'icon' => 'Banknote', 'sort_order' => 1],
            ['slug' => 'debit-card', 'name' => 'app.economy.payment_methods.debit_card', 'kind' => PaymentMethodKind::Card, 'icon' => 'CreditCard', 'sort_order' => 2],
            ['slug' => 'credit-card', 'name' => 'app.economy.payment_methods.credit_card', 'kind' => PaymentMethodKind::Card, 'icon' => 'CreditCard', 'sort_order' => 3],
            ['slug' => 'bank-transfer', 'name' => 'app.economy.payment_methods.bank_transfer', 'kind' => PaymentMethodKind::BankTransfer, 'icon' => 'Landmark', 'sort_order' => 4],
            ['slug' => 'direct-debit', 'name' => 'app.economy.payment_methods.direct_debit', 'kind' => PaymentMethodKind::BankTransfer, 'icon' => 'Landmark', 'sort_order' => 5],
            ['slug' => 'paypal', 'name' => 'app.economy.payment_methods.paypal', 'kind' => PaymentMethodKind::DigitalWallet, 'icon' => 'Wallet', 'sort_order' => 6],
            ['slug' => 'bizum', 'name' => 'app.economy.payment_methods.bizum', 'kind' => PaymentMethodKind::DigitalWallet, 'icon' => 'Smartphone', 'sort_order' => 7],
            ['slug' => 'crypto-wallet', 'name' => 'app.economy.payment_methods.crypto_wallet', 'kind' => PaymentMethodKind::Crypto, 'icon' => 'Bitcoin', 'sort_order' => 8],
            ['slug' => 'cheque', 'name' => 'app.economy.payment_methods.cheque', 'kind' => PaymentMethodKind::Cheque, 'icon' => 'ScrollText', 'sort_order' => 9],
            ['slug' => 'other', 'name' => 'app.economy.payment_methods.other', 'kind' => PaymentMethodKind::Other, 'icon' => 'CircleEllipsis', 'sort_order' => 10],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['slug' => $method['slug']],
                [
                    'user_id' => null,
                    'name' => $method['name'],
                    'kind' => $method['kind'],
                    'icon' => $method['icon'],
                    'is_active' => true,
                    'sort_order' => $method['sort_order'],
                ],
            );
        }
    }
}
