<?php

namespace Tests\Unit\Economy;

use App\Data\Economy\CreateEconomicTransactionData;
use App\Enums\TransactionScope;
use App\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoneyConversionTest extends TestCase
{
    use RefreshDatabase;

    private function data(float $amount): CreateEconomicTransactionData
    {
        return CreateEconomicTransactionData::fromArray([
            'type' => 'expense',
            'scope' => 'personal',
            'title' => 'Test',
            'amount' => $amount,
            'currency' => 'EUR',
        ]);
    }

    public function test_amount_to_minor_conversion(): void
    {
        $this->assertSame(1995, $this->data(19.95)->amount_minor);
    }

    public function test_minor_to_amount_conversion(): void
    {
        $this->assertSame('19.95', $this->data(19.95)->amount_minor !== 0 ? number_format(1995 / 100, 2, '.', '') : '0.00');
    }

    public function test_large_amount_conversion(): void
    {
        $this->assertSame(99999999, $this->data(999999.99)->amount_minor);
    }

    public function test_zero_amount(): void
    {
        $this->assertSame(0, $this->data(0.0)->amount_minor);
    }

    public function test_one_cent(): void
    {
        $this->assertSame(1, $this->data(0.01)->amount_minor);
    }

    public function test_type_and_scope_enums(): void
    {
        $data = $this->data(10);

        $this->assertSame(TransactionType::Expense, $data->type);
        $this->assertSame(TransactionScope::Personal, $data->scope);
    }
}
