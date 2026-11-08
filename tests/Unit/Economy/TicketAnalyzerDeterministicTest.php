<?php

namespace Tests\Unit\Economy;

use App\Contracts\ReceiptTextExtractor;
use App\Data\Economy\ReceiptTextData;
use App\Services\Economy\ReceiptTextParser;
use App\Services\Economy\TicketAnalyzer;
use HomeSide\AiAgents\AiAgentManager;
use LogicException;
use Tests\TestCase;

/**
 * Pins who owns the final decision of the offline layer: the parser, not the extractor.
 *
 * The extractor can only order the recognised variants by a cheap score — it must not
 * parse. Turning the candidates into structured data, and refusing the ones without a
 * readable amount, is the analyzer's job. These tests bind a stub extractor that throws
 * when the single-candidate `extract()` path is used, so "the analyzer walked the
 * candidates" is asserted as a fact rather than inferred from the result.
 */
class TicketAnalyzerDeterministicTest extends TestCase
{
    /**
     * Noise without any total label: long, but unparseable.
     */
    private const NOISY_TEXT = <<<'TEXT'
    le Restaurant n'accepte plus les CHEQUES
    Merci de votre visite, A bientot
    TYAS 20,00 7 6WE
    TUR 16,00 GE
    VERE POUILL 14,08
    CHATEAU FILET GRILLE 856,00
    TEXT;

    /**
     * The measured Avenue total, in the shape Tesseract actually returned.
     */
    private const AVENUE_TOTAL_TEXT = <<<'TEXT'
    41. Ave MA
    Ll ei 7 mon
    table : 141
    TOTAL TTE2 135,00 [
    Merci de votre visite,
    TEXT;

    public function test_it_skips_a_longer_candidate_the_parser_cannot_turn_into_an_amount(): void
    {
        $analyzer = $this->analyzer([
            $this->candidate(self::NOISY_TEXT, 'spa', 'raw_1x'),
            $this->candidate(self::AVENUE_TOTAL_TEXT, 'spa', 'grayscale_2x'),
        ]);

        $result = $analyzer->analyzeDeterministically('/tmp/receipt.jpg');

        $this->assertNotNull($result, 'A candidate with a readable amount resolves the ticket offline.');
        $this->assertSame(13500, $result->amount_minor);
        $this->assertSame('41. Ave MA', $result->title);
    }

    public function test_it_falls_through_every_candidate_before_giving_up(): void
    {
        $analyzer = $this->analyzer([
            $this->candidate(self::NOISY_TEXT, 'spa', 'raw_1x'),
            $this->candidate(self::NOISY_TEXT, 'eng', 'raw_1x'),
            $this->candidate(self::AVENUE_TOTAL_TEXT, 'eng', 'grayscale_2x'),
        ]);

        $this->assertSame(13500, $analyzer->analyzeDeterministically('/tmp/receipt.jpg')?->amount_minor);
    }

    public function test_it_returns_null_when_no_candidate_carries_a_readable_amount(): void
    {
        $analyzer = $this->analyzer([
            $this->candidate(self::NOISY_TEXT, 'spa', 'raw_1x'),
            $this->candidate("HARRODS\n87-135 BROMPTON ROAD\nAMOUNT DUE", 'eng', 'raw_1x'),
        ]);

        $this->assertNull(
            $analyzer->analyzeDeterministically('/tmp/receipt.jpg'),
            'A receipt without a legible amount must fall back to the metered AI path.'
        );
    }

    public function test_it_returns_null_when_the_extractor_yields_no_candidates(): void
    {
        $this->assertNull($this->analyzer([])->analyzeDeterministically('/tmp/receipt.jpg'));
    }

    /**
     * Build the analyzer over a stub extractor that only implements the candidate contract.
     *
     * @param  list<ReceiptTextData>  $candidates  The candidates the stub extractor reports.
     */
    private function analyzer(array $candidates): TicketAnalyzer
    {
        return new TicketAnalyzer(
            $this->mock(AiAgentManager::class),
            $this->extractor($candidates),
            new ReceiptTextParser,
        );
    }

    /**
     * Build a stub extractor that refuses the single-candidate shortcut.
     *
     * @param  list<ReceiptTextData>  $candidates  The candidates the stub extractor reports.
     */
    private function extractor(array $candidates): ReceiptTextExtractor
    {
        return new class($candidates) implements ReceiptTextExtractor
        {
            /**
             * @param  list<ReceiptTextData>  $candidates  The candidates the stub extractor reports.
             */
            public function __construct(private readonly array $candidates) {}

            public function candidates(string $absolutePath): array
            {
                return $this->candidates;
            }

            public function extract(string $absolutePath): ?ReceiptTextData
            {
                throw new LogicException('The analyzer must walk every candidate, not only the best one.');
            }
        };
    }

    /**
     * Build one recognised variant.
     */
    private function candidate(string $text, string $language, string $variant): ReceiptTextData
    {
        return new ReceiptTextData(
            text: $text,
            engine: 'tesseract',
            variant: $variant,
            confidence: 72.0,
            language: $language,
        );
    }
}
