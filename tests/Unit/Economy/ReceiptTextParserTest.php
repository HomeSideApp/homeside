<?php

namespace Tests\Unit\Economy;

use App\Data\Economy\ReceiptAnalysisResultData;
use App\Services\Economy\ReceiptTextParser;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Pins the offline receipt parser against real Tesseract output.
 *
 * The fixtures below are the raw OCR text captured from the real tickets used to
 * evaluate the layer, not hand-written ideal text: `yoTaL 77 135,00 §` or
 * `1 CHATERU FILER oF LUE` are what Tesseract 5.3.4 actually returned. The parser
 * therefore has to be tolerant with the label and strict with the number.
 */
class ReceiptTextParserTest extends TestCase
{
    /**
     * Raw text captured from the Avenue ticket (psm 6, eng+spa).
     */
    private const AVENUE_TEXT = <<<'TEXT'
    AVENUE DES CHAMPS
    12 RUE DE PARIS 75008
    TEL 01 23 45 67 89
    1 CHATERU FILER oF LUE
    yoTaL 77 135,00 §
    TOTAL TTC 135,00 €
    date: 22/9/16 12:07:28
    MERCI DE VOTRE VISITE
    TEXT;

    /**
     * Raw text captured from the Gucci ticket: English receipt, clean numbers and
     * only a subtotal line, because the total was not legible.
     */
    private const GUCCI_TEXT = <<<'TEXT'
    GUCCI
    Via Montenapoleone 8, Milano
    VAT 01234567890
    date: 22/9/16 12:07:28
    Item 25.00
    Item 50.00
    Subtotal 75.00.
    TEXT;

    /**
     * Raw text captured from the Harrods ticket: the label survived, the amount did not.
     */
    private const HARRODS_TEXT = <<<'TEXT'
    HARRODS
    87-135 BROMPTON ROAD
    AMOUNT DUE
    THANK YOU FOR SHOPPING WITH US
    TEXT;

    public function test_it_reads_the_total_and_currency_from_the_avenue_ticket(): void
    {
        $result = $this->parser()->parse(self::AVENUE_TEXT);

        $this->assertInstanceOf(ReceiptAnalysisResultData::class, $result);
        $this->assertSame(13500, $result->amount_minor);
        $this->assertSame('EUR', $result->currency);
        $this->assertSame('AVENUE DES CHAMPS', $result->title);
        $this->assertSame('2016-09-22', $result->occurred_at?->toDateString());
    }

    public function test_it_reads_the_subtotal_and_date_from_the_gucci_ticket(): void
    {
        $result = $this->parser()->parse(self::GUCCI_TEXT);

        $this->assertInstanceOf(ReceiptAnalysisResultData::class, $result);
        $this->assertSame(7500, $result->amount_minor);
        $this->assertSame('GUCCI', $result->title);
        $this->assertSame('2016-09-22', $result->occurred_at?->toDateString());
    }

    public function test_gucci_total_is_metered_in_the_default_currency_when_no_marker_is_legible(): void
    {
        $result = $this->parser()->parse(self::GUCCI_TEXT);

        $this->assertSame('ARS', $result?->currency, 'The parser defaults to ARS when no currency marker survives the OCR.');
    }

    public function test_an_illegible_total_without_any_amount_returns_null(): void
    {
        $this->assertNull(
            $this->parser()->parse(self::HARRODS_TEXT),
            'Harrods has the AMOUNT DUE label but no legible amount, so the import must fall back to AI.'
        );
    }

    public function test_a_receipt_without_any_total_label_returns_null(): void
    {
        $this->assertNull($this->parser()->parse('1 CHATERU FILER oF LUE yoTaL 77 §'));
    }

    public function test_garbage_text_returns_null(): void
    {
        $this->assertNull($this->parser()->parse("§§§ ~~~ |||\n1lI0O0 . , :"));
    }

    public function test_it_accepts_the_es_ar_thousands_separator(): void
    {
        $result = $this->parser()->parse("SUPERMERCADO NORTE\nTOTAL A PAGAR 1.234,56");

        $this->assertSame(123456, $result?->amount_minor);
    }

    public function test_it_accepts_the_english_thousands_separator(): void
    {
        $result = $this->parser()->parse("CORNER STORE\nTOTAL USD 1,234.56");

        $this->assertSame(123456, $result?->amount_minor);
        $this->assertSame('USD', $result?->currency);
    }

