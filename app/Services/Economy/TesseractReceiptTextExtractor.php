<?php

namespace App\Services\Economy;

use App\Contracts\ReceiptTextExtractor;
use App\Data\Economy\ReceiptTextData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Imagick;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Reads receipt text offline with Tesseract, using Imagick for preprocessing.
 *
 * Nothing here is metered and nothing here calls the AI: it is the free first pass in
 * front of the paid analysis, so every failure mode degrades to an empty result instead of
 * throwing.
 *
 * No single recognition setup wins on real tickets, so the engine reports a small set of
 * variants instead of a single guess. Two dimensions were measured on the real ticket
 * images the import receives (284x450 px, derived by `generateAiVersion()`, which never
 * enlarges), with `TOTAL TTC: 135,00 €` as ground truth:
 *
 *   - Language. A composite `-l eng+spa` misread the total as `155,00` in every variant it
 *     was tried in, so languages are separate variants: `spa` read the total correctly at
 *     1x (`TOTAL TTE2 135,00`), `eng` read the surrounding prose better but turned the
 *     `3` into a `5`. Each language is therefore its own candidate.
 *   - Scale. 3x and 4x made every language worse, so the upscale is adaptive: a doubled
 *     image only when the source is narrower than the configured threshold, and the raw
 *     image untouched otherwise.
 *
 * `--psm 6` is kept for all variants: measured against the same tickets, psm 4 returned
 * 79-110 characters of noise where psm 6 returned 297-690 with the total on them.
 *
 * A variant's score is deliberately cheap — no parsing, no length bonus, no confidence
 * bonus. Measured, the longest and most confident variants were exactly the ones that
 * misread the total, so volume must never outrank evidence of a total line. The decision
 * of which candidate is right belongs to the parser, which walks the candidates in order.
 *
 * Two measured traps are handled explicitly: `adaptiveThresholdImage()` can blank the page,
 * so a variant with empty output is discarded instead of winning, and the number of
 * Tesseract invocations is capped (see MAX_CANDIDATES and the time budget) because a hung
 * or slow variant must not delay the import.
 *
 * PDF tickets are recognised too, because the upload accepts them and they used to reach
 * this layer with no offline chance at all. A PDF is not one document but two kinds, and the
 * text layer decides which one it is: measured on a generated receipt, `PdfParser` read a
 * digital PDF exactly (`BAR PRUEBA TOTAL TTC 28,84 IVA 21% 5,00`) where OCR on the same page
 * misread the total, and a scanned PDF with no text layer yielded zero characters. Text and
 * rasterisation are therefore a cascade, never alternatives:
 *
 *   1. A PDF is first read through its text layer, once. A candidate with real content is
 *      put in front of every OCR variant, because the caller keeps the first candidate the
 *      parser can read and the text layer is exact.
 *   2. The page is then rasterised once with Imagick (which delegates the rendering to the
 *      already-installed Ghostscript) and walks exactly the same variant matrix as an
 *      uploaded image — there is no second OCR path.
 *
 * The cascade only degrades in one direction: a document whose text layer is unreadable or
 * absent is recognised from the raster, and a document that cannot be rasterised (no
 * Ghostscript, an Imagick built without PDF support, a corrupt file) is still readable when
 * its text layer has content. A text layer without a payable amount never stops the OCR
 * variants from being tried — it only fails to be the first parseable candidate.
 *
 * Two details decide whether the raster path works at all:
 *
 *   - Resolution. The page is rendered at 300 DPI, and the resolution must be set *before*
 *     the file is read: a PDF page has no pixel size of its own, so ImageMagick's default of
 *     72 DPI renders a receipt line far too small to read. Measured on the generated fixture,
 *     `TOTAL TTC 28,84` was read exactly at 300 DPI and misread as `TOTAL TI 28,54` at 72 DPI.
 *   - Pages. Only page 0 is requested, because a ticket is a single page and a 50-page upload
 *     must not cost 50 recognitions. ImageMagick forwards the page selection to Ghostscript,
 *     so the remaining pages are never rendered either.
 */
