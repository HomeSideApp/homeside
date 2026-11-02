<?php

namespace App\Contracts;

use App\Data\Economy\ReceiptTextData;

/**
 * Port for offline receipt text recognition.
 *
 * Implementations MUST degrade to an empty result instead of throwing. The offline layer is
 * an optimisation in front of the metered AI path, so a missing engine, an unsupported
 * document, a timeout or an unreadable image all mean the same thing: no offline text, and
 * the caller falls back to the AI analysis.
 *
 * No single recognition setup wins on real tickets, so the port reports every usable
 * variant rather than a single guess. Turning a variant into structured data — and therefore
 * deciding which variant is right — belongs to the parser, not to the engine: the extractor
 * can only rank its candidates by a cheap score.
 *
 * Version 1 supports images and PDF tickets. An image is recognised through the variant
 * matrix directly. A PDF is a cascade, because the two readings measure very differently: its
 * first page is read through the embedded text layer first (a digital PDF states its own text
 * exactly, where OCR of the same page misreads the amount), and that candidate is reported
 * *before* every OCR variant; when there is no usable text layer — a scan, or the parser
 * library not being installed — the page is rasterised at 300 DPI, first page only, and
 * recognised through exactly the same variants as an image. A document that cannot be
 * rasterised (no Ghostscript, no PDF support in Imagick, a corrupt or empty file) still
 * yields its text-layer candidate, and yields nothing at all when it has no text either, so
 * it keeps going through the AI path unchanged.
 *
 * A text layer without a payable amount never short-circuits the cascade: the OCR variants
 * are still reported, and the parser simply moves on to them.
 */
interface ReceiptTextExtractor
{
    /**
     * Recognise the text of the receipt image at the given path, best candidate first.
     *
     * The path may be an image or a PDF document; a PDF is read through its text layer first
     * and rasterised only when that does not answer, and the caller does not distinguish the
     * two.
     *
     * Variants that produced no text are dropped, so an empty list means the layer cannot
     * help with this document. For a PDF the exact text-layer reading, when there is one, is
     * always the first candidate; the OCR variants follow it, ordered by a cheap score — a
     * text that looks like a total line outranks one that does not, and volume never earns a
     * bonus — because only the parser can decide whether a candidate actually yields an
     * amount.
     *
     * @param  string  $absolutePath  Absolute path of the derived receipt image or PDF document.
     * @return list<ReceiptTextData> The recognised variants, best first; empty when nothing usable was recognised.
     */
    public function candidates(string $absolutePath): array;

    /**
     * Read the text of the receipt image or PDF document, keeping only the best ranked candidate.
     *
     * Kept for callers that only need one text. The analysis path uses {@see candidates()}
     * so an unparseable best candidate cannot hide a readable one.
     *
     * @param  string  $absolutePath  Absolute path of the derived receipt image or PDF document.
     * @return ReceiptTextData|null The best recognised text, or null when nothing usable was recognised.
     */
    public function extract(string $absolutePath): ?ReceiptTextData;
}
