<?php

namespace Tests\Unit\Economy;

use App\Data\Economy\EconomicTransactionTaxData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_tax_rate_stored_as_percentage_times_100(): void
    {
        $tax = EconomicTransactionTaxData::fromArray([
            'name' => 'IVA',
            'rate' => 21,
            'taxable_base' => 100,
            'amount' => 21,
        ]);

        $this->assertSame(2100, $tax->rate);
        $this->assertSame(10000, $tax->taxable_base_minor);
        $this->assertSame(2100, $tax->tax_amount_minor);
    }

    public function test_multi_country_rates_iva_igv_vat(): void
    {
        $iva = EconomicTransactionTaxData::fromArray(['name' => 'IVA', 'rate' => 21, 'taxable_base' => 10, 'amount' => 2.1]);
        $igv = EconomicTransactionTaxData::fromArray(['name' => 'IGV', 'rate' => 18, 'taxable_base' => 10, 'amount' => 1.8]);
        $vat = EconomicTransactionTaxData::fromArray(['name' => 'VAT', 'rate' => 25, 'taxable_base' => 10, 'amount' => 2.5]);

        $this->assertSame(2100, $iva->rate);
        $this->assertSame(1800, $igv->rate);
        $this->assertSame(2500, $vat->rate);
    }

    public function test_tax_name_accepts_any_string(): void
    {
        $tax = EconomicTransactionTaxData::fromArray([
            'name' => 'Sales tax (CA 8.25%)',
            'rate' => 8.25,
            'taxable_base' => 100,
            'amount' => 8.25,
        ]);

        $this->assertSame('Sales tax (CA 8.25%)', $tax->name);
        $this->assertSame(825, $tax->rate);
    }

    public function test_decimal_rates_round_correctly(): void
    {
        $tax = EconomicTransactionTaxData::fromArray([
            'name' => 'GST',
            'rate' => 5.125,
            'taxable_base' => 33.33,
            'amount' => 1.71,
        ]);

        $this->assertSame(512, $tax->rate); // 5.125*100 = 512.5 → round 512
        $this->assertSame(3333, $tax->taxable_base_minor);
    }
}
