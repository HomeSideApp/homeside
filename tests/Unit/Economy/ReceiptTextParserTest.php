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

    /**
     * Raw text captured from the Zara ticket (psm 6, spa). `TS 108 PUN PRECIO IMPORTE A`
     * is the column header of the item table, and the payable total is the later
     * `Total 1 59,95` — the measured ground truth read from the paper ticket.
     */
    private const ZARA_TEXT = <<<'TEXT'
    hiwtacón mesas?
    ZARA, CC. LAS GLORIAS, L 404
    TLF: +34 934 860 134
    07/02/2013 14 25 79446
    127974308 01 Trans: 345681
    Fecha Expedición/Operación 07/02/2013
    TS 108 PUN PRECIO IMPORTE A
    o m0oa Ta 59,95 V
    10001194 1210320104037 BOTIN TACÓN
    Total 1 59,95
    Efectivo EUR 100,00,
    Cambro Efectivo EUR 40,05
    TEXT;

    /**
     * Excerpt captured from the sports-betting ticket: the payable amount is `IMPORTE
     * APUESTA`, while `TOTAL PUNTOS EQ.` labels a bet market and must not win.
     */
    private const APUESTAS_TEXT = <<<'TEXT'
    ” juegging
    APUESTAS DEPORTIVAS
    APUESTA CONTRAPARTIDA ACUMULADA
    1- copa del Rey 24/10/2017
    Evento: Real Murcia - F.C.
    2 Mod: Total Puntos Eq.
    visitante (74,5)
    cuota: 1,20
    Importe apuesta : 5,00 €
    cuota acumulada : 399)
    Premio Posible E 17505: €
    TEXT;

    /**
     * Raw text captured from the CONNOR ticket (eng, psm 6). The real `TOTAL $19.95` of the
     * photo was not recognised; what survived is the pre-discount `Original Total`, which
     * the OCR mangled into `gina) Total`, plus the `YOU SAVE` line.
     */
    private const CONNOR_TEXT = <<<'TEXT'
    CONNOR
    Pr: 03 4074 3600
    9599118905044 COBEX SKTANY’ JEAN
    gina) Total “$23,185.00
    U SAVI-$29,175.05
    oT of $1.81 a
    $20.00
    19.9
    TEXT;

    /**
     * Excerpt captured from the H&M ticket: the merchant is the bare `HaM` line and the
     * payable total is the German `BON TOTAL` line.
     */
    private const HM_TEXT = <<<'TEXT'
    HaM
    Shop. 609
    Kurfurstendamm 20-24. Tel 030/ 8826299
    10719 Berlin
    04012002 18:40 F7—K7 Bon.nr 115
    KASSLERER4OSS SALES9
    1 ARTIKEL
    SUMME EUR 1.90
    BON TOTAL IN DEN == 3.72
    TEXT;

    /**
     * Excerpt captured from the Sephora ticket: the legal name is the merchant, the
     * `SEPHORA GRAN VIA` store line below it is not.
     */
    private const SEPHORA_TEXT = <<<'TEXT'
    Sephora Cosmeticos España, S.L.
    CIF : B84227461
    SEPHORA GRAN VIA
    €. C. Gran Vía C/ Literatura 1-3
    TEL: 93 259 15 30 FAX : 93 259 00 13
    TOTAL EUR 52.50
    TEXT;

    /**
     * Raw text captured from the bar ticket: a Spanish receipt with no currency symbol
     * anywhere, a `dd.mm.yy` date and an ISO-form date printed below it.
     */
    private const BAR_TEXT = <<<'TEXT'
    ,
    - e /
    s C A Ah madrid
    . Hapos y DIOSOS.
    24 h :
    E ¿ h
    = Mesa 40 l
    -/ »
    =)
    : '
    -4
    z o MA
    4,60
    : Total 26,00
    - 23,64
    + 2,6
    E SALON
    Fecha; 26.01.13, Hora:2143
    2013/01/26 21:41
    TEXT;

    /**
     * Raw text of a Spanish supermarket ticket with a two-unit line, a priced item table and
     * a VAT breakdown. The amounts reconcile on purpose: 4,00 + 6,50 + 3,50 = 14,00, which
     * is the printed total.
     */
    private const SUPERMARKET_TEXT = <<<'TEXT'
    MERCADO CENTRAL
    CIF B12345678
    C/ MAYOR 12
    02/03/2019 18:42
    2x CAFE CON LECHE 4,00
    1 TORTILLA DE PATATA 6,50
    1 ZUMO DE NARANJA 3,50
    IVA 10% 1,27
    TOTAL 14,00
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

    public function test_the_merchant_is_also_exposed_as_the_place(): void
    {
        $result = $this->parser()->parse(self::AVENUE_TEXT);

        $this->assertInstanceOf(ReceiptAnalysisResultData::class, $result);
        $this->assertSame(
            'AVENUE DES CHAMPS',
            $result->place,
            'The merchant is reused as the place so the by-place report is not blind to imported tickets.'
        );
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

    public function test_it_reads_the_real_zara_total_and_not_the_column_header(): void
    {
        $result = $this->parser()->parse(self::ZARA_TEXT);

        $this->assertSame(
            5995,
            $result?->amount_minor,
            '`TS 108 PUN PRECIO IMPORTE A` is the item-table column header, not the total: the payable amount is `Total 1 59,95`.'
        );
        $this->assertStringContainsString('ZARA', (string) $result?->title);
    }

    public function test_a_column_header_line_never_beats_a_later_total(): void
    {
        $result = $this->parser()->parse("LIBRERIA\nCANT PRECIO IMPORTE 108,00\nTotal 42,00");

        $this->assertSame(4200, $result?->amount_minor, 'A line naming two column headers is a header, not a total.');
    }

    public function test_a_base_imponible_never_beats_the_total(): void
    {
        $result = $this->parser()->parse("MERCADO\nBASE IMPONIBLE 10,00\nTOTAL A PAGAR 25,00");

        $this->assertSame(2500, $result?->amount_minor);
    }

    public function test_the_strongest_total_label_wins_over_a_subtotal(): void
    {
        $result = $this->parser()->parse("MERCADO\nSUBTOTAL 10,00\nTOTAL 25,00");

        $this->assertSame(2500, $result?->amount_minor);
    }

    public function test_it_reads_the_french_totale_labels(): void
    {
        $this->assertSame(900, $this->parser()->parse("BOULANGERIE\nTOTALE EUROP 9,00")?->amount_minor);
        $this->assertSame(900, $this->parser()->parse("BOULANGERIE\nTOTALE 9,00")?->amount_minor);
    }

    public function test_an_importe_label_alone_is_still_read(): void
    {
        $result = $this->parser()->parse("TABAC\nIMPORTE 42,00");

        $this->assertSame(4200, $result?->amount_minor, 'Receipts that only print IMPORTE must keep working.');
    }

    public function test_the_last_total_wins_because_totals_are_printed_at_the_foot(): void
    {
        $result = $this->parser()->parse("TIENDA\nTOTAL 108,00\nTOTAL A PAGAR 59,95");

        $this->assertSame(5995, $result?->amount_minor, 'Among equally strong labels the last one is the payable total.');
    }

    public function test_a_later_subtotal_never_beats_the_total_above_it(): void
    {
        $result = $this->parser()->parse("MERCADO\nTOTAL 25,00\nSUBTOTAL 10,00");

        $this->assertSame(2500, $result?->amount_minor, 'Being later must not let a weak label outrank a strong one.');
    }

    public function test_a_column_header_is_never_read_as_the_only_amount(): void
    {
        $this->assertNull(
            $this->parser()->parse("BOUTIQUE\nPRECIO IMPORTE 108,00"),
            'A receipt whose only amount-bearing line is the item-table header has no readable total.'
        );
    }

    public function test_it_reads_the_abbreviated_tot_label(): void
    {
        $result = $this->parser()->parse("KIOSCO\nTOT. 12,00");

        $this->assertSame(1200, $result?->amount_minor);
    }

    public function test_a_bet_market_total_does_not_beat_the_importe_apuesta(): void
    {
        $result = $this->parser()->parse(self::APUESTAS_TEXT);

        $this->assertSame(500, $result?->amount_minor, '`TOTAL PUNTOS EQ.` labels a bet market, not the payable amount.');
    }

    public function test_the_currency_is_read_from_the_amount_line_not_from_a_distant_equivalent(): void
    {
        $result = $this->parser()->parse("CASA DE CAMBIO\nTOTAL \$ 1.000\nEQUIV. EUR 90,00");

        $this->assertSame(100000, $result?->amount_minor);
        $this->assertSame(
            'ARS',
            $result?->currency,
            'The amount is the peso total; the EUR marker belongs to a conversion line, not to it.'
        );
    }

    public function test_an_amount_line_wins_over_a_distant_marker_of_another_currency(): void
    {
        $this->assertSame(
            'EUR',
            $this->parser()->parse("BOULANGERIE\nTOTAL TTC 135,00 €\nEQUIV. US\$ 150,00")?->currency,
            'The euro sign sits on the payable line, so the distant US\$ must not win.'
        );

        $this->assertSame(
            'USD',
            $this->parser()->parse("DUTY FREE\nTOTAL US\$ 120,00\nEQUIVALENTE EUR 92,00")?->currency,
            'US\$ is unambiguous and outranks the distant word EUR.'
        );
    }

    public function test_a_us_dollar_marker_is_never_read_as_a_peso(): void
    {
        $result = $this->parser()->parse("ELECTRONICS STORE\nTOTAL US\$ 120,00");

        $this->assertSame(12000, $result?->amount_minor);
        $this->assertSame('USD', $result?->currency, '`US$` contains `$`: the peso reading must never come first.');
    }

    public function test_a_bare_dollar_sign_is_read_as_a_peso(): void
    {
        $result = $this->parser()->parse("ALMACEN DON PEDRO\nTOTAL \$ 1.234,56");

        $this->assertSame(123456, $result?->amount_minor);
        $this->assertSame('ARS', $result?->currency, 'A bare `$` on an Argentine receipt is a peso.');
    }

    public function test_an_explicit_ars_code_is_read(): void
    {
        $result = $this->parser()->parse("SUPERMERCADO NORTE\nTOTAL 1.234,56 ARS");

        $this->assertSame(123456, $result?->amount_minor);
        $this->assertSame('ARS', $result?->currency);
    }

    public function test_the_full_text_fallback_keeps_the_signal_order(): void
    {
        $result = $this->parser()->parse("CASA DE CAMBIO\nTOTAL 120,00\nENTREGADO US\$ 120,00\nEQUIVALENTE EUR 92,00");

        $this->assertSame(
            'USD',
            $result?->currency,
            'When the amount line carries no marker, an explicit US\$ still outranks a distant EUR.'
        );
    }

    public function test_the_currency_travels_with_an_amount_printed_below_its_label(): void
    {
        $this->assertSame(
            'EUR',
            $this->parser()->parse("BOULANGERIE\nTOTAL A PAGAR\n135,00 €")?->currency,
            'When the number is printed under the label, both lines belong to the amount block.'
        );
    }

    public function test_an_argentine_us_dollar_marker_is_read_as_usd(): void
    {
        $this->assertSame(
            'USD',
            $this->parser()->parse("ELECTRONICS STORE\nTOTAL U\$S 120,00")?->currency,
            '`U$S` is the local spelling of the US dollar and must not fall through to the peso reading.'
        );
    }

    public function test_the_signal_priority_holds_across_the_whole_text(): void
    {
        $result = $this->parser()->parse("CAMBIO\nTOTAL 120,00\nCAJA \$ 500,00\nENTREGADO US\$ 120,00");

        $this->assertSame(
            'USD',
            $result?->currency,
            'In the whole-text fallback an explicit US\$ outranks a bare `$` printed above it.'
        );
    }

    public function test_a_receipt_without_any_currency_marker_falls_back_to_ars(): void
    {
        $this->assertSame(
            'ARS',
            $this->parser()->parse("PANADERIA LA ESQUINA\nTOTAL 250")?->currency,
            'No marker means ARS by product decision: the app is Argentine and the review screen lets the person correct it.'
        );
    }

    public function test_an_impossible_year_drops_the_date_instead_of_losing_the_amount(): void
    {
        $result = $this->parser()->parse("BODEGA\n12/03/1800\nTOTAL 10,00");

        $this->assertSame(1000, $result?->amount_minor);
        $this->assertNull($result?->occurred_at, 'A year out of the plausible range must not be invented.');
    }

    public function test_the_measured_dates_are_pinned(): void
    {
        $this->assertSame(
            '2017-10-24',
            $this->parser()->parse(self::APUESTAS_TEXT)?->occurred_at?->toDateString(),
            'The betting ticket prints `24/10/2017`.'
        );

        $this->assertSame(
            '2013-02-07',
            $this->parser()->parse(self::ZARA_TEXT)?->occurred_at?->toDateString(),
            'The Zara ticket prints `07/02/2013` in dd/mm/yyyy order.'
        );
    }

    public function test_a_dot_separated_two_digit_year_is_read_from_the_bar_ticket(): void
    {
        $result = $this->parser()->parse(self::BAR_TEXT);

        $this->assertSame(
            '2013-01-26',
            $result?->occurred_at?->toDateString(),
            '`Fecha; 26.01.13` is dd.mm.yy and means 26 January 2013, not 1926 or 2026.'
        );
        $this->assertSame(2600, $result?->amount_minor);
    }

    public function test_the_original_total_never_beats_the_payable_total(): void
    {
        $result = $this->parser()->parse("TIENDA\nOriginal Total \$29,195.00\nTOTAL \$19.95");

        $this->assertSame(
            1995,
            $result?->amount_minor,
            'The original total is the price before the discount, not what the customer paid.'
        );
    }

    public function test_a_receipt_that_only_prints_the_original_total_has_no_payable_amount(): void
    {
        $this->assertNull(
            $this->parser()->parse("CONNOR\nOriginal Total \$29,195.00\nYOU SAVE \$29,175.05"),
            'When only the pre-discount price survived the OCR there is no payable amount to report.'
        );
    }

    public function test_every_pre_discount_label_is_discarded(): void
    {
        foreach (['TOTAL ORIGINAL 29,00', 'ORIGINAL 29,00', 'PRECIO ORIGINAL 29,00', 'ANTES DE DESCUENTO 29,00', 'PVP 29,00', 'P.V.P. 29,00'] as $line) {
            $this->assertNull(
                $this->parser()->parse("TIENDA\n".$line),
                "`{$line}` is the pre-discount price, so it is never the payable amount."
            );
        }
    }

    public function test_the_measured_connor_original_total_is_discarded(): void
    {
        $this->assertNull(
            $this->parser()->parse(self::CONNOR_TEXT),
            'The real `TOTAL $19.95` was not recognised, and the mangled `gina) Total` line is the `Original Total` of the photo.'
        );
    }

    public function test_a_payable_total_that_merely_resembles_a_discarded_label_is_kept(): void
    {
        $this->assertSame(
            2500,
            $this->parser()->parse("TIENDA\nTOTAL FINAL 25,00")?->amount_minor,
            '`FINAL` shares letters with the mangled `original` but is a real payable total.'
        );
        $this->assertSame(
            1200,
            $this->parser()->parse("LIBRERIA\nTOTAL PAGINA 12,00")?->amount_minor,
            '`PAGINA` contains `gina` but not the OCR-damaged tail of `original` in a pre-discount line.'
        );
        $this->assertSame(
            2884,
            $this->parser()->parse("GASOLINERA\nTOTAL EUROS 28,84")?->amount_minor,
            'The euro line of the same ticket must survive the retired-currency discard.'
        );
        $this->assertSame(
            2500,
            $this->parser()->parse("FLORISTERIA\nTOTAL 4 LIRIOS 25,00")?->amount_minor,
            '`LIRIOS` starts like `LIRAS` but is a product, so the line is still a payable total.'
        );
        $this->assertSame(
            2500,
            $this->parser()->parse("TIENDA\nTOTAL DEMO 25,00")?->amount_minor,
            '`DEM` only marks a retired currency on a word boundary.'
        );
    }

    public function test_the_payable_total_is_unaffected_by_the_two_discards(): void
    {
        $this->assertSame(5995, $this->parser()->parse("TIENDA\nTOTAL 59,95")?->amount_minor);
        $this->assertSame(2500, $this->parser()->parse("MERCADO\nSUBTOTAL 10,00\nTOTAL 25,00")?->amount_minor);
    }

    public function test_the_measured_tickets_without_a_readable_date_stay_null(): void
    {
        $this->assertNull(
            $this->parser()->parse(self::HM_TEXT)?->occurred_at,
            'The H&M ticket has no readable date, so none may be invented.'
        );
        $this->assertNull(
            $this->parser()->parse(self::SEPHORA_TEXT)?->occurred_at,
            'The Sephora ticket has no readable date, so none may be invented.'
        );
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

    public function test_a_question_mark_noise_line_does_not_become_the_merchant(): void
    {
        $result = $this->parser()->parse("hiwtacón mesas?\nFERRETERIA SAN MARTIN\nTOTAL 10,00");

        $this->assertSame('FERRETERIA SAN MARTIN', $result?->title);
    }

    public function test_the_measured_merchant_names_are_preserved(): void
    {
        $this->assertSame('APUESTAS DEPORTIVAS', $this->parser()->parse(self::APUESTAS_TEXT)?->title);
        $this->assertSame('HaM', $this->parser()->parse(self::HM_TEXT)?->title);
        $this->assertSame('Sephora Cosmeticos España, S.L', $this->parser()->parse(self::SEPHORA_TEXT)?->title);
    }

    public function test_the_avenue_ticket_reports_no_items_because_its_only_price_line_is_the_total(): void
    {
        $result = $this->parser()->parse(self::AVENUE_TEXT);

        $this->assertInstanceOf(ReceiptAnalysisResultData::class, $result);
        $this->assertSame(
            [],
            $result->items,
            'The only priced line is the mangled total row `yoTaL 77 135,00`, and a row that still carries a stray number is not a product row.'
        );
        $this->assertSame([], $result->taxes, 'The Avenue ticket prints no tax breakdown.');
        $this->assertSame(
            'AVENUE DES CHAMPS',
            $result->place,
            'The merchant is reused as the place, so the by-place report is not blind to imported tickets.'
        );
    }

    public function test_the_parsed_result_serializes_like_the_ai_payload(): void
    {
        $payload = $this->parser()->parse(self::AVENUE_TEXT)?->toArray();

        $this->assertSame('135.00', $payload['amount'] ?? null);
        $this->assertSame('EUR', $payload['currency'] ?? null);
        $this->assertSame(Carbon::parse('2016-09-22')->toISOString(), $payload['occurred_at'] ?? null);
    }

    public function test_a_spanish_cif_without_a_currency_symbol_is_read_as_euros(): void
    {
        $result = $this->parser()->parse("SUPERMERCADO\nCIF B12345678\nTOTAL 28,84");

        $this->assertSame(2884, $result?->amount_minor);
        $this->assertSame(
            'EUR',
            $result?->currency,
            'A Spanish receipt with no symbol at the total prints its CIF instead, and that is enough to infer EUR.'
        );
    }

    public function test_a_french_siret_without_a_currency_symbol_is_read_as_euros(): void
    {
        $result = $this->parser()->parse("BOULANGERIE\nSIRET 123 456 789 00012\nTOTAL 9,50");

        $this->assertSame(950, $result?->amount_minor);
        $this->assertSame('EUR', $result?->currency);
    }

    public function test_an_italian_partita_iva_without_a_currency_symbol_is_read_as_euros(): void
    {
        $result = $this->parser()->parse("TRATTORIA\nP.IVA 01234567890\nTOTAL 42,00");

        $this->assertSame(4200, $result?->amount_minor);
        $this->assertSame('EUR', $result?->currency);
    }

    public function test_a_dutch_btw_identifier_is_read_as_euros(): void
    {
        $this->assertSame(
            'EUR',
            $this->parser()->parse("CAFE\nBTW NL123456789B01\nTOTAL 4,20")?->currency
        );
    }

    public function test_a_bare_vat_identifier_never_claims_the_euro(): void
    {
        $this->assertSame(
            'ARS',
            $this->parser()->parse("SHOP\nVAT 01234567890\nTOTAL 75.00")?->currency,
            'The United Kingdom prints VAT too and is not in the eurozone, so a bare VAT must fall through to the default.'
        );
    }

    public function test_an_argentine_cuit_wins_over_a_euro_sign(): void
    {
        $result = $this->parser()->parse("ALMACEN\nCUIT 20-12345678-9\nFactura B\nTOTAL 250,00 €");

        $this->assertSame(25000, $result?->amount_minor);
        $this->assertSame(
            'ARS',
            $result?->currency,
            'A CUIT and a Factura B cannot come from a European merchant: the euro sign on the paper is a template leftover, not the currency.'
        );
    }

    public function test_every_argentine_fiscal_identifier_wins_over_the_european_ones(): void
    {
        foreach (['CUIT 20-12345678-9', 'CUIL 27-12345678-4', 'AFIP 20-12345678-9', 'Responsable Inscripto', 'Factura C', 'Monotributo'] as $identifier) {
            $result = $this->parser()->parse("TIENDA\n{$identifier}\nCIF B12345678\nTOTAL 250,00");

            $this->assertSame(
                'ARS',
                $result?->currency,
                "`{$identifier}` is an Argentine identifier, so the receipt is in pesos even next to a European CIF."
            );
        }
    }

    public function test_an_argentine_invoice_denominated_in_dollars_keeps_the_dollar(): void
    {
        $this->assertSame(
            'USD',
            $this->parser()->parse("ALMACEN\nCUIT 20-12345678-9\nTOTAL U\$S 250,00")?->currency,
            'Argentine merchants do invoice in dollars, and an explicit U$S is stronger evidence than the fiscal id.'
        );
    }

    public function test_a_bare_iva_rate_never_claims_the_euro(): void
    {
        $result = $this->parser()->parse("BAR\nIVA 21%\nTOTAL 26,00");

        $this->assertSame(2600, $result?->amount_minor);
        $this->assertSame(
            'ARS',
            $result?->currency,
            'Argentina charges IVA at 21% like Spain, so IVA alone must resolve to the default instead of inferring EUR.'
        );
    }

    public function test_a_bare_iva_line_does_not_override_an_explicit_euro_amount(): void
    {
        $this->assertSame(
            'EUR',
            $this->parser()->parse("BAR\nIVA 21%\nTOTAL 26,00 €")?->currency,
            'The euro sign on the amount line is direct evidence and still wins over the ambiguous IVA line.'
        );
    }

    public function test_a_pound_sterling_amount_is_read_as_gbp(): void
    {
        $result = $this->parser()->parse("COFFEE SHOP\nTOTAL £ 12,50");

        $this->assertSame(1250, $result?->amount_minor);
        $this->assertSame('GBP', $result?->currency);
    }

    public function test_the_eurozone_identifiers_of_the_catalogue_are_all_read_as_euros(): void
    {
        foreach (['NIF X1234567L', 'CIF B12345678', 'N.I.F. X1234567L', 'C.I.F. B12345678', 'NIPT 123456789', 'NIPC 123456789', 'SIRET 12345678900012', 'SIREN 123456789', 'RCS Paris 123456789', 'TVA FR12345678901', 'P.IVA 01234567890', 'P IVA 01234567890', 'Partita IVA 01234567890', 'Codice Fiscale RSSMRA80A01H501U', 'USt-IdNr DE123456789', 'USt DE123456789', 'MwSt 19%', 'Mehrwertsteuer 19%', 'UID ATU12345678', 'BTW NL123456789B01', 'PDV 12345678901', 'ALV 12345678', 'DPH 1234567890', 'PVM LT123456789', 'FPA 123456789'] as $identifier) {
            $this->assertSame(
                'EUR',
                $this->parser()->parse("TIENDA\n{$identifier}\nTOTAL 28,84")?->currency,
                "`{$identifier}` is a eurozone fiscal identifier, so the receipt is in euros."
            );
        }
    }

    public function test_the_currency_that_was_already_read_from_a_symbol_is_unchanged(): void
    {
        $this->assertSame('EUR', $this->parser()->parse(self::AVENUE_TEXT)?->currency);
        $this->assertSame('EUR', $this->parser()->parse("GASOLINERA\nTOTAL EUROS 28,84")?->currency);
        $this->assertSame('USD', $this->parser()->parse("ELECTRONICS STORE\nTOTAL US\$ 120,00")?->currency);
        $this->assertSame('ARS', $this->parser()->parse("ALMACEN DON PEDRO\nTOTAL \$ 1.234,56")?->currency);
    }

    public function test_a_lowercase_km_unit_is_not_a_lithuanian_fiscal_identifier(): void
    {
        $this->assertSame(
            'ARS',
            $this->parser()->parse("ESTACION DE SERVICIO\nkm 45 ruta 8\nTOTAL 2.500,00")?->currency,
            '`KM` only identifies Lithuania in upper case; in lower case it is the distance unit printed on Argentine tickets.'
        );
        $this->assertSame(
            'EUR',
            $this->parser()->parse("PARDUTUVE\nKM 123456789\nTOTAL 28,84")?->currency,
            'The upper-case Lithuanian `KM` identifier still claims the euro.'
        );
    }

    public function test_a_shop_named_europa_is_not_a_euro_marker(): void
    {
        $this->assertSame(
            'ARS',
            $this->parser()->parse("PANADERIA EUROPA\nTOTAL 2.500,00")?->currency,
            'The word boundary keeps a merchant name such as EUROPA out of the EURO marker.'
        );
    }

    public function test_the_spanish_word_factura_alone_is_not_an_argentine_identifier(): void
    {
        $this->assertSame(
            'EUR',
            $this->parser()->parse("SUPERMERCADO\nFactura\nCIF B12345678\nTOTAL 28,84")?->currency,
            '`Factura` is printed in Spain too, so only `Factura B`/`Factura C` identify Argentina.'
        );
    }

    public function test_it_reads_the_item_lines_when_they_reconcile_with_the_total(): void
    {
        $result = $this->parser()->parse(self::SUPERMARKET_TEXT);

        $this->assertNotNull($result);
        $this->assertCount(3, $result->items);
        $this->assertSame(
            ['CAFE CON LECHE', 'TORTILLA DE PATATA', 'ZUMO DE NARANJA'],
            array_map(static fn ($item) => $item->name, $result->items),
            'The description is the line without its quantity and without its price.'
        );
        $this->assertSame(
            [400, 650, 350],
            array_map(static fn ($item) => $item->total_minor, $result->items),
            '4,00 + 6,50 + 3,50 is the payable total of 14,00, so every row is reported.'
        );
    }

    public function test_the_x_marker_sets_the_quantity_and_splits_the_unit_amount(): void
    {
        $items = $this->parser()->parse(self::SUPERMARKET_TEXT)?->items;

        $this->assertIsArray($items);
        $this->assertSame(2, $items[0]->quantity, '`2x CAFE CON LECHE 4,00` sells two units.');
        $this->assertSame(400, $items[0]->total_minor);
        $this->assertSame(200, $items[0]->unit_amount_minor, '4,00 over 2 units is 2,00 each.');
        $this->assertSame(1, $items[1]->quantity, 'A line without a printed quantity is one unit.');
    }

    public function test_the_items_serialize_with_the_shape_the_review_screen_reads(): void
    {
        $payload = $this->parser()->parse(self::SUPERMARKET_TEXT)?->toArray();

        $this->assertSame(
            [
                'name' => 'CAFE CON LECHE',
                'quantity' => 2,
                'unit_amount' => '2.00',
                'total' => '4.00',
            ],
            $payload['items'][0] ?? null,
            'Amounts are decimals in the payload, not minor units.'
        );
    }

    public function test_items_that_do_not_reconcile_with_the_total_are_all_dropped(): void
    {
        $text = str_replace('TOTAL 14,00', 'TOTAL 15,00', self::SUPERMARKET_TEXT);

        $result = $this->parser()->parse($text);

        $this->assertSame(1500, $result?->amount_minor, 'The total itself is unaffected by the guard.');
        $this->assertSame(
            [],
            $result?->items,
            'The recognised amounts add up to 14,00 and the ticket says 15,00: a partial list would hide the missing row.'
        );
    }

    public function test_a_rounding_difference_within_the_tolerance_still_reconciles(): void
    {
        $text = str_replace('TOTAL 14,00', 'TOTAL 14,04', self::SUPERMARKET_TEXT);

        $this->assertCount(
            3,
            $this->parser()->parse($text)?->items,
            'A four-cent difference is inside the allowed tolerance and must not discard the items.'
        );
    }

    public function test_a_difference_above_the_tolerance_does_not_reconcile(): void
    {
        $text = str_replace('TOTAL 14,00', 'TOTAL 14,50', self::SUPERMARKET_TEXT);

        $this->assertSame(
            [],
            $this->parser()->parse($text)?->items,
            '0,50 is above both the flat 0,05 allowance and 2 % of 14,50.'
        );
    }

    public function test_a_receipt_without_priced_item_lines_reports_no_items(): void
    {
        $result = $this->parser()->parse("TIENDA\nTOTAL 25,00");

        $this->assertSame(2500, $result?->amount_minor);
        $this->assertSame([], $result?->items);
    }

    public function test_the_gucci_rows_are_table_labels_and_are_not_reported_as_items(): void
    {
        $result = $this->parser()->parse(self::GUCCI_TEXT);

        $this->assertSame(
            [],
            $result?->items,
            '`Item 25.00` names a table cell, not a product, so the row is not a description the review screen can use.'
        );
        $this->assertSame([], $result?->taxes, '`VAT 01234567890` is a fiscal identifier, not a tax component.');
    }

    public function test_a_total_a_tax_or_a_change_line_never_becomes_an_item(): void
    {
        $result = $this->parser()->parse(
            "TIENDA\nTOTAL 14,00\nIVA 21% 2,00\nCAMBIO 3,00\nEFECTIVO 20,00\nDESCUENTO 1,00\nPROPINA 1,00"
        );

        $this->assertSame(1400, $result?->amount_minor);
        $this->assertSame([], $result?->items, 'Every one of those lines labels something other than a product row.');
    }

    public function test_it_reads_a_twenty_one_percent_tax_component(): void
    {
        $result = $this->parser()->parse("TIENDA\nIVA 21% 10,40\nTOTAL 59,95");

        $this->assertCount(1, $result?->taxes);

        $tax = $result->taxes[0];

        $this->assertSame('IVA', $tax->name, 'The label is reported as it was printed.');
        $this->assertSame(2100, $tax->rate, '21% is stored in hundredths, the scale of the AI payload.');
        $this->assertSame(1040, $tax->tax_amount_minor);
        $this->assertSame(4952, $tax->taxable_base_minor, '10,40 at 21% implies a taxable base of 49,52.');
        $this->assertSame(
            [
                'name' => 'IVA',
                'rate' => '21.00',
                'taxable_base' => '49.52',
                'amount' => '10.40',
            ],
            $result->toArray()['taxes'][0],
            'The serialized component uses the rate in percent and decimals, as the review screen expects.'
        );
    }

    public function test_it_reads_a_ten_percent_tax_component(): void
    {
        $tax = $this->parser()->parse("TIENDA\nIVA 10% 0,82\nTOTAL 10,00")?->taxes[0] ?? null;

        $this->assertNotNull($tax);
        $this->assertSame(1000, $tax->rate);
        $this->assertSame(820, $tax->taxable_base_minor, '0,82 at 10% implies a taxable base of 8,20.');
        $this->assertSame(82, $tax->tax_amount_minor);
    }

    public function test_a_rate_printed_with_a_comma_decimal_is_read(): void
    {
        $tax = $this->parser()->parse("TIENDA\nIVA al 21,00% 59,2\nTOTAL 59,95")?->taxes[0] ?? null;

        $this->assertNotNull($tax, '`IVA al 21,00% 59,2` is the measured Spanish shape.');
        $this->assertSame(2100, $tax->rate);
        $this->assertSame(5920, $tax->tax_amount_minor, 'One decimal is completed to two, not read as 5,92.');
        $this->assertSame(28190, $tax->taxable_base_minor);
    }

    public function test_a_tax_line_without_an_amount_is_discarded(): void
    {
        $result = $this->parser()->parse("TIENDA\nIVA 21%\nTOTAL 59,95");

        $this->assertSame(5995, $result?->amount_minor);
        $this->assertSame([], $result?->taxes, 'A rate without an amount is not a tax breakdown.');
    }

    public function test_a_tax_component_larger_than_the_total_is_discarded(): void
    {
        $result = $this->parser()->parse("TIENDA\nIVA 21% 90,00\nTOTAL 59,95");

        $this->assertSame(5995, $result?->amount_minor);
        $this->assertSame([], $result?->taxes, 'No component of a receipt can exceed the amount paid for it.');
    }

    public function test_every_printed_tax_rate_is_kept(): void
    {
        $result = $this->parser()->parse("TIENDA\nIVA 10% 0,82\nIVA 21% 10,40\nTOTAL 59,95");

        $this->assertCount(2, $result?->taxes, 'A Spanish ticket prints one component per rate.');
        $this->assertSame(
            [1000, 2100],
            array_map(static fn ($tax) => $tax->rate, $result->taxes),
            'Both printed rates survive, in document order.'
        );
    }

    public function test_the_spanish_tax_breakdown_is_not_reconciled_against_the_total(): void
    {
        $result = $this->parser()->parse(self::SUPERMARKET_TEXT);

        $tax = $result?->taxes[0] ?? null;

        $this->assertNotNull($tax, 'Spanish prices are printed tax-included, so the breakdown is informative.');
        $this->assertNotSame(
            $result?->amount_minor,
            $tax->taxable_base_minor + $tax->tax_amount_minor,
            '12,70 + 1,27 is not 14,00 and that is not an error: the tax is already inside the printed prices.'
        );
    }

    public function test_items_are_discarded_when_the_item_section_is_disabled(): void
    {
        config(['economy.ocr.items.enabled' => false]);

        $result = $this->parser()->parse(self::SUPERMARKET_TEXT);

        $this->assertSame([], $result?->items, 'Disabling the section restores the previous empty list even when the rows reconcile.');
        $this->assertCount(1, $result?->taxes, 'The tax section has its own switch and is unaffected.');
    }

    public function test_taxes_are_discarded_when_the_tax_section_is_disabled(): void
    {
        config(['economy.ocr.taxes.enabled' => false]);

        $result = $this->parser()->parse(self::SUPERMARKET_TEXT);

        $this->assertSame([], $result?->taxes);
        $this->assertCount(3, $result?->items, 'The item section has its own switch and is unaffected.');
    }

    public function test_the_total_date_merchant_and_currency_are_unchanged_by_the_new_sections(): void
    {
        $result = $this->parser()->parse(self::SUPERMARKET_TEXT);

        $this->assertSame(1400, $result?->amount_minor);
        $this->assertSame('2019-03-02', $result?->occurred_at?->toDateString());
        $this->assertSame('MERCADO CENTRAL', $result?->title);
        $this->assertSame('EUR', $result?->currency, 'The CIF still decides the currency.');
        $this->assertSame(
            'MERCADO CENTRAL',
            $result?->place,
            'The merchant is reused as the place, so the by-place report is not blind to imported tickets.'
        );
    }

    /**
     * Resolve the parser under test.
     */
    private function parser(): ReceiptTextParser
    {
        return new ReceiptTextParser;
    }
}
