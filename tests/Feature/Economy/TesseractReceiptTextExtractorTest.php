<?php

namespace Tests\Feature\Economy;

use App\Data\Economy\ReceiptTextData;
use App\Services\Economy\ReceiptTextParser;
use App\Services\Economy\TesseractReceiptTextExtractor;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

/**
 * Covers the degrade-to-null contract of the offline text extractor.
 *
 * The extractor is the free first pass in front of the metered AI analysis, so every
 * environment problem (layer disabled, binary missing, PDF instead of an image, unreadable
 * file) has to come back as null rather than as an exception.
 */
class TesseractReceiptTextExtractorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_it_returns_null_when_the_layer_is_disabled(): void
    {
        $path = $this->receiptImagePath();
        config(['economy.ocr.enabled' => false]);

        $this->assertNull($this->extractor()->extract($path));
        $this->assertSame([], $this->extractor()->candidates($path));
    }

    public function test_it_returns_null_when_the_binary_is_not_installed(): void
    {
        $path = $this->receiptImagePath();
        config(['economy.ocr.binary' => '/definitely/not/installed/tesseract']);

        $this->assertNull($this->extractor()->extract($path));
        $this->assertSame([], $this->extractor()->candidates($path));
    }

    public function test_it_returns_null_for_a_document_that_is_not_an_image(): void
    {
        Storage::disk('local')->put('economy/documents/ticket.pdf', "%PDF-1.4\n1 0 obj\n");

        $this->assertNull(
            $this->extractor()->extract($this->storedPath('economy/documents/ticket.pdf')),
            'PDF documents are out of scope for v1 and must fall back to the AI path.'
        );
    }

    public function test_it_returns_null_for_a_missing_file(): void
    {
        $this->assertNull($this->extractor()->extract($this->storedPath('economy/documents/missing.jpg')));
    }

    public function test_it_returns_null_when_no_variant_produces_text(): void
    {
        // A blank white page is a supported image the engine cannot read.
        Storage::disk('local')->put('economy/documents/blank.jpg', $this->blankImageBytes());
        config(['economy.ocr.min_confidence' => 0]);

        $result = $this->extractor()->extract($this->storedPath('economy/documents/blank.jpg'));

        $this->assertNull($result);
    }

    public function test_it_recognises_the_total_of_a_receipt_image(): void
    {
        $this->skipWhenTesseractIsNotInstalled();

        $path = $this->receiptImagePath();

        $before = $this->ocrTemporaryFiles();

        $result = $this->extractor()->extract($path);

        $this->assertInstanceOf(ReceiptTextData::class, $result);
        $this->assertSame('tesseract', $result->engine);
        $this->assertNotSame('', $result->variant, 'The winning variant is reported for diagnostics.');
        $this->assertStringContainsString('135,00', $result->text);
        $this->assertStringContainsStringIgnoringCase('total', $result->text);

        if ($result->confidence !== null) {
            $this->assertGreaterThanOrEqual(0.0, $result->confidence);
            $this->assertLessThanOrEqual(100.0, $result->confidence);
        }

        $this->assertSame(
            $before,
            $this->ocrTemporaryFiles(),
            'Preprocessed variants must be cleaned up, whatever the outcome.'
        );
    }

    public function test_it_reports_every_usable_variant_ordered_best_first(): void
    {
        $this->skipWhenTesseractIsNotInstalled();

        $path = $this->receiptImagePath();
        $extractor = $this->extractor();

        $candidates = $extractor->candidates($path);

        $this->assertNotSame([], $candidates, 'A readable receipt must yield at least one candidate.');

        $seen = [];
        $previousScore = INF;

        foreach ($candidates as $candidate) {
            $this->assertInstanceOf(ReceiptTextData::class, $candidate);
            $this->assertSame('tesseract', $candidate->engine);
            $this->assertNotSame('', trim($candidate->text), 'Empty variants are discarded, never ranked.');
            $this->assertContains($candidate->language, ['spa', 'eng'], 'Every candidate reports the language it was read in.');
            $this->assertNotSame('', (string) $candidate->variant, 'Every candidate reports its preprocessing variant.');

            $score = $extractor->scoreCandidate($candidate->text);
            $this->assertLessThanOrEqual($previousScore, $score, 'Candidates are ordered from best to worst.');
            $previousScore = $score;

            $key = $candidate->language.':'.$candidate->variant;
            $this->assertArrayNotHasKey($key, $seen, 'Each language and preprocessing combination is read at most once.');
            $seen[$key] = true;
        }

        // Both configured languages must have been attempted: a language is a variant dimension,
        // not part of a composite language string.
        $this->assertSame(['eng', 'spa'], collect($candidates)->pluck('language')->unique()->sort()->values()->all());

        // The decision belongs to the parser: the first candidate it can read wins.
        $parser = new ReceiptTextParser;
        $firstParsed = null;

        foreach ($candidates as $candidate) {
            $firstParsed = $parser->parse($candidate->text);

            if ($firstParsed !== null) {
                break;
            }
        }

        $this->assertNotNull($firstParsed, 'At least one candidate must carry a readable amount.');
        $this->assertSame(13500, $firstParsed->amount_minor);

        // The single-candidate shortcut stays the first candidate.
        $this->assertSame($candidates[0]->text, $extractor->extract($path)?->text);
    }

    public function test_it_does_not_upscale_an_image_that_is_already_wide(): void
    {
        $this->skipWhenTesseractIsNotInstalled();

        Storage::disk('local')->put('economy/documents/wide.jpg', $this->receiptImageBytes(1100));

        $candidates = $this->extractor()->candidates($this->storedPath('economy/documents/wide.jpg'));

        $this->assertNotSame([], $candidates);

        foreach ($candidates as $candidate) {
            $this->assertStringNotContainsString(
                '2x',
                (string) $candidate->variant,
                'A 1100 px wide image is above the upscale threshold and must not be enlarged.'
            );
        }
    }

    public function test_the_ranking_never_prefers_volume_over_evidence_of_a_total(): void
    {
        $extractor = $this->extractor();

        $verboseWithoutTotal = str_repeat('MERCI DE VOTRE VISITE AVEC NOS MEILLEURS SALUTATIONS ', 20);
        $shortWithTotal = 'TOTAL TTC 135,00';

        $this->assertGreaterThan(
            $extractor->scoreCandidate($verboseWithoutTotal),
            $extractor->scoreCandidate($shortWithTotal),
            'A ten times longer text without a readable total cannot outrank a short readable one.'
        );

        $this->assertSame(
            $extractor->scoreCandidate($shortWithTotal),
            $extractor->scoreCandidate(str_repeat("prose illisible\n", 200).$shortWithTotal),
            'Length must not change the score: only the evidence of a total does.'
        );
    }

    /**
     * Resolve the extractor under test.
     */
    private function extractor(): TesseractReceiptTextExtractor
    {
        return new TesseractReceiptTextExtractor;
    }

    /**
     * Absolute path of a stored fake receipt image.
     */
    private function storedPath(string $relativePath): string
    {
        return Storage::disk('local')->path($relativePath);
    }

    /**
     * Store a readable receipt image and return its absolute path.
     */
    private function receiptImagePath(): string
    {
        Storage::disk('local')->put('economy/documents/ticket.jpg', $this->receiptImageBytes());

        return $this->storedPath('economy/documents/ticket.jpg');
    }

    /**
     * Render a small but realistic receipt with GD, so the test does not depend on a
     * binary fixture and the engine has real glyphs to read.
     */
    private function receiptImageBytes(int $width = 900): string
    {
        $lines = [
            'AVENUE DES CHAMPS',
            '12 RUE DE PARIS',
            'TEL 01 23 45 67 89',
            'yoTaL 77 135,00',
            'TOTAL TTC 135,00',
            'date: 22/9/16 12:07:28',
        ];

        $image = imagecreatetruecolor($width, 420);
        imagefilledrectangle($image, 0, 0, $width, 420, imagecolorallocate($image, 252, 250, 245));
        $ink = imagecolorallocate($image, 30, 30, 30);

        $y = 30;

        foreach ($lines as $line) {
            imagestring($image, 5, 40, $y, $line, $ink);
            $y += 50;
        }

        ob_start();
        imagejpeg($image, null, 92);
        $bytes = (string) ob_get_clean();

        return $bytes;
    }

    /**
     * Render a blank page: a valid image with nothing to read.
     */
    private function blankImageBytes(): string
    {
        $image = imagecreatetruecolor(600, 300);
        imagefilledrectangle($image, 0, 0, 600, 300, imagecolorallocate($image, 255, 255, 255));

        ob_start();
        imagejpeg($image, null, 92);
        $bytes = (string) ob_get_clean();

        return $bytes;
    }

    /**
     * List the temporary files the extractor may leave behind.
     *
     * @return list<string>
     */
    private function ocrTemporaryFiles(): array
    {
        $files = glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'receipt-ocr-*') ?: [];
        sort($files);

        return array_values($files);
    }

    /**
     * Skip only the recognition test when the engine is not available.
     */
    private function skipWhenTesseractIsNotInstalled(): void
    {
        $probe = new Process(['tesseract', '--version']);

        try {
            $probe->run();
        } catch (Throwable) {
            $this->markTestSkipped('Tesseract is not available in this environment.');
        }

        if (! $probe->isSuccessful()) {
            $this->markTestSkipped('Tesseract is not available in this environment.');
        }
    }
}
