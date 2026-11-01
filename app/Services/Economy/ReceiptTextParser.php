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
     * Words that make an amount line a non-total (tax, discount, change, line item).
     *
     * `TTC` (French, tax included) and `A PAGAR` are deliberately absent: they are totals.
     */
    private const NON_TOTAL_WORDS = 'iva|i\.v\.a|vat|tax|taxe|impuesto|descuento|discount|remise|ahorro|saving|savings|change|vuelto|cambio|propina|tip|quantity|cantidad|unidades|articulos|items|qty';

    /**
     * Words that never identify a merchant name.
     */
    private const NON_TITLE_WORDS = 'total|subtotal|importe|amount|amt|montant|suma|fecha|date|hora|time|hde|cuit|cuil|nif|iva|vat|tax|ticket|factura|invoice|receipt|tel|telefono|telephone|phone|table|mesa|due|change|vuelto|cambio|merci|gracias|thank|client|cliente|card|tarjeta|cash|efectivo|item|cant|qty|desc|descripcion|description|servicio|service';

    /**
     * Parse receipt text into structured economic data.
     *
     * @param  string  $text  The raw text recognised by the offline OCR engine.
     * @return ReceiptAnalysisResultData|null The structured data, or null when no amount was recognised.
     */
    public function parse(string $text): ?ReceiptAnalysisResultData
    {
        $lines = $this->lines($text);

        $amountMinor = $this->extractAmountMinor($lines);

        if ($amountMinor === null) {
            return null;
        }

        return new ReceiptAnalysisResultData(
            title: $this->extractTitle($lines),
            amount_minor: $amountMinor,
            currency: $this->detectCurrency($text),
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
     * Find the payable amount in minor units.
     *
     * Total lines win over subtotal lines, and among the totals the largest value wins:
     * a receipt can print `TOTAL ARTICULOS 3` next to the real payable total, but the
     * payable total is the biggest number of the total family.
     *
     * @param  list<string>  $lines  The recognised lines.
     * @return int|null The amount in minor units, or null when no total is legible.
     */
    private function extractAmountMinor(array $lines): ?int
    {
        $totals = [];
        $subtotals = [];

        foreach ($lines as $index => $line) {
            $kind = $this->classifyAmountLine($line);

            if ($kind === null) {
                continue;
            }

            $amount = $this->lineAmountMinor($line, $lines[$index + 1] ?? null);

            if ($amount === null) {
                continue;
            }

            if ($kind === 'total') {
                $totals[] = $amount;
            } else {
                $subtotals[] = $amount;
            }
        }

        if ($totals !== []) {
            return max($totals);
        }

        if ($subtotals !== []) {
            return max($subtotals);
        }

        return null;
    }

    /**
     * Classify a line as a total, a subtotal or neither.
     *
     * @param  string  $line  A recognised line.
     * @return string|null `total`, `subtotal`, or null when the line is not an amount line.
     */
    private function classifyAmountLine(string $line): ?string
    {
        $normalized = Str::lower(Str::ascii($line));

        if (preg_match('/sub\s*-?\s*total|sous\s*-?\s*total/u', $normalized) === 1) {
            return 'subtotal';
        }

        if (preg_match('/\b(total|tot|importe|amount|amt|montant|suma|a\s*pagar)\b/u', $normalized) !== 1) {
            return null;
        }

        if (preg_match('/\b('.self::NON_TOTAL_WORDS.')\b/u', $normalized) === 1) {
            return null;
        }

        return 'total';
    }

    /**
     * Read the amount of an amount line, falling back to the line printed right after it.
     *
     * @param  string  $line  The amount line.
     * @param  string|null  $nextLine  The following line, when the amount is printed below its label.
     * @return int|null The amount in minor units, or null when the line carries no unambiguous number.
     */
    private function lineAmountMinor(string $line, ?string $nextLine): ?int
    {
        $amount = $this->rightmostAmountMinor($line);

        if ($amount !== null) {
            return $amount;
        }

        if ($nextLine === null) {
            return null;
        }

        // A label with the amount printed underneath is common on Spanish receipts, but
        // only a line that is essentially nothing but a number is accepted.
        if (preg_match('/^[^\p{L}\d]*(\d[\d.,]*)[^\p{L}\d]*$/u', $nextLine, $matches) !== 1) {
            return null;
        }

        return $this->toMinorUnits($matches[1]);
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
     * `€`/`EUR` and `USD` are explicit markers; a bare `$` is read as ARS, which is also
     * the documented default when no marker survived the OCR.
     *
     * @param  string  $text  The recognised text.
     * @return string The ISO currency code.
     */
    private function detectCurrency(string $text): string
    {
        $upper = Str::upper($text);

        if (str_contains($text, '€') || preg_match('/\bEUR\b/u', $upper) === 1) {
            return 'EUR';
        }

        if (preg_match('/\bUSD\b/u', $upper) === 1 || str_contains($text, 'US$')) {
            return 'USD';
        }

        return 'ARS';
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
     * The merchant is the first line that looks like a name: it has real letters, is not a
     * date, does not contain a receipt keyword and is not a bare number.
     *
     * @param  list<string>  $lines  The recognised lines.
     * @return string|null The merchant name, or null when no line looks like one.
     */
    private function extractTitle(array $lines): ?string
    {
        foreach ($lines as $line) {
            $candidate = trim($line, " \t\n\r\0\x0B.,:;-_=|*#·•\"'§€$%()[]{}");

            if (mb_strlen($candidate) < 3) {
                continue;
            }

            $letters = (int) preg_match_all('/\p{L}/u', $candidate);
            $visible = (int) preg_match_all('/\S/u', $candidate);

            if ($letters < 3 || $letters * 2 < $visible) {
                continue;
            }

            if ($this->extractDate($candidate) !== null) {
                continue;
            }

            $normalized = Str::lower(Str::ascii($candidate));

            if (preg_match('/\b('.self::NON_TITLE_WORDS.')\b/u', $normalized) === 1) {
                continue;
            }

            return Str::limit($candidate, 80, '');
        }

        return null;
    }
}
