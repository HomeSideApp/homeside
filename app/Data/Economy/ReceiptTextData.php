<?php

namespace App\Data\Economy;

/**
 * Raw text an offline OCR engine recognised in a receipt image.
 *
 * One instance is one variant: the same image read with a given language and a given
 * preprocessing chain. The language and the variant are kept so the winning combination can
 * be reported for diagnostics — the scoring is deliberately coarse and the measurement of
 * what actually wins per ticket is how the variant matrix is tuned.
 */
final readonly class ReceiptTextData
{
    /**
     * Create a receipt text value object.
     *
     * @param  string  $text  The recognised text, already trimmed.
     * @param  string  $engine  The engine that produced the text, e.g. `tesseract`.
     * @param  string  $variant  The preprocessing variant and scale that produced the text, e.g. `grayscale_2x`.
     * @param  float|null  $confidence  The mean per-word engine confidence (0-100), when the engine reports it.
     * @param  string|null  $language  The Tesseract language the image was read with, e.g. `spa`.
     * @return void This constructor does not return a value.
     */
    public function __construct(
        public string $text,
        public string $engine,
        public string $variant,
        public ?float $confidence = null,
        public ?string $language = null,
    ) {}
}
