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
 * environment problem (layer disabled, binary missing, corrupt PDF, unreadable file) has to
 * come back as null rather than as an exception.
 *
 * PDF tickets are a cascade, not a choice of engine. A digital PDF states its own text, so
 * the first page is read with `smalot/pdfparser` and that exact reading is put in front of
 * every OCR variant; a scanned PDF has no text layer at all, so the page is rasterised with
 * Imagick at 300 DPI and fed into the same variant matrix as an image. The fixtures below
 * are generated here rather than shipped, and there are two of them on purpose: a vector PDF
 * with a real text layer, and an image-only PDF that only the raster path can read.
 *
 * The vector fixture also proves the resolution matters for the raster path: an 8 pt line is
 * 8 px tall on a page rendered at the ImageMagick default of 72 DPI (measured: `TOTAL TTC
 * 28,84` becomes `TOTAL TI 28,54`, a wrong amount) and 33 px tall at the 300 DPI the extractor
 * sets (read exactly).
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

    public function test_it_returns_null_for_a_corrupt_pdf(): void
    {
        Storage::disk('local')->put('economy/documents/broken.pdf', "%PDF-1.4\n1 0 obj\n");

        $path = $this->storedPath('economy/documents/broken.pdf');
        $before = $this->ocrTemporaryFiles();

        $this->assertNull(
            $this->extractor()->extract($path),
            'A PDF Ghostscript cannot read must fall back to the AI path instead of throwing.'
        );

        $this->assertSame(
            $before,
            $this->ocrTemporaryFiles(),
            'A failed rasterisation must not leave its temporary files behind.'
        );
    }

    public function test_it_returns_null_for_a_pdf_without_pages(): void
    {
        Storage::disk('local')->put('economy/documents/empty.pdf', $this->pdfBytes([]));

        $this->assertNull(
            $this->extractor()->extract($this->storedPath('economy/documents/empty.pdf')),
            'A PDF with no pages has nothing to recognise and must not throw.'
        );
    }

    public function test_it_returns_null_for_a_pdf_when_ghostscript_is_not_installed(): void
    {
        Storage::disk('local')->put('economy/documents/scan.pdf', $this->imageOnlyPdfBytes());
        config(['economy.ocr.pdf.ghostscript_binary' => '/definitely/not/installed/gs']);

        $path = $this->storedPath('economy/documents/scan.pdf');

        $this->assertNull(
            $this->extractor()->extract($path),
            'A scanned PDF without Ghostscript has no text layer and cannot be rasterised.'
        );
        $this->assertSame([], $this->extractor()->candidates($path));
    }

    public function test_it_recognises_the_total_of_a_pdf_ticket(): void
    {
        $this->skipWhenTesseractIsNotInstalled();
        $this->skipWhenPdfRasterisationIsUnavailable();

        Storage::disk('local')->put('economy/documents/ticket.pdf', $this->pdfBytes([$this->ticketLines()]));

        $path = $this->storedPath('economy/documents/ticket.pdf');
        $before = $this->ocrTemporaryFiles();
        $extractor = $this->extractor();

        $candidates = $extractor->candidates($path);

        $this->assertNotSame([], $candidates, 'A text PDF must be rasterised and recognised offline.');

        $parsed = null;

        foreach ($candidates as $candidate) {
            $parsed = (new ReceiptTextParser)->parse($candidate->text);

            if ($parsed !== null) {
                break;
            }
        }

        $this->assertNotNull($parsed, 'The rasterised PDF must yield a parseable amount.');
        $this->assertSame(2884, $parsed->amount_minor);
        $this->assertNotNull($extractor->extract($path), 'The single-candidate shortcut works for PDFs too.');

        $this->assertSame(
            $before,
            $this->ocrTemporaryFiles(),
            'The rasterised page and the preprocessed variants must be cleaned up, whatever the outcome.'
        );
    }

    public function test_it_rasterises_only_the_first_page_of_a_pdf(): void
    {
        $this->skipWhenTesseractIsNotInstalled();
        $this->skipWhenPdfRasterisationIsUnavailable();

        Storage::disk('local')->put('economy/documents/multi.pdf', $this->pdfBytes([
            $this->ticketLines(),
            ['SECOND PAGE', 'TOTAL TTC 999,00', 'IVA 21%'],
        ]));

        $candidates = $this->extractor()->candidates($this->storedPath('economy/documents/multi.pdf'));

        $this->assertNotSame([], $candidates);

        $parsed = null;

        foreach ($candidates as $candidate) {
            $this->assertStringNotContainsString(
                '999',
                $candidate->text,
                'A ticket is one page: later pages must never be rasterised or recognised.'
            );

            $parsed ??= (new ReceiptTextParser)->parse($candidate->text);
        }

        $this->assertNotNull($parsed);
        $this->assertSame(2884, $parsed->amount_minor, 'The amount comes from the first page.');
    }

    public function test_it_detects_a_pdf_by_content_and_not_only_by_extension(): void
    {
        $this->skipWhenTesseractIsNotInstalled();
        $this->skipWhenPdfRasterisationIsUnavailable();

        Storage::disk('local')->put('economy/documents/ticket.bin', $this->pdfBytes([$this->ticketLines()]));

        $this->assertNotSame(
            [],
            $this->extractor()->candidates($this->storedPath('economy/documents/ticket.bin')),
            'A PDF stored without a .pdf extension must still be recognised, because the type is sniffed.'
        );
    }

    public function test_it_reads_the_text_layer_of_a_digital_pdf_before_rasterising(): void
    {
        Storage::disk('local')->put('economy/documents/digital.pdf', $this->pdfBytes([$this->ticketLines()]));

        $candidates = $this->extractor()->candidates($this->storedPath('economy/documents/digital.pdf'));

        $this->assertNotSame([], $candidates, 'A digital PDF must be read offline, from its text layer.');

        $textLayer = $candidates[0];

        $this->assertSame('pdfparser', $textLayer->engine, 'The text layer is an exact reading, not OCR.');
        $this->assertSame('text_layer', $textLayer->variant);
        $this->assertNull($textLayer->language, 'Reading a text layer has no OCR language.');
        $this->assertNull($textLayer->confidence, 'A text layer reports no OCR confidence.');
        $this->assertStringContainsString('TOTAL TTC 28,84', $textLayer->text, 'The text layer is verbatim.');

        // The cascade must be enough on its own: the parser reads the first candidate and
        // stops, so the exact text wins before any OCR variant is consulted.
        $parsed = (new ReceiptTextParser)->parse($textLayer->text);

        $this->assertNotNull($parsed, 'The text layer of a digital ticket carries the amount.');
        $this->assertSame(2884, $parsed->amount_minor);
    }

    public function test_a_scanned_pdf_has_no_text_layer_and_is_recognised_by_rasterising(): void
    {
        $this->skipWhenTesseractIsNotInstalled();
        $this->skipWhenPdfRasterisationIsUnavailable();

        Storage::disk('local')->put('economy/documents/scan.pdf', $this->imageOnlyPdfBytes());

        $candidates = $this->extractor()->candidates($this->storedPath('economy/documents/scan.pdf'));

        $this->assertNotSame([], $candidates, 'A scanned PDF has no text layer, so OCR must resolve it.');

        foreach ($candidates as $candidate) {
            $this->assertSame('tesseract', $candidate->engine);
            $this->assertNotSame('text_layer', $candidate->variant, 'A page with no text has no text layer to report.');
        }

        $parsed = null;

        foreach ($candidates as $candidate) {
            $parsed = (new ReceiptTextParser)->parse($candidate->text);

            if ($parsed !== null) {
                break;
            }
        }

        $this->assertNotNull($parsed, 'The rasterised scan must still yield the amount.');
        $this->assertSame(2884, $parsed->amount_minor);
    }

    public function test_a_text_layer_without_an_amount_does_not_stop_the_ocr_variants(): void
    {
        $this->skipWhenTesseractIsNotInstalled();
        $this->skipWhenPdfRasterisationIsUnavailable();

        Storage::disk('local')->put('economy/documents/prose.pdf', $this->pdfBytes([[
            'GRACIAS POR SU COMPRA',
            'CONSERVE SU TICKET',
        ]]));

        $candidates = $this->extractor()->candidates($this->storedPath('economy/documents/prose.pdf'));

        $this->assertNotSame([], $candidates);
        $this->assertSame('text_layer', $candidates[0]->variant, 'The text layer is still the first candidate.');
        $this->assertNull(
            (new ReceiptTextParser)->parse($candidates[0]->text),
            'A text layer without an amount yields no result; the cascade must continue.'
        );
        $this->assertGreaterThan(
            1,
            count($candidates),
            'An unhelpful text layer must not cut the cascade: the OCR variants are still tried.'
        );
    }

    public function test_a_digital_pdf_is_readable_when_ghostscript_is_not_installed(): void
    {
        Storage::disk('local')->put('economy/documents/ticket.pdf', $this->pdfBytes([$this->ticketLines()]));
        config(['economy.ocr.pdf.ghostscript_binary' => '/definitely/not/installed/gs']);

        $path = $this->storedPath('economy/documents/ticket.pdf');
        $extractor = $this->extractor();
        $candidates = $extractor->candidates($path);

        $this->assertCount(1, $candidates, 'Without Ghostscript there is no raster, but the text layer remains.');
        $this->assertSame('pdfparser', $candidates[0]->engine);
        $this->assertSame('text_layer', $candidates[0]->variant);

        $parsed = (new ReceiptTextParser)->parse($candidates[0]->text);

        $this->assertNotNull($parsed);
        $this->assertSame(2884, $parsed->amount_minor);
        $this->assertSame($candidates[0]->text, $extractor->extract($path)?->text);
    }

    public function test_it_falls_back_to_rasterising_when_the_text_layer_is_disabled(): void
    {
        $this->skipWhenTesseractIsNotInstalled();
        $this->skipWhenPdfRasterisationIsUnavailable();

        Storage::disk('local')->put('economy/documents/digital.pdf', $this->pdfBytes([$this->ticketLines()]));
        config(['economy.ocr.pdf.text_layer' => false]);

        $candidates = $this->extractor()->candidates($this->storedPath('economy/documents/digital.pdf'));

        $this->assertNotSame([], $candidates);

        foreach ($candidates as $candidate) {
            $this->assertNotSame(
                'text_layer',
                $candidate->variant,
                'With the text layer disabled, and with the library absent, the layer falls back to the raster.'
            );
        }
    }

    public function test_the_image_path_never_reports_a_text_layer(): void
    {
        $this->skipWhenTesseractIsNotInstalled();

        $candidates = $this->extractor()->candidates($this->receiptImagePath());

        $this->assertNotSame([], $candidates);

        foreach ($candidates as $candidate) {
            $this->assertSame('tesseract', $candidate->engine);
            $this->assertNotSame(
                'text_layer',
                $candidate->variant,
                'Only a PDF has a text layer; an image is always recognised by the OCR matrix.'
            );
        }
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
     * The lines of the receipt used as the PDF fixture.
     *
     * @return list<string>
     */
    private function ticketLines(): array
    {
        return [
            'AVENUE DES CHAMPS',
            '12 RUE DE PARIS',
            'TOTAL TTC 28,84',
            'IVA 21%',
            'date: 22/10/2026',
        ];
    }

    /**
     * Build a vector PDF, one text block per page, with no external tool at generation time.
     *
     * The page box is deliberately receipt sized (283x450 pt, the same ratio as the ticket
     * images the import receives) and the font is 8 pt, so the fixture is illegible at the
     * ImageMagick default resolution of 72 DPI and readable at the 300 DPI the extractor sets.
     *
     * @param  list<list<string>>  $pages  The lines of each page, in order.
     * @return string The PDF bytes.
     */
    private function pdfBytes(array $pages, int $fontSize = 8): string
    {
        $pageCount = count($pages);
        $fontObjectId = 3 + $pageCount * 2;
        $kids = [];
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
        ];

        foreach ($pages as $index => $lines) {
            $pageObjectId = 3 + $index * 2;
            $kids[] = $pageObjectId.' 0 R';
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pageCount.' >>';
        $objects[$fontObjectId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';

        foreach ($pages as $index => $lines) {
            $pageObjectId = 3 + $index * 2;
            $contentObjectId = $pageObjectId + 1;

            $objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 283 450]'
                .' /Resources << /Font << /F1 '.$fontObjectId.' 0 R >> >>'
                .' /Contents '.$contentObjectId.' 0 R >>';

            $stream = 'BT /F1 '.$fontSize.' Tf 20 420 Td 14 TL'."\n";

            foreach ($lines as $line) {
                $stream .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line).") Tj T*\n";
            }

            $stream .= "ET\n";
            $objects[$contentObjectId] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream';
        }

        ksort($objects);

        return $this->pdfDocument($objects);
    }

    /**
     * Build an image-only PDF: a raster page with no text layer at all.
     *
     * This is the scanned ticket the cascade must still handle. The page holds a single
     * embedded JPEG drawn over the whole receipt-sized page, so `PdfParser` finds no text and
     * only the rasterised OCR path can read the amount. Nothing external is invoked at
     * generation time: the JPEG is written inline as a `DCTDecode` stream.
     *
     * @return string The PDF bytes.
     */
    private function imageOnlyPdfBytes(int $width = 566, int $height = 900): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 252, 250, 245));
        $ink = imagecolorallocate($image, 30, 30, 30);

        $y = 60;

        foreach ($this->ticketLines() as $line) {
            imagestring($image, 5, 40, $y, $line, $ink);
            $y += 80;
        }

        ob_start();
        imagejpeg($image, null, 92);
        $jpeg = (string) ob_get_clean();

        $content = 'q 283 0 0 450 0 0 cm /Im1 Do Q';

        return $this->pdfDocument([
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 283 450]'
                .' /Resources << /XObject << /Im1 5 0 R >> >> /Contents 4 0 R >>',
            4 => '<< /Length '.strlen($content).">>\nstream\n".$content.'\nendstream',
            5 => '<< /Type /XObject /Subtype /Image /Width '.$width.' /Height '.$height
                .' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode'
                .' /Length '.strlen($jpeg).">>\nstream\n".$jpeg."\nendstream",
        ]);
    }

    /**
     * Serialise PDF objects, keyed by object id, into a complete PDF document.
     *
     * @param  array<int, string>  $objects  The object bodies, keyed by object id.
     * @return string The PDF bytes.
     */
    private function pdfDocument(array $objects): string
    {
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $objectId => $body) {
            $offsets[$objectId] = strlen($pdf);
            $pdf .= $objectId." 0 obj\n".$body."\nendobj\n";
        }

        $maxObjectId = max(array_keys($objects));
        $startXref = strlen($pdf);
        $pdf .= "xref\n0 ".($maxObjectId + 1)."\n0000000000 65535 f \n";

        for ($objectId = 1; $objectId <= $maxObjectId; $objectId++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectId] ?? 0);
        }

        return $pdf."trailer\n<< /Size ".($maxObjectId + 1)." /Root 1 0 R >>\nstartxref\n".$startXref."\n%%EOF\n";
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
     * Skip the PDF recognition tests when the page cannot be rasterised here.
     */
    private function skipWhenPdfRasterisationIsUnavailable(): void
    {
        if (! in_array('PDF', (new \Imagick)->queryFormats('PDF'), true)) {
            $this->markTestSkipped('Imagick cannot read PDF in this environment.');
        }

        $probe = new Process(['gs', '--version']);

        try {
            $probe->run();
        } catch (Throwable) {
            $this->markTestSkipped('Ghostscript is not available in this environment.');
        }

        if (! $probe->isSuccessful()) {
            $this->markTestSkipped('Ghostscript is not available in this environment.');
        }
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
