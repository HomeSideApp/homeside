<?php

namespace App\Services\Economy;

use App\Contracts\ReceiptTextExtractor;
use App\Data\Economy\ReceiptTextData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Imagick;
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
 */
final class TesseractReceiptTextExtractor implements ReceiptTextExtractor
{
    /**
     * MIME types this version can rasterise. PDF documents are deferred to a later phase.
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

        $binary = $this->resolveBinary();

        if ($binary === null || ! class_exists(Imagick::class)) {
            return [];
        }

        if (! $this->isSupportedImage($absolutePath)) {
            return [];
        }

        $psm = (int) config('economy.ocr.psm', 6);
        $timeout = (int) config('economy.ocr.timeout_seconds', 20);
        $minConfidence = (float) config('economy.ocr.min_confidence', 0);
        $budget = (float) config('economy.ocr.total_timeout_seconds', self::DEFAULT_TOTAL_BUDGET_SECONDS);
        $scale = $this->upscaleFactor($absolutePath);
        $matrix = $this->matrix($scale);

        /** @var list<string> $temporaries */
        $temporaries = [];

        /** @var array<string, string> $prepared One preprocessed file per chain, reused across languages. */
        $prepared = [];

        /** @var list<array{data: ReceiptTextData, score: float, order: int}> $ranked */
        $ranked = [];

        $startedAt = microtime(true);

        try {
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
                    $path = $prepared[$step['name']] ??= $this->prepareVariant($absolutePath, $step['chain'], $scale, $temporaries);
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

        return array_map(static fn (array $entry): ReceiptTextData => $entry['data'], $ranked);
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
     * Build one preprocessing variant on disk, or reuse the source for the raw variant.
     *
     * @param  string  $sourcePath  Absolute path of the source image.
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
     * Decide whether the file is an image this version can recognise.
     *
     * The check is content-based, so a PDF upload (still accepted by the import) degrades
     * to an empty result here instead of being handed to Tesseract.
     *
     * @param  string  $path  Absolute path of the candidate file.
     */
    private function isSupportedImage(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        $info = @getimagesize($path);

        if ($info === false) {
            return false;
        }

        return in_array(strtolower((string) ($info['mime'] ?? '')), self::SUPPORTED_MIME_TYPES, true);
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