    public function test_it_accepts_a_flat_decimal_comma_amount(): void
    {
        $result = $this->parser()->parse("PANADERIA LA ESQUINA\nIMPORTE 1234,56");

        $this->assertSame(123456, $result?->amount_minor);
    }

    public function test_it_accepts_an_amount_written_without_decimals(): void
    {
        $result = $this->parser()->parse("KIOSCO\nTOTAL 250");

        $this->assertSame(25000, $result?->amount_minor);
    }

    public function test_a_subtotal_alone_is_still_accepted(): void
    {
        $result = $this->parser()->parse("FARMACIA\nSUBTOTAL 42,10");

        $this->assertSame(4210, $result?->amount_minor);
    }

    public function test_the_total_line_wins_over_the_subtotal_line(): void
    {
        $result = $this->parser()->parse("RESTAURANTE\nSubtotal 90,00\nTOTAL 110,00");

        $this->assertSame(11000, $result?->amount_minor, 'The payable total is preferred over the subtotal.');
    }

    public function test_it_reads_an_amount_printed_on_the_line_after_the_label(): void
    {
        $result = $this->parser()->parse("LIBRERIA\nTOTAL A PAGAR\n135,00");

        $this->assertSame(13500, $result?->amount_minor);
    }

    public function test_an_impossible_year_drops_the_date_instead_of_losing_the_amount(): void
    {
        $result = $this->parser()->parse("BODEGA\n12/03/1800\nTOTAL 10,00");

        $this->assertSame(1000, $result?->amount_minor);
        $this->assertNull($result?->occurred_at, 'A year out of the plausible range must not be invented.');
    }

    public function test_a_receipt_without_a_date_keeps_occurred_at_null(): void
    {
        $result = $this->parser()->parse("BODEGA\nTOTAL 10,00");

        $this->assertSame(1000, $result?->amount_minor);
        $this->assertNull($result?->occurred_at);
    }

    public function test_it_reads_an_iso_date(): void
    {
        $result = $this->parser()->parse("TIENDA\n2019-11-05\nTOTAL 10,00");

        $this->assertSame('2019-11-05', $result?->occurred_at?->toDateString());
    }

    public function test_it_reads_a_dash_separated_date_with_a_two_digit_year(): void
    {
        $result = $this->parser()->parse("TIENDA\n15-04-19\nTOTAL 10,00");

        $this->assertSame('2019-04-15', $result?->occurred_at?->toDateString());
    }

    public function test_it_reads_a_dot_separated_date(): void
    {
        $result = $this->parser()->parse("TIENDA\n05.11.2019\nTOTAL 10,00");

        $this->assertSame('2019-11-05', $result?->occurred_at?->toDateString());
    }

    public function test_it_reads_a_spanish_month_name(): void
    {
        $result = $this->parser()->parse("TIENDA\n12 nov 2019\nTOTAL 10,00");

        $this->assertSame('2019-11-12', $result?->occurred_at?->toDateString());
    }

    public function test_it_reads_a_french_month_name(): void
    {
        $result = $this->parser()->parse("BOULANGERIE\n22 novembre 2016\nTOTAL 10,00");

        $this->assertSame('2016-11-22', $result?->occurred_at?->toDateString());
    }

    public function test_it_reads_an_english_month_name(): void
    {
        $result = $this->parser()->parse("COFFEE SHOP\nNov 22, 2016\nTOTAL 10,00");

        $this->assertSame('2016-11-22', $result?->occurred_at?->toDateString());
    }

    public function test_items_and_taxes_stay_empty_in_this_version(): void
    {
        $result = $this->parser()->parse(self::AVENUE_TEXT);

        $this->assertInstanceOf(ReceiptAnalysisResultData::class, $result);
        $this->assertSame([], $result->items, 'Line items are deliberately out of scope for the offline layer.');
        $this->assertSame([], $result->taxes);
        $this->assertNull($result->place);
    }

    public function test_the_parsed_result_serializes_like_the_ai_payload(): void
    {
        $payload = $this->parser()->parse(self::AVENUE_TEXT)?->toArray();

        $this->assertSame('135.00', $payload['amount'] ?? null);
        $this->assertSame('EUR', $payload['currency'] ?? null);
        $this->assertSame(Carbon::parse('2016-09-22')->toISOString(), $payload['occurred_at'] ?? null);
    }

    /**
     * Resolve the parser under test.
     */
    private function parser(): ReceiptTextParser
    {
        return new ReceiptTextParser;
    }
}
