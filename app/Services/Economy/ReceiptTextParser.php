<?php

namespace App\Services\Economy;

use App\Data\Economy\ReceiptAnalysisResultData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Turns raw receipt OCR text into structured economic data.
 *
 * The parser is written against real Tesseract output, not against ideal text: the
 * captured tickets contain lines such as `yoTaL 77 135,00 §` or
 * `1 CHATERU FILER oF LUE`. The strategy is therefore "tolerant with the label, strict
 * with the number": a line only becomes a candidate when it looks like a total label, and
 * the value is only accepted when it can be read unambiguously in one of the supported
 * money formats.
 *
 * The amount is the hard requirement. Without an amount the parser returns null so the
 * import falls back to the metered AI path; a receipt with a readable amount and an
 * unreadable date is still a win, because the date is left null instead of being invented.
 *
 * Line items and taxes are deliberately out of scope for the offline layer: they are
 * decidable only by the AI path today, and the review screen lets the user add them.
 */
final class ReceiptTextParser
{
    /**
     * Upper bound for an accepted amount, in minor units (1,000,000.00).
     *
     * Anything above this is OCR noise (a merged barcode, a CUIT or a phone number), and
     * a wrong amount on a confirmed transaction is worse than falling back to the AI.
     */
    private const MAX_AMOUNT_MINOR = 100_000_000;

    /**
     * Month names in the three languages receipts show up in, after ASCII folding.
     *
     * @var array<string, int>
     */
    private const MONTHS = [
        'ene' => 1, 'enero' => 1, 'jan' => 1, 'january' => 1, 'janv' => 1, 'janvier' => 1,
        'feb' => 2, 'febrero' => 2, 'february' => 2, 'fev' => 2, 'fevr' => 2, 'fevrier' => 2,
        'mar' => 3, 'marzo' => 3, 'march' => 3, 'mars' => 3,
        'abr' => 4, 'abril' => 4, 'apr' => 4, 'april' => 4, 'avr' => 4, 'avril' => 4,
        'may' => 5, 'mayo' => 5, 'mai' => 5,
        'jun' => 6, 'junio' => 6, 'june' => 6, 'juin' => 6,
        'jul' => 7, 'julio' => 7, 'july' => 7, 'juil' => 7, 'juillet' => 7,
        'ago' => 8, 'agosto' => 8, 'aug' => 8, 'august' => 8, 'aou' => 8, 'aout' => 8,
        'sep' => 9, 'set' => 9, 'septiembre' => 9, 'september' => 9, 'septembre' => 9,
        'oct' => 10, 'octubre' => 10, 'october' => 10, 'octobre' => 10,
        'nov' => 11, 'noviembre' => 11, 'november' => 11, 'novembre' => 11,
        'dic' => 12, 'diciembre' => 12, 'dec' => 12, 'december' => 12, 'decembre' => 12,
    ];

    /**
     * Strength of the payable-total family: a strong label always outranks a weaker one.
     *
     * Measured need: on the Zara ticket the `IMPORTE` of an item-table column header used
     * to win over the real `Total`, so `IMPORTE` alone is a medium label, never a strong
     * one, and `SUBTOTAL`/`BASE IMPONIBLE` are weak.
     */
    private const STRENGTH_STRONG = 3;

    /**
     * Strength of `IMPORTE`, the label Spanish receipts print when they print no `TOTAL`.
     */
    private const STRENGTH_MEDIUM = 2;

    /**
     * Strength of subtotals and taxable bases: only used when nothing stronger is legible.
     */
    private const STRENGTH_WEAK = 1;

    /**
     * Strong payable-total labels: `TOTAL`, `TOTALE` (with its `EUR`/`EUROP`/`EURO` suffix),
     * `TOTAL A PAGAR`, `TOTAL TTC`, `AMOUNT DUE`, `SUMA`, `A PAGAR` and `TOT.`.
     *
     * The bare word alternatives cover the suffixed forms, and the word boundaries keep
     * `SUBTOTAL`/`TOTALE` out of the `TOTAL`/`TOT` match instead of relying on pattern order.
     */
    private const STRONG_LABEL_PATTERN = '/\b(total|totale|tot|suma|amount|amt|montant|a\s*pagar)\b/u';

