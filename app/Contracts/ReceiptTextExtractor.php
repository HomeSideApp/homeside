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
 * Version 1 supports images only. PDF documents are not rasterised yet and always
 * yield no candidates, so they keep going through the AI path unchanged.
 */
interface ReceiptTextExtractor
{
    /**
     * Recognise the text of the receipt image at the given path, best candidate first.
     *
     * Variants that produced no text are dropped, so an empty list means the layer cannot
     * help with this document. Ordering is by a cheap score only — a text that looks like a
     * total line outranks one that does not, and volume never earns a bonus — because only
     * the parser can decide whether a candidate actually yields an amount.
     *
     * @param  string  $absolutePath  Absolute path of the derived receipt image.
     * @return list<ReceiptTextData> The recognised variants, best first; empty when nothing usable was recognised.
     */
    public function candidates(string $absolutePath): array;

    /**
     * Read the text of the receipt image, keeping only the best ranked candidate.
     *
     * Kept for callers that only need one text. The analysis path uses {@see candidates()}
     * so an unparseable best candidate cannot hide a readable one.
     *
     * @param  string  $absolutePath  Absolute path of the derived receipt image.
     * @return ReceiptTextData|null The best recognised text, or null when nothing usable was recognised.
     */
    public function extract(string $absolutePath): ?ReceiptTextData;
}