final class TesseractReceiptTextExtractor implements ReceiptTextExtractor
{
    /**
     * MIME types of the images this version can recognise directly.
     *
     * @var list<string>
     */
    private const SUPPORTED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/bmp',
        'image/tiff',
        'image/webp',
    ];

    /**
     * MIME type of the documents that are rasterised before recognition.
     */
    private const PDF_MIME_TYPE = 'application/pdf';

    /**
     * Document kinds this layer understands.
     */
    private const KIND_IMAGE = 'image';

    private const KIND_PDF = 'pdf';

    /**
     * Resolution used to rasterise a PDF page, in DPI.
     *
     * 300 DPI is the smallest value that reliably reads receipt-sized type: at ImageMagick's
     * default of 72 DPI the measured fixture was illegible, and the accuracy gains above
     * 300 DPI do not pay for the extra pixels.
     */
    private const DEFAULT_PDF_RESOLUTION_DPI = 300;

    /**
     * Range a configured PDF resolution is clamped to, in DPI.
     */
    private const MIN_PDF_RESOLUTION_DPI = 72;

    private const MAX_PDF_RESOLUTION_DPI = 600;

    /**
     * Factor applied by the preprocessed variants to a source image that is too narrow.
     */
    private const UPSCALE_FACTOR = 2;

    /**
     * Images narrower than this (in pixels) are upscaled before recognition.
     */
    private const UPSCALE_THRESHOLD_PX = 1000;

    /**
     * Maximum deskew angle, in degrees.
     */
    private const DESKEW_THRESHOLD_DEGREES = 40;

    /**
     * Hard ceiling on Tesseract invocations per document.
     *
     * The measured matrix is two languages by three preprocessing chains, six runs. The
     * ceiling only bites when someone configures more languages than measured: at roughly
     * 0.3-2.3 s per run on the measured tickets, six runs cost ~2-8 s per import, which a
     * queued job absorbs comfortably but a runaway matrix would not.
     */
    private const MAX_CANDIDATES = 8;

    /**
     * Default overall recognition budget, in seconds, for one document.
     */
    private const DEFAULT_TOTAL_BUDGET_SECONDS = 45;

    /**
     * Words that label a payable amount, matched against ASCII-folded lowercase text.
     */
    private const TOTAL_LABEL_PATTERN = '/\b(total|tot|importe|amount|amt|montant|suma|a\s*pagar|subtotal|sous\s*-?\s*total)\b/u';

    /**
     * A number shaped like money: two decimals, with either separator style.
     */
    private const MONEY_PATTERN = '/\d{1,3}(?:[.,]\d{3})*[.,]\d{2}(?!\d)/u';

    /**
     * Recognise the receipt image and report every usable variant, best first.
     *
     * @param  string  $absolutePath  Absolute path of the derived receipt image.
     * @return list<ReceiptTextData> The recognised variants ordered from best to worst; empty when the layer cannot help.
     */
    public function candidates(string $absolutePath): array
    {
        if (! (bool) config('economy.ocr.enabled', true)) {
            return [];
        }

        $kind = $this->detectDocumentKind($absolutePath);

        if ($kind === null) {
            return [];
        }

        // A digital PDF carries its own text, so it is read before anything is rasterised
        // and parsed exactly once per document — never once per OCR variant.
        $textLayer = $kind === self::KIND_PDF ? $this->textLayerCandidate($absolutePath) : null;

        $binary = $this->resolveBinary();

        if ($binary === null || ! class_exists(Imagick::class)) {
            // Neither the raster nor the OCR can happen, but a digital PDF still has text.
            return $textLayer === null ? [] : [$textLayer];
        }

        $psm = (int) config('economy.ocr.psm', 6);
        $timeout = (int) config('economy.ocr.timeout_seconds', 20);
        $minConfidence = (float) config('economy.ocr.min_confidence', 0);
        $budget = (float) config('economy.ocr.total_timeout_seconds', self::DEFAULT_TOTAL_BUDGET_SECONDS);

        /** @var list<string> $temporaries */
        $temporaries = [];

        /** @var array<string, string> $prepared One preprocessed file per chain, reused across languages. */
        $prepared = [];

        /** @var list<array{data: ReceiptTextData, score: float, order: int}> $ranked */
        $ranked = [];

        $startedAt = microtime(true);

        try {
            // A PDF is rasterised once, up front, and from then on it is just a page image:
            // the same languages and the same preprocessing chains below apply unchanged.
            $sourcePath = $kind === self::KIND_PDF
                ? $this->rasterizePdf($absolutePath, $temporaries)
                : $absolutePath;

            if ($sourcePath === null) {
                // No Ghostscript (or no PDF support, or a corrupt file): OCR cannot run, but a
                // digital PDF must remain readable from its text layer alone.
                return $textLayer === null ? [] : [$textLayer];
            }

            $scale = $this->upscaleFactor($sourcePath);
            $matrix = $this->matrix($scale);

            foreach ($matrix as $order => $step) {
                if ($budget > 0 && microtime(true) - $startedAt >= $budget) {
                    // A slow ticket must not hold the import: keep the candidates already
                    // recognised and let the parser decide with those.
                    Log::warning('Receipt OCR stopped after exhausting its time budget', [
                        'attempted' => $order,
                        'planned' => count($matrix),
                    ]);

                    break;
                }

                try {
                    $path = $prepared[$step['name']] ??= $this->prepareVariant($sourcePath, $step['chain'], $scale, $temporaries);
                } catch (Throwable) {
                    continue;
                }

                $recognized = $this->readText($binary, $path, $step['language'], $psm, $timeout);

                if ($recognized === null) {
                    continue;
                }

                [$text, $confidence] = $recognized;

                if (trim($text) === '') {
                    // adaptiveThresholdImage() can return an empty page: never let an
                    // empty variant win over a variant that actually saw the total.
                    continue;
                }

                if ($minConfidence > 0 && $confidence !== null && $confidence < $minConfidence) {
                    continue;
                }

                $ranked[] = [
                    'data' => new ReceiptTextData(
                        text: $text,
                        engine: 'tesseract',
                        variant: $step['label'],
                        confidence: $confidence,
                        language: $step['language'],
                    ),
                    'score' => $this->scoreCandidate($text),
                    'order' => $order,
                ];
            }
        } finally {
            foreach ($temporaries as $temporary) {
                if (is_file($temporary)) {
                    @unlink($temporary);
                }
            }
        }

        usort($ranked, fn (array $left, array $right): int => $right['score'] <=> $left['score']
            ?: $left['order'] <=> $right['order']);

        /** @var list<ReceiptTextData> $candidates */
        $candidates = array_map(static fn (array $entry): ReceiptTextData => $entry['data'], $ranked);

        if ($textLayer !== null) {
            // Always first, and not part of the score ranking: the caller keeps the first
            // candidate the parser can read, and the text layer is the exact reading. An OCR
            // variant that merely looks like a total line must never outrank it, and a text
            // layer without an amount simply lets the parser move on to the OCR variants.
            array_unshift($candidates, $textLayer);
        }

        return $candidates;
    }

    /**
     * Read the embedded text layer of a PDF, when the document has one.
     *
     * A digital PDF states its own text, so this reading is exact where OCR on the same page
     * introduces errors: measured on the generated receipt fixture, the text layer returned
     * `TOTAL TTC 28,84` verbatim, while every OCR variant of the rasterised page had to be
     * tried to find it. A scanned PDF has no text layer at all and yields zero characters,
     * which is why this can never replace the raster path — the two are a cascade.
     *
     * Both the library and the parse are optional at runtime. The dependency is required by
     * this application, but the offline layer must keep working when it is absent, so the
     * missing class and a parser that throws on a malformed document are the same outcome as
     * an empty text layer: null, and the raster path answers instead.
     *
     * Only the first page is read, mirroring the raster path: a ticket is one page, and a
     * long upload must not turn into a long extraction.
     *
     * @param  string  $path  Absolute path of the PDF document.
     * @return ReceiptTextData|null The text layer as a candidate, or null when there is no usable text layer.
     */
    private function textLayerCandidate(string $path): ?ReceiptTextData
    {
        if (! (bool) config('economy.ocr.pdf.text_layer', true)) {
            return null;
        }

        if (! class_exists(Parser::class)) {
            // The library is optional at runtime: without it the layer degrades to the raster
            // path it had before text layers were read, instead of breaking the import.
            return null;
        }

        try {
            $text = trim((new Parser)->parseFile($path)->getText(1));
        } catch (Throwable $exception) {
            // No paths and no user data in the log line: only the failure class.
            Log::warning('Receipt text layer could not be read', ['reason' => $exception::class]);

            return null;
        }

        if (preg_match('/\S/u', $text) !== 1) {
            // A scanned document, or a page whose text is only whitespace: nothing to gain.
            return null;
        }

        return new ReceiptTextData(
            text: $text,
            engine: 'pdfparser',
            variant: 'text_layer',
            confidence: null,
            language: null,
        );
    }

    /**
     * Read the receipt text, keeping only the best ranked candidate.
     *
     * @param  string  $absolutePath  Absolute path of the derived receipt image.
     * @return ReceiptTextData|null The best recognised text, or null when the layer cannot help.
     */
    public function extract(string $absolutePath): ?ReceiptTextData
    {
        return $this->candidates($absolutePath)[0] ?? null;
    }

    /**
     * Score one recognised variant, higher is better.
     *
     * The score answers only one cheap question — does this text show a payable amount in a
     * shape the parser can read — because the extractor must not parse. Length and engine
     * confidence are deliberately absent: measured on the real tickets, the longest and most
     * confident variants were the ones that misread the total as `155,00`, so they cannot be
     * allowed to outrank a shorter text that carries the real one.
     *
     * @param  string  $text  The recognised text.
     * @return float The variant score.
     */
    public function scoreCandidate(string $text): float
    {
        $score = 0.0;

        if ($this->hasTotalAmountEvidence($text)) {
            $score += 100.0;
        }

        if (preg_match(self::MONEY_PATTERN, $text) === 1) {
            $score += 50.0;
        }

        if (preg_match('/\p{L}{3,}/u', $text) === 1) {
            $score += 10.0;
        }

        return $score;
    }

    /**
     * Decide whether any line pairs a total label with a money-shaped amount.
     *
     * This mirrors the one thing the parser needs, without parsing: a line such as
     * `TOTAL TTE2 135,00 [` counts, a page of prose with an unrelated `856,00` does not.
     *
     * @param  string  $text  The recognised text.
     */
    private function hasTotalAmountEvidence(string $text): bool
    {
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $normalized = mb_strtolower(Str::ascii($line));

            if (preg_match(self::TOTAL_LABEL_PATTERN, $normalized) === 1
                && preg_match(self::MONEY_PATTERN, $line) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * The enabled preprocessing variants, keyed by their configuration name.
     *
     * @return array<string, string|null> Variant name mapped to its preprocessing chain (null = raw image).
     */
    private function variants(): array
    {
        $variants = [];

        if ((bool) config('economy.ocr.variants.raw', true)) {
            $variants['raw'] = null;
        }

        if ((bool) config('economy.ocr.variants.grayscale', true)) {
            $variants['grayscale'] = 'grayscale';
        }

        if ((bool) config('economy.ocr.variants.adaptive', true)) {
            $variants['adaptive'] = 'adaptive';
        }

        return $variants;
    }

    /**
     * Build the variant matrix: every configured language against every preprocessing chain.
     *
     * Order is the priority: languages first in their configured order, then the
     * preprocessing chains in their configured order. It is the tie-break when two
     * candidates score the same, and it encodes the measurement — `spa` before `eng`
     * because the Spanish reading recovered the total where the English one misread it, and
     * the raw image before the preprocessed chains because preprocessing costs more and won
     * on the same tickets less often.
     *
     * @param  int  $scale  The upscale factor the preprocessed chains will apply.
     * @return list<array{name: string, language: string, chain: string|null, label: string}> The planned runs, in priority order.
     */
    private function matrix(int $scale): array
    {
        $matrix = [];

        foreach ($this->languages() as $language) {
            foreach ($this->variants() as $name => $chain) {
                $matrix[] = [
                    'name' => $name,
                    'language' => $language,
                    'chain' => $chain,
                    'label' => $this->variantLabel($name, $chain === null ? 1 : $scale),
                ];
            }
        }

        return array_slice($matrix, 0, self::MAX_CANDIDATES);
    }

    /**
     * The diagnostic name of one variant, including the scale it was read at.
     *
     * @param  string  $name  The configuration name of the preprocessing chain.
     * @param  int  $scale  The upscale factor that was applied.
     */
    private function variantLabel(string $name, int $scale): string
    {
        return match ($name) {
            'raw' => 'raw_1x',
            'grayscale' => "grayscale_{$scale}x",
            'adaptive' => "grayscale_{$scale}x_deskew_adaptive",
            default => "{$name}_{$scale}x",
        };
    }

    /**
     * Rasterise the first page of a PDF into a temporary PNG image.
     *
     * The result is a normal page image, so the caller feeds it into the same variant matrix
     * as an uploaded image. Rasterising happens once per document, before the matrix, because
     * Ghostscript is far more expensive than a PNG read.
     *
     * Every reason the rasterisation cannot happen — no Ghostscript, an Imagick built without
     * PDF support, a corrupt file, a document with no pages — is the same outcome for the
     * caller: null, so the import falls back to the AI path instead of failing.
     *
     * @param  string  $path  Absolute path of the PDF document.
     * @param  list<string>  $temporaries  Accumulator of files that must be removed afterwards.
     * @return string|null The absolute path of the rendered page, or null when it cannot be rendered.
     */
    private function rasterizePdf(string $path, array &$temporaries): ?string
    {
        if ($this->resolveExecutable((string) config('economy.ocr.pdf.ghostscript_binary', 'gs')) === null) {
            Log::warning('Receipt OCR cannot rasterise the PDF: Ghostscript is not installed');

            return null;
        }

        $resolution = $this->pdfResolution();
        $image = new Imagick;

        if ($image->queryFormats('PDF') === []) {
            Log::warning('Receipt OCR cannot rasterise the PDF: Imagick has no PDF support');

            return null;
        }

        try {
            // The resolution must be set BEFORE the file is read: a PDF page has no pixels of
            // its own, so ImageMagick renders it at the resolution known when it reads it, and
            // its default of 72 DPI makes receipt type too small to recognise.
            $image->setResolution($resolution, $resolution);

            // Only the first page: a ticket is one page, and a long PDF must not be recognised
            // page by page. ImageMagick forwards the scene selection to Ghostscript as the
            // first/last page pair, so the other pages are never even rendered.
            $image->readImage($path.'[0]');
            $image->setIteratorIndex(0);

            if ($image->getNumberImages() < 1) {
                return null;
            }

            // A rendered text page needs no colour: greyscale keeps the raster small, and the
            // preprocessing chains below convert to greyscale anyway.
            $image->setImageColorspace(Imagick::COLORSPACE_GRAY);
            $image->setImageFormat('png');

            $temporary = $this->temporaryPath();
            $temporaries[] = $temporary;
            $image->writeImage($temporary);

            return $temporary;
        } catch (Throwable $exception) {
            // No paths and no user data in the log line: only the failure class. A corrupt
            // upload must never turn into a failed import.
            Log::warning('Receipt OCR could not rasterise the PDF', ['reason' => $exception::class]);

            return null;
        } finally {
            $image->clear();
        }
    }

    /**
     * Resolution used to render a PDF page, clamped to a sane range.
     *
     * 72 DPI is the value that breaks recognition, so it is the floor rather than a value we
     * would ever choose; above 600 DPI the extra pixels only cost time.
     */
    private function pdfResolution(): int
    {
        $configured = (int) config('economy.ocr.pdf.dpi', self::DEFAULT_PDF_RESOLUTION_DPI);

        return max(self::MIN_PDF_RESOLUTION_DPI, min(self::MAX_PDF_RESOLUTION_DPI, $configured));
    }

    /**
     * Build one preprocessing variant on disk, or reuse the source for the raw variant.
     *
     * The source is an uploaded image or the page a PDF was rasterised into: by the time this
     * runs the two are indistinguishable, which is what keeps a single OCR path.
     *
     * @param  string  $sourcePath  Absolute path of the source image or rasterised page.
     * @param  string|null  $preprocessing  The preprocessing chain to apply, or null for the raw image.
     * @param  int  $scale  The upscale factor for the preprocessed chains.
     * @param  list<string>  $temporaries  Accumulator of files that must be removed afterwards.
     * @return string The absolute path Tesseract should read.
     */
    private function prepareVariant(string $sourcePath, ?string $preprocessing, int $scale, array &$temporaries): string
    {
        if ($preprocessing === null) {
            return $sourcePath;
        }

        $image = new Imagick($sourcePath);
        $image->setImageColorspace(Imagick::COLORSPACE_GRAY);

        if ($scale > 1) {
            $image->resizeImage(
                $image->getImageWidth() * $scale,
                $image->getImageHeight() * $scale,
                Imagick::FILTER_CUBIC,
                1,
            );
        }

        if ($preprocessing === 'adaptive') {
            try {
                $image->deskewImage(self::DESKEW_THRESHOLD_DEGREES);
            } catch (Throwable) {
                // Deskew is a refinement: a failure leaves the page as it was.
            }

            $image->adaptiveThresholdImage(25, 25, 10);
        }

        $path = $this->temporaryPath();
        $temporaries[] = $path;

        $image->setImageFormat('png');
        $image->writeImage($path);
        $image->clear();

        return $path;
    }

    /**
     * Decide the upscale factor for this document.
     *
     * 3x and 4x made every measured ticket worse, and an image that is already wide has
     * enough pixels for the default character height, so only narrow images are doubled.
     *
     * @param  string  $path  Absolute path of the source image.
     * @return int 2 for a narrow image, 1 when it is already wide enough.
     */
    private function upscaleFactor(string $path): int
    {
        $threshold = (int) config('economy.ocr.upscale_threshold_px', self::UPSCALE_THRESHOLD_PX);

        if ($threshold <= 0) {
            return self::UPSCALE_FACTOR;
        }

        $geometry = @getimagesize($path);
        $width = is_array($geometry) ? (int) ($geometry[0] ?? 0) : 0;

        if ($width <= 0) {
            return 1;
        }

        return $width < $threshold ? self::UPSCALE_FACTOR : 1;
    }

    /**
     * Run Tesseract over one variant and read its text plus mean confidence.
     *
     * The TSV output is preferred because it carries the per-word confidence. When the TSV
     * cannot be reconstructed the plain text output is retried, so a format quirk can never
     * cost a readable ticket.
     *
     * @param  string  $binary  The resolved Tesseract binary.
     * @param  string  $path  Absolute path of the image to recognise.
     * @param  string  $language  The Tesseract language for this variant.
     * @param  int  $psm  The page segmentation mode.
     * @param  int  $timeout  The per-run timeout in seconds.
     * @return array{0: string, 1: float|null}|null The trimmed text and its mean confidence, or null on failure.
     */
    private function readText(string $binary, string $path, string $language, int $psm, int $timeout): ?array
    {
        $tsv = $this->run($binary, $path, $language, $psm, $timeout, 'tsv');

        if ($tsv !== null) {
            [$text, $confidence] = $this->parseTsv($tsv);

            if ($text !== '') {
                return [$text, $confidence];
            }
        }

        $plain = $this->run($binary, $path, $language, $psm, $timeout, 'txt');

        if ($plain === null) {
            return null;
        }

        $plain = trim($plain);

        return $plain === '' ? null : [$plain, null];
    }

    /**
     * Execute one Tesseract invocation, writing the recognised output to stdout.
     *
     * @param  string  $binary  The resolved Tesseract binary.
     * @param  string  $path  Absolute path of the image to recognise.
     * @param  string  $language  The Tesseract language.
     * @param  int  $psm  The page segmentation mode.
     * @param  int  $timeout  The run timeout in seconds.
     * @param  string  $format  The Tesseract output config (`tsv` or `txt`).
     * @return string|null The raw output, or null when the run fails or times out.
     */
    private function run(string $binary, string $path, string $language, int $psm, int $timeout, string $format): ?string
    {
        $process = new Process([$binary, $path, 'stdout', '-l', $language, '--psm', (string) $psm, $format]);
        $process->setTimeout($timeout > 0 ? $timeout : null);

        try {
            $process->run();
        } catch (Throwable $exception) {
            // No paths and no user data in the log line: only the failure class.
            Log::warning('Receipt OCR run failed', [
                'format' => $format,
                'language' => $language,
                'reason' => $exception::class,
            ]);

            return null;
        }

        if (! $process->isSuccessful()) {
            Log::warning('Receipt OCR run was not successful', [
                'format' => $format,
                'language' => $language,
                'exit_code' => $process->getExitCode(),
            ]);

            return null;
        }

        return $process->getOutput();
    }

    /**
     * Rebuild readable lines from Tesseract's TSV output and average the word confidences.
     *
     * @param  string  $tsv  The raw TSV output.
     * @return array{0: string, 1: float|null} The reconstructed text and its mean confidence.
     */
    private function parseTsv(string $tsv): array
    {
        $rows = preg_split('/\R/u', trim($tsv)) ?: [];

        $lines = [];
        $currentKey = null;
        $currentWords = [];
        $confidences = [];

        foreach ($rows as $index => $row) {
            if ($index === 0 || $row === '') {
                continue;
            }

            $columns = explode("\t", $row);

            if (count($columns) < 12 || (int) $columns[0] !== 5) {
                continue;
            }

            $word = trim($columns[11]);
            $confidence = (float) $columns[10];

            if ($word === '' || $confidence < 0) {
                continue;
            }

            $key = $columns[2].':'.$columns[3].':'.$columns[4];

            if ($currentKey !== $key && $currentWords !== []) {
                $lines[] = implode(' ', $currentWords);
                $currentWords = [];
            }

            $currentKey = $key;
            $currentWords[] = $word;
            $confidences[] = $confidence;
        }

        if ($currentWords !== []) {
            $lines[] = implode(' ', $currentWords);
        }

        $text = trim(implode("\n", $lines));
        $mean = $confidences === [] ? null : array_sum($confidences) / count($confidences);

        return [$text, $mean];
    }

    /**
     * Resolve the configured Tesseract binary, degrading to null when it is not installed.
     *
     * @return string|null The executable path, or null when the binary cannot be found.
     */
    private function resolveBinary(): ?string
    {
        $configured = trim((string) config('economy.ocr.binary', 'tesseract'));

        return $configured === '' ? null : $this->resolveExecutable($configured);
    }

    /**
     * Resolve an external executable, either as an absolute path or through PATH.
     *
     * @param  string  $configured  A bare command name or an absolute path.
     * @return string|null The executable path, or null when it cannot be found.
     */
    private function resolveExecutable(string $configured): ?string
    {
        if ($configured === '') {
            return null;
        }

        if (str_contains($configured, '/') || str_contains($configured, '\\')) {
            return is_file($configured) && is_executable($configured) ? $configured : null;
        }

        $searchPath = getenv('PATH');
        $directories = explode(PATH_SEPARATOR, is_string($searchPath) ? $searchPath : '');

        foreach ($directories as $directory) {
            if ($directory === '') {
                continue;
            }

            $candidate = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$configured;

            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Read the configured languages, each one a separate variant.
     *
     * A composite value such as `eng+spa` is still accepted because Tesseract supports it,
     * but it is measured to degrade the total, so the default is a list of single languages.
     *
     * @return list<string> The languages to try, in priority order.
     */
    private function languages(): array
    {
        $configured = config('economy.ocr.languages', ['spa', 'eng']);

        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        $languages = [];

        foreach ((array) $configured as $language) {
            $language = mb_strtolower(trim((string) $language));

            if (preg_match('/^[a-z]{2,3}(?:\+[a-z]{2,3})?$/u', $language) !== 1) {
                continue;
            }

            if (! in_array($language, $languages, true)) {
                $languages[] = $language;
            }
        }

        return $languages === [] ? ['spa', 'eng'] : $languages;
    }

    /**
     * Decide which kind of document the file is, from its content rather than its name.
     *
     * The MIME type is sniffed, so a PDF stored under any name is still recognised as a PDF.
     * The extension only ever corroborates the sniffed type — it can never grant support on
     * its own, because a `.pdf` name over JPEG bytes is a JPEG, not a document to rasterise.
     *
     * @param  string  $path  Absolute path of the candidate file.
     * @return string|null `pdf`, `image`, or null when this layer cannot read the file.
     */
    private function detectDocumentKind(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $mime = $this->detectMimeType($path);

        if ($mime === self::PDF_MIME_TYPE) {
            return self::KIND_PDF;
        }

        if (in_array($mime, self::SUPPORTED_MIME_TYPES, true)) {
            return self::KIND_IMAGE;
        }

        // Last resort for a PDF the sniffer could not name (a missing fileinfo extension,
        // for example): the signature proves the content and the extension corroborates it.
        if ($this->hasPdfSignature($path) && $this->hasExtension($path, 'pdf')) {
            return self::KIND_PDF;
        }

        return null;
    }

    /**
     * Sniff the MIME type of a file, preferring the content over the file name.
     *
     * @param  string  $path  Absolute path of the candidate file.
     * @return string|null The lowercase MIME type, or null when it cannot be determined.
     */
    private function detectMimeType(string $path): ?string
    {
        $sniffed = @mime_content_type($path);
        $sniffed = is_string($sniffed) && $sniffed !== '' ? mb_strtolower($sniffed) : null;

        if ($sniffed !== null && $sniffed !== 'application/octet-stream') {
            return $sniffed;
        }

        // `getimagesize()` is the strongest signal for the image formats this layer reads,
        // and it is what recognised them before PDFs were supported.
        $info = @getimagesize($path);

        if (is_array($info)) {
            $reported = mb_strtolower((string) ($info['mime'] ?? ''));

            if ($reported !== '') {
                return $reported;
            }
        }

        return $sniffed;
    }

    /**
     * Decide whether the file starts with the PDF signature.
     *
     * @param  string  $path  Absolute path of the candidate file.
     */
    private function hasPdfSignature(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            return fread($handle, 5) === '%PDF-';
        } finally {
            fclose($handle);
        }
    }

    /**
     * Decide whether the file carries the given extension, ignoring case.
     *
     * @param  string  $path  Absolute path of the candidate file.
     * @param  string  $extension  The extension to compare against, without the dot.
     */
    private function hasExtension(string $path, string $extension): bool
    {
        return mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)) === $extension;
    }

    /**
     * Reserve a temporary file for a preprocessed variant.
     *
     * The path has no extension on purpose: Imagick writes the PNG format set on the
     * image, and Tesseract sniffs the content instead of the file name.
     */
    private function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'receipt-ocr-');

        if ($path === false) {
            throw new \RuntimeException('Unable to create a temporary file for the OCR variant.');
        }

        return $path;
    }
}