    /**
     * Medium payable-total label: `IMPORTE`.
     */
    private const MEDIUM_LABEL_PATTERN = '/\bimporte\b/u';

    /**
     * Weak labels: a subtotal is not the payable amount.
     */
    private const WEAK_LABEL_PATTERN = '/\b(sub\s*-?\s*total|subtotale|sous\s*-?\s*total|base\s*imponible|base\s*imposable)\b/u';

    /**
     * Words that make an amount line a non-total (tax, discount, change, line item).
     *
     * `TTC` (French, tax included) and `A PAGAR` are deliberately absent: they are totals.
     * `PUNTOS`, `GOLES`, `PARTE` and `CUOTA` are bet-market nouns: on the measured
     * sports-betting ticket `TOTAL PUNTOS EQ.` labels a market, so its trailing market
     * code must never be read as the payable amount.
     */
    private const NON_TOTAL_WORDS = 'iva|i\.v\.a|vat|tax|taxe|impuesto|descuento|discount|remise|ahorro|saving|savings|change|vuelto|cambio|propina|tip|quantity|cantidad|unidades|articulos|items|qty|puntos|goles|parte|cuota';

    /**
     * Column-header words of the item table.
     *
     * A line that names two or more of them is a column header, not a total: on the Zara
     * ticket `TS 108 PUN PRECIO IMPORTE A` carries the `108` of the header row and used to
     * win over the real `Total 1 59,95` printed at the foot.
     */
    private const COLUMN_HEADER_WORDS = '/\b(importe|precio|price|p\s*[.]?\s*unit(?:ario)?|u\s*[.]?\s*price|cant(?:idad)?|articulo|descripcion)\b/u';

    /**
     * Minimum number of distinct column-header words that makes a line a header.
     */
    private const MIN_COLUMN_HEADER_WORDS = 2;

    /**
     * Labels of the price printed *before* the discount: never the amount that was paid.
     *
     * Measured need: the CONNOR ticket prints `Original Total $29,195.00` next to
     * `YOU SAVE $29,175.05`, while the real `TOTAL $19.95` was not recognised at all. The
     * pre-discount price is a strong `TOTAL` line, so it used to be reported as the amount
     * charged. `ANTES DE DESCUENTO` and `CON DESCUENTO` need no alternative here: the
     * NON_TOTAL_WORDS already reject every line that mentions a discount.
     */
    private const ORIGINAL_TOTAL_PATTERN = '/\b(?:original\w*)\b/u';

    /**
     * The retail `PVP` (precio de venta al público) label in its printed spellings.
     */
    private const RETAIL_PRICE_PATTERN = '/\bp\s*\.?\s*v\s*\.?\s*p\b/u';

    /**
     * OCR-damaged spellings of `original` in a pre-discount total line.
     *
     * The parser is tolerant with labels because that is what the engine returns: on the
     * CONNOR ticket `Original Total` came back as `gina) Total` in one variant and as
     * `plrtoinal Total` in another. The tail of the word is what survives, so the match is
     * anchored on it, at the start of a token or after a consonant — which keeps real words
     * that merely contain the same letters (`pagina`) out of the discard.
     */
    private const ORIGINAL_TOTAL_OCR_TAIL_PATTERN = '/(?:^|[^aeiou\s])(?:gina|ginal|oinal)\b/u';

    /**
     * Words that never identify a merchant name.
     */
    private const NON_TITLE_WORDS = 'total|subtotal|subtotale|totale|importe|precio|price|amount|amt|montant|suma|summe|fecha|date|hora|time|hde|cif|cuit|cuil|nif|iva|vat|tax|ticket|factura|invoice|receipt|tel|tlf|fax|telefono|telephone|phone|trans|operacion|table|mesa|due|change|vuelto|cambio|merci|gracias|thank|client|cliente|card|tarjeta|cash|efectivo|item|cant|qty|desc|descripcion|description|servicio|service|contrapartida|acumulada|evento|cuota|goles|puntos|articulo|artikel|bon|rue|via|calle|avda|avenida|piazza|gasse|strasse|blvd|boulevard';

    /**
     * Number of leading lines scored for the merchant name.
     *
     * The name sits at the top of the receipt, so a short window keeps the store's own
     * footer (`TARJETA FIDELIDAD SEPHORA`, `Vielen Dank ...`) from outscoring it. The
     * measured tickets resolve the merchant inside this window, and widening it starts to
     * lose `Sephora Cosmeticos España, S.L.` to a later, more regular line.
     */
    private const TITLE_WINDOW = 8;

