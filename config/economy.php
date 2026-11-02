<?php

/*
|--------------------------------------------------------------------------
| Economy
|--------------------------------------------------------------------------
|
| Configuration for the economy module. The `ocr` block drives the offline receipt
| reading layer: Tesseract (installed in the application image) plus Imagick read the
| derived receipt image first, for free, and only a ticket the offline layer cannot
| resolve goes to the metered AI analysis.
|
| `ocr.enabled` is the single switch for the whole layer. Setting it to false (or
| removing the Tesseract binary from the image) does NOT break imports: every ticket
| falls back to the AI path exactly as it did before the offline layer existed.
|
| The defaults below are the ones measured on real tickets with Tesseract 5.3.4:
| `--psm 6` wins consistently — `--psm 4` returned 79-110 characters of noise where
| psm 6 returned 297-690 with the total — and the languages are tried one by one,
| because a composite `eng+spa` misread the total of the reference ticket.
|
| Cost: two languages by three preprocessing chains is six Tesseract runs per import,
| roughly 2-8 s on the measured 284x450 ticket images. `max_candidates` caps the matrix
| and `total_timeout_seconds` caps the wall clock, so a slow or hung variant is dropped
| instead of holding the import.
|
| PDF tickets cost a little more: the first page is rendered with Ghostscript through
| Imagick at 300 DPI (~0.5 s), and the resulting page image then walks the same six-run
| matrix (~3-6 s measured on a receipt-sized page). Only the first page is rendered.
|
*/

return [

    'ocr' => [

        /*
        | Master switch for the offline receipt layer. When disabled, no Tesseract
        | process is spawned and every import takes the metered AI path.
        */
        'enabled' => (bool) env('ECONOMY_OCR_ENABLED', true),

        /*
        | Tesseract binary. A bare name is resolved through PATH; an absolute path is
        | used as-is. When the binary cannot be found the layer degrades to the AI path.
        */
        'binary' => (string) env('ECONOMY_OCR_BINARY', 'tesseract'),

        /*
        | Tesseract languages, tried as separate variants in this order. Both languages are
        | installed in the image and both are needed: receipts arrive in English and in
        | Spanish. They are deliberately not combined into `eng+spa`: measured on the real
        | Avenue ticket, the composite reading returned the wrong total (`155,00` instead of
        | `135,00`) in every variant it was tried in, while `spa` alone read it correctly.
        | Order is the tie-break between equally scored variants, and `spa` comes first
        | because it recovered the total on the receipt family this import actually receives.
        */
        'languages' => array_values(array_filter(array_map(
            static fn (string $language): string => trim($language),
            explode(',', (string) env('ECONOMY_OCR_LANGUAGES', 'spa,eng')),
        ))),

        /*
        | Images narrower than this (in pixels) are upscaled 2x by the preprocessed variants;
        | a wider image is read at its own size. The derived ticket images are 284x450 px
        | because `generateAiVersion()` never enlarges, and 3x/4x made every measured ticket
        | worse, so the scale is adaptive instead of fixed.
        */
        'upscale_threshold_px' => (int) env('ECONOMY_OCR_UPSCALE_THRESHOLD_PX', 1000),

        /*
        | Page segmentation mode. 6 ("assume a single uniform block of text") is the
        | measured winner on receipt photos; 4 is deliberately not the default.
        */
        'psm' => (int) env('ECONOMY_OCR_PSM', 6),

        /*
        | Mean per-word confidence floor (0-100) for a variant to be considered. 0 keeps
        | every non-empty variant, which is the measured-safe default: a low-confidence
        | variant still beats no offline text at all.
        */
        'min_confidence' => (float) env('ECONOMY_OCR_MIN_CONFIDENCE', 0),

        /*
        | Per-invocation timeout in seconds. A hung OCR run must never hang the job.
        */
        'timeout_seconds' => (int) env('ECONOMY_OCR_TIMEOUT_SECONDS', 20),

        /*
        | Overall budget in seconds for one document. Once it is exhausted the variants
        | already recognised are kept and the remaining ones are dropped, so a slow ticket
        | cannot delay the import. 0 disables the budget.
        */
        'total_timeout_seconds' => (int) env('ECONOMY_OCR_TOTAL_TIMEOUT_SECONDS', 45),

        /*
        | Preprocessing variants that are tried and scored, once per configured language. No
        | single chain wins on every ticket, so the cheapest set that covered the measured
        | tickets is tried in turn:
        |   raw       — the image as the user cropped it
        |   grayscale — greyscale plus an adaptive 2x upscale, which helps narrow crops
        |   adaptive  — greyscale, the same upscale, deskew and adaptive threshold; keeps
        |               digits but dirties prose, and can return an empty page (discarded
        |               when empty)
        | Disabling all three disables the layer in practice.
        */
        'variants' => [
            'raw' => (bool) env('ECONOMY_OCR_VARIANT_RAW', true),
            'grayscale' => (bool) env('ECONOMY_OCR_VARIANT_GRAYSCALE', true),
            'adaptive' => (bool) env('ECONOMY_OCR_VARIANT_ADAPTIVE', true),
        ],

        /*
        | PDF tickets. The upload accepts PDFs and they used to reach the AI path with no
        | offline chance at all. A PDF is read as a cascade, because the two readings measure
        | very differently: a digital PDF states its own text (measured: `PdfParser` returned
        | `BAR PRUEBA TOTAL TTC 28,84 IVA 21% 5,00` exactly, where OCR of the same page
        | misread the amount), and a scanned PDF has no text at all and needs the raster.
        */
        'pdf' => [

            /*
            | Read the embedded text layer with `smalot/pdfparser` before rasterising. The
            | resulting candidate is placed in front of every OCR variant, because it is an
            | exact reading and the caller keeps the first candidate the parser can turn
            | into an amount. A text layer without an amount does not short-circuit the
            | cascade: the OCR variants still run. Disable it to force the raster path, and
            | note that the layer degrades to that same path on its own when the library is
            | not installed.
            */
            'text_layer' => (bool) env('ECONOMY_OCR_PDF_TEXT_LAYER', true),

            /*
            | Resolution, in DPI, used to render the page. It is set before the file is
            | read because the default of 72 DPI produces digits that cannot be recognised;
            | the value is clamped to the 72-600 range. Cost grows with the square of the
            | resolution: a receipt page takes ~0.5 s to render and ~0.7 s per OCR run at
            | 300 DPI.
            */
            'dpi' => (int) env('ECONOMY_OCR_PDF_DPI', 300),

            /*
            | Ghostscript binary: Imagick's PDF coder delegates the rendering to it, so a
            | missing Ghostscript means PDFs degrade to the AI path instead of throwing. A
            | bare name is resolved through PATH; an absolute path is used as-is.
            */
            'ghostscript_binary' => (string) env('ECONOMY_OCR_GHOSTSCRIPT_BINARY', 'gs'),

        ],

    ],

];