    /**
     * Characters trimmed from the edges of a candidate name: punctuation and typographic
     * quotes, never `?`/`¿`, which must keep hurting the line that carries them.
     */
    private const TITLE_TRIM_CHARACTERS = " \t\n\r\0\x0B.,:;-_=|*#·•\"'§€$%()[]{}\u{2018}\u{2019}\u{201C}\u{201D}\u{00AB}\u{00BB}\u{2013}\u{2014}";

    /**
     * Characters that mark a line as OCR noise rather than a name.
     */
    private const TITLE_NOISE_CHARACTERS = '/[|~§^\\\\*=<>@#\/]/u';

    /**
     * Runs of two or more consecutive symbols, a strong sign of OCR noise.
     */
    private const TITLE_SYMBOL_RUNS = '/(?:[^\p{L}\p{N}\s]){2,}/u';

    /**
     * Parse receipt text into structured economic data.
     *
     * @param  string  $text  The raw text recognised by the offline OCR engine.
     * @return ReceiptAnalysisResultData|null The structured data, or null when no amount was recognised.
     */
    public function parse(string $text): ?ReceiptAnalysisResultData
    {
        $lines = $this->lines($text);

        $total = $this->extractTotal($lines);

        if ($total === null) {
            return null;
        }

        return new ReceiptAnalysisResultData(
            title: $this->extractTitle($lines),
            amount_minor: $total['amount_minor'],
            currency: $this->detectCurrency($total['lines'], $text),
            place: null,
            occurred_at: $this->extractDate($text),
            items: [],
            taxes: [],
        );
    }

    /**
     * Split the recognised text into trimmed, non-empty lines.
     *
     * @param  string  $text  The recognised text.
     * @return list<string> The significant lines in recognition order.
     */
    private function lines(string $text): array
    {
        $raw = preg_split('/\R/u', $text) ?: [];

        $lines = [];

        foreach ($raw as $line) {
            $line = trim($line);

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * Find the payable amount in minor units, together with the lines that printed it.
     *
     * Three rules, all measured on real tickets:
     *
     *   1. Label hierarchy. A strong label (`TOTAL`, `TOTALE EUROP`, `TOTAL A PAGAR`,
     *      `AMOUNT DUE`, `A PAGAR`, `TOT.`) always outranks `IMPORTE`, which always outranks
     *      a subtotal. A weak label never wins while a strong one is legible.
     *   2. Column headers are not totals. A line that names two item-table columns is the
     *      header of the table, whatever amount it carries.
     *   3. Within the same strength the last occurrence wins: totals are printed at the foot,
     *      so the bottom-most occurrence of the same label is the payable one.
     *
     * Lines that are excluded before any of the above: the item-table column headers, the
     * tax/discount/change lines, the retired-currency conversions and the pre-discount
     * total. See {@see amountLineStrength()}.
     *
     * The lines travel with the amount because the currency is decided next to it: the
     * marker that counts is the one printed on the payable line (or right below it when the
     * amount is printed underneath its label), not one that happens to appear anywhere else
     * on the paper.
     *
     * @param  list<string>  $lines  The recognised lines.
     * @return array{amount_minor: int, lines: list<string>}|null The amount in minor units and its source lines, or null when no total is legible.
     */
    private function extractTotal(array $lines): ?array
    {
        $best = null;
        $bestStrength = 0;

        foreach ($lines as $index => $line) {
            $strength = $this->amountLineStrength($line);

            if ($strength === 0) {
                continue;
            }

            $amount = $this->lineAmount($line, $lines[$index + 1] ?? null);

            if ($amount === null) {
                continue;
            }

            // A later line of the same strength replaces the earlier one; a weaker label
            // never replaces a stronger one.
            if ($strength >= $bestStrength) {
                $best = $amount;
                $bestStrength = $strength;
            }
        }

        return $best;
    }

    /**
     * Rate how strongly a line claims to carry the payable total.
     *
     * @param  string  $line  A recognised line.
     * @return int One of the STRENGTH_* constants, or 0 when the line is not an amount line.
     */
    private function amountLineStrength(string $line): int
    {
        $normalized = Str::lower(Str::ascii($line));

        if ($this->isColumnHeader($normalized)) {
            return 0;
        }

        if (preg_match('/\b('.self::NON_TOTAL_WORDS.')\b/u', $normalized) === 1) {
            return 0;
        }

        if ($this->isNonPayableAmountLine($normalized)) {
            return 0;
        }

        if (preg_match(self::STRONG_LABEL_PATTERN, $normalized) === 1) {
            return self::STRENGTH_STRONG;
        }

        if (preg_match(self::MEDIUM_LABEL_PATTERN, $normalized) === 1) {
            return self::STRENGTH_MEDIUM;
        }

        if (preg_match(self::WEAK_LABEL_PATTERN, $normalized) === 1) {
            return self::STRENGTH_WEAK;
        }

        return 0;
    }

    /**
     * Decide whether a normalized line labels a price that is not the amount paid.
     *
     * The pre-discount price is rejected here, before the strength hierarchy runs, so a
     * discarded line can never win a tie: `ORIGINAL TOTAL`, `TOTAL ORIGINAL`,
     * `PRECIO ORIGINAL`, `PVP` and the OCR-damaged spellings of `original`. It is what the
     * goods cost before the discount, not what the customer paid.
     *
     * Retired currencies (pesetas, lira, franc, mark) are deliberately NOT handled: they
     * only appear on receipts from before the euro, which nobody still prints. Carrying a
     * rule for them also risked discarding a real total on a line that merely contained
     * such a word as a product or shop name.
     *
     * @param  string  $normalized  The lower-cased, ASCII-folded line.
     * @return bool True when the line cannot carry the payable total.
     */
    private function isNonPayableAmountLine(string $normalized): bool
    {
        return preg_match(self::ORIGINAL_TOTAL_PATTERN, $normalized) === 1
            || preg_match(self::ORIGINAL_TOTAL_OCR_TAIL_PATTERN, $normalized) === 1
            || preg_match(self::RETAIL_PRICE_PATTERN, $normalized) === 1;
    }

    /**
     * Decide whether a normalized line is a column header of the item table.
     *
     * @param  string  $normalized  The lower-cased, ASCII-folded line.
     * @return bool True when the line names more than one item-table column.
     */
    private function isColumnHeader(string $normalized): bool
    {
        if (preg_match_all(self::COLUMN_HEADER_WORDS, $normalized, $matches) === false) {
            return false;
        }

        return count(array_unique($matches[1])) >= self::MIN_COLUMN_HEADER_WORDS;
    }

    /**
     * Read the amount of an amount line, falling back to the line printed right after it.
     *
     * The lines that produced the amount are returned with it so the currency can be read
     * from the same place the number was read from.
     *
     * @param  string  $line  The amount line.
     * @param  string|null  $nextLine  The following line, when the amount is printed below its label.
     * @return array{amount_minor: int, lines: list<string>}|null The amount in minor units and its source lines, or null when the line carries no unambiguous number.
     */
    private function lineAmount(string $line, ?string $nextLine): ?array
    {
        $amount = $this->rightmostAmountMinor($line);

        if ($amount !== null) {
            return ['amount_minor' => $amount, 'lines' => [$line]];
        }

        if ($nextLine === null) {
            return null;
        }

        // A label with the amount printed underneath is common on Spanish receipts, but
        // only a line that is essentially nothing but a number is accepted.
        if (preg_match('/^[^\p{L}\d]*(\d[\d.,]*)[^\p{L}\d]*$/u', $nextLine, $matches) !== 1) {
            return null;
        }

        $amount = $this->toMinorUnits($matches[1]);

        if ($amount === null) {
            return null;
        }

        return ['amount_minor' => $amount, 'lines' => [$line, $nextLine]];
    }

    /**
     * Read the rightmost number of a line.
     *
     * The rightmost number is the value: `TOTAL TTC 135,00 €` and `TOTAL 3 ARTICULOS 135,00`
     * both end with the payable amount.
     *
     * @param  string  $line  A recognised line.
     * @return int|null The amount in minor units, or null when no number is legible.
     */
    private function rightmostAmountMinor(string $line): ?int
    {
        if (preg_match_all('/\d+(?:[.,]\d+)*/u', $line, $matches) === false || $matches[0] === []) {
            return null;
        }

        return $this->toMinorUnits((string) end($matches[0]));
    }

    /**
     * Convert a recognised money token into minor units.
     *
     * Supports `1.234,56` (es-AR), `1,234.56` (en), `1234,56` and `75.00`, and rejects
     * ambiguous tokens such as `1.2345` instead of guessing a separator.
     *
     * @param  string  $token  The number token as it appears in the text.
     * @return int|null The amount in minor units, or null when the token is not a sane amount.
     */
    private function toMinorUnits(string $token): ?int
    {
        $token = trim($token);

        if (preg_match('/^\d[\d.,]*$/u', $token) !== 1) {
            return null;
        }

        $integerPart = $token;
        $decimals = null;

        if (preg_match('/^(\d{1,3}(?:\.\d{3})+)(?:,(\d{1,2}))?$/u', $token, $matches) === 1) {
            // 1.234 / 1.234,56 — the dot groups thousands.
            $integerPart = str_replace('.', '', $matches[1]);
            $decimals = $matches[2] ?? null;
        } elseif (preg_match('/^(\d{1,3}(?:,\d{3})+)(?:\.(\d{1,2}))?$/u', $token, $matches) === 1) {
            // 1,234 / 1,234.56 — the comma groups thousands.
            $integerPart = str_replace(',', '', $matches[1]);
            $decimals = $matches[2] ?? null;
        } elseif (preg_match('/^(\d+)[.,](\d{1,2})$/u', $token, $matches) === 1) {
            // 1234,56 / 75.00 — a single separator is the decimal one.
            $integerPart = $matches[1];
            $decimals = $matches[2];
        } elseif (preg_match('/^\d+$/u', $token) !== 1) {
            return null;
        }

        if (strlen($integerPart) > 9) {
            return null;
        }

        $minor = (int) $integerPart * 100;

        if ($decimals !== null) {
            $minor += (int) str_pad($decimals, 2, '0');
        }

        if ($minor <= 0 || $minor > self::MAX_AMOUNT_MINOR) {
            return null;
        }

        return $minor;
    }

    /**
     * Detect the receipt currency.
     *
     * The marker that counts is the one printed next to the payable amount, so the search
     * starts on the lines that produced the amount and only then widens to the whole
     * recognised text: a ticket whose total is `TOTAL $ 1.000` is a peso ticket even when a
     * `EQUIV. EUR` sits in its footer.
     *
     * Signal order, which is the same in both passes because it is a property of the
     * markers and not of where they are found:
     *
     *   1. `US$`/`USD` → USD. `US$` is checked first because it *contains* `$`: reading the
     *      dollar sign before it would turn every US-dollar receipt into an Argentine peso.
     *   2. `€`/`EUR` → EUR.
     *   3. `ARS` → ARS, matched on word boundaries so `NARS` in a product name is not a
     *      peso marker.
     *   4. A bare `$` → ARS.
     *
     * With no marker at all the answer is ARS. That is a product decision, not a guess: the
     * application is Argentine, most receipts arrive in pesos, and the parsed amount is
     * shown on the review screen where the person confirms or corrects the currency before
     * anything is saved. Deriving a currency from the receipt date, the merchant's language
     * or the address would be inventing data, so it is never done.
     *
     * @param  list<string>  $amountLines  The lines the payable amount was read from.
     * @param  string  $text  The whole recognised text, used only when those lines carry no marker.
     * @return string The ISO currency code.
     */
    private function detectCurrency(array $amountLines, string $text): string
    {
        return $this->currencyOfLines($amountLines)
            ?? $this->currencyOfLines($this->lines($text))
            ?? 'ARS';
    }

    /**
     * Read the strongest currency marker of a block of lines.
     *
     * One pass per signal, in priority order, so the order holds across the whole block and
     * not only inside one line: an explicit `US$` in the footer outranks a `$` printed
     * above it instead of losing to document order.
     *
     * @param  list<string>  $lines  The lines to scan.
     * @return string|null The ISO currency code, or null when no line carries a marker.
     */
    private function currencyOfLines(array $lines): ?string
    {
        foreach ($lines as $line) {
            if ($this->lineHasUsDollar($line)) {
                return 'USD';
            }
        }

        foreach ($lines as $line) {
            if ($this->lineHasEuro($line)) {
                return 'EUR';
            }
        }

        foreach ($lines as $line) {
            if (preg_match('/\bARS\b/u', Str::upper($line)) === 1) {
                return 'ARS';
            }
        }

        foreach ($lines as $line) {
            if (str_contains($line, '$')) {
                return 'ARS';
            }
        }

        return null;
    }

    /**
     * Decide whether a line carries a US-dollar marker.
     *
     * `US$` and its Argentine spelling `U$S` are checked before the bare `$` everywhere,
     * because both contain it: reading `$` first would turn every US-dollar receipt into a
     * peso.
     *
     * @param  string  $line  A recognised line.
     */
    private function lineHasUsDollar(string $line): bool
    {
        $upper = Str::upper($line);

        return str_contains($upper, 'US$')
            || preg_match('/\bU\$S\b/u', $upper) === 1
            || preg_match('/\bUSD\b/u', $upper) === 1;
    }

    /**
     * Decide whether a line carries a euro marker.
     *
     * @param  string  $line  A recognised line.
     */
    private function lineHasEuro(string $line): bool
    {
        return str_contains($line, '€') || preg_match('/\bEUR\b/u', Str::upper($line)) === 1;
    }

    /**
     * Find the most plausible receipt date.
     *
     * Supported shapes: `yyyy-mm-dd`, `dd/mm/yyyy`, `dd-mm-yy`, `dd.mm.yyyy` and the same
     * days with Spanish, English or French month names. The year must be plausible; a
     * receipt from 1800 is OCR noise, so the date is dropped instead of invented.
     *
     * @param  string  $text  The recognised text.
     * @return Carbon|null The recognised date, or null when no plausible date was found.
     */
    private function extractDate(string $text): ?Carbon
    {
        if (preg_match_all('/(?<!\d)(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})(?!\d)/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $date = $this->buildDate((int) $match[1], (int) $match[2], (int) $match[3]);

                if ($date !== null) {
                    return $date;
                }
            }
        }

        if (preg_match_all('/(?<!\d)(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2,4})(?!\d)/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $date = $this->buildDate((int) $match[3], (int) $match[2], (int) $match[1]);

                if ($date !== null) {
                    return $date;
                }
            }
        }

        if (preg_match_all('/(?<![\p{L}\d])(\d{1,2})[\s.\-\/]*([\p{L}]{3,12})\.?[\s.\-\/]+(\d{2,4})(?!\d)/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $month = self::MONTHS[Str::lower(Str::ascii($match[2]))] ?? null;

                if ($month === null) {
                    continue;
                }

                $date = $this->buildDate((int) $match[3], $month, (int) $match[1]);

                if ($date !== null) {
                    return $date;
                }
            }
        }

        if (preg_match_all('/(?<![\p{L}\d])([\p{L}]{3,12})\.?[\s.\-\/]+(\d{1,2}),?[\s.\-\/]+(\d{2,4})(?!\d)/u', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $month = self::MONTHS[Str::lower(Str::ascii($match[1]))] ?? null;

                if ($month === null) {
                    continue;
                }

                $date = $this->buildDate((int) $match[3], $month, (int) $match[2]);

                if ($date !== null) {
                    return $date;
                }
            }
        }

        return null;
    }

    /**
     * Build a date when month, day and year form a real, plausible calendar date.
     *
     * @param  int  $year  The recognised year, two or four digits.
     * @param  int  $month  The recognised month.
     * @param  int  $day  The recognised day.
     * @return Carbon|null The date, or null when it is impossible or implausible.
     */
    private function buildDate(int $year, int $month, int $day): ?Carbon
    {
        if ($year < 100) {
            $year += 2000;
        }

        $latestPlausibleYear = (int) Carbon::now()->year + 1;

        if ($year < 2000 || $year > $latestPlausibleYear) {
            return null;
        }

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return Carbon::create($year, $month, $day, 0, 0, 0);
    }

    /**
     * Find the merchant name.
     *
     * Measured need: taking the first line that cleared a weak filter returned garbage such
     * as `hiwtacón mesas?` (Zara) and `s C A Ah madrid` (a bar), because the OCR noise sits
     * above the real name. So the leading lines are scored and the best one wins.
     *
     * The score rewards what a merchant name looks like — several words, mostly letters,
     * few digits, upper case — and penalizes what OCR noise and receipt boilerplate look
     * like. Position is part of the score because the merchant is printed at the top; it
     * must stay small enough that `APUESTAS DEPORTIVAS` still beats the longer construction
     * below it, and strong enough that `ZARA` beats the noise line above it.
     *
     * @param  list<string>  $lines  The recognised lines.
     * @return string|null The merchant name, or null when no line looks like one.
     */
    private function extractTitle(array $lines): ?string
    {
        $bestTitle = null;
        $bestScore = null;

        foreach (array_slice($lines, 0, self::TITLE_WINDOW) as $index => $line) {
            $score = $this->titleScore($line, $index);

            if ($score === null) {
                continue;
            }

            if ($bestScore === null || $score > $bestScore) {
                $candidate = trim($line, self::TITLE_TRIM_CHARACTERS);
                $bestTitle = Str::limit($candidate, 80, '');
                $bestScore = $score;
            }
        }

        return $bestTitle;
    }

    /**
     * Score one leading line as a merchant name.
     *
     * @param  string  $line  The recognised line.
     * @param  int  $index  Its position among the leading lines, 0 being the first.
     * @return float|null The score, or null when the line cannot be a merchant name.
     */
    private function titleScore(string $line, int $index): ?float
    {
        $candidate = trim($line, self::TITLE_TRIM_CHARACTERS);

        if (mb_strlen($candidate) < 3) {
            return null;
        }

        $letters = $this->countMatches('/\p{L}/u', $candidate);
        $visible = $this->countMatches('/\S/u', $candidate);

        if ($letters < 3 || $letters * 2 < $visible) {
            return null;
        }

        if ($this->extractDate($candidate) !== null) {
            return null;
        }

        if (preg_match('/\b('.self::NON_TITLE_WORDS.')\b/u', Str::lower(Str::ascii($candidate))) === 1) {
            return null;
        }

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $candidate, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $informativeTokens = 0;
        $singleLetterTokens = 0;
        $mixedTokens = 0;
        $wordTokens = 0;
        $twoLetterWordTokens = 0;

        foreach ($tokens as $token) {
            $length = mb_strlen($token);
            $hasLetters = preg_match('/\p{L}/u', $token) === 1;

            if ($length >= 2) {
                $informativeTokens++;
            } elseif ($hasLetters) {
                $singleLetterTokens++;
            }

            if ($hasLetters && $length >= 2) {
                $wordTokens++;

                if ($length === 2) {
                    $twoLetterWordTokens++;
                }
            }

            if ($hasLetters && preg_match('/\p{N}/u', $token) === 1) {
                $mixedTokens++;
            }
        }

        // A line whose words are mostly two letters long is a mangled OCR fragment
        // (`Ll ei 7 mon`), not a merchant name: real words of a receipt are longer, and
        // rejecting the fragments is a hard gate because their score is indistinguishable
        // from that of the noise line above them.
        if ($wordTokens > 0 && $twoLetterWordTokens * 2 > $wordTokens) {
            return null;
        }

        $digits = $this->countMatches('/\p{N}/u', $candidate);
        $uppercase = $this->countMatches('/\p{Lu}/u', $candidate);
        $noise = $this->countMatches(self::TITLE_NOISE_CHARACTERS, $candidate);
        $runs = $this->countMatches(self::TITLE_SYMBOL_RUNS, $candidate);
        $question = preg_match('/[?¿]/u', $candidate) === 1 ? 1 : 0;
        $capsRatio = $letters > 0 ? $uppercase / $letters : 0.0;
        $density = $visible > 0 ? $letters / $visible : 0.0;

        return $letters
            + $informativeTokens * 2
            + $capsRatio * 2
            + $density * 2
            - $digits * 3
            - $singleLetterTokens * 2
            - $mixedTokens * 6
            - $noise * 4
            - $question * 10
            - $runs * 6
            + (self::TITLE_WINDOW - $index);
    }

    /**
     * Count non-overlapping matches of a pattern.
     *
     * @param  string  $pattern  A PCRE pattern.
     * @param  string  $subject  The text to scan.
     * @return int The number of matches, 0 when the pattern does not apply.
     */
    private function countMatches(string $pattern, string $subject): int
    {
        $count = preg_match_all($pattern, $subject);

        return $count === false ? 0 : $count;
    }
}
