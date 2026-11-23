<?php

declare(strict_types=1);

namespace App\Ai\Modules;

use HomeSide\AiAgents\Contracts\ModuleAiProvider;

/**
 * AI module for the economy domain, declaring its agents and default
 * configuration.
 */
class EconomyAiModule implements ModuleAiProvider
{
    /**
     * Get the module this provider belongs to.
     */
    public function module(): string
    {
        return 'economy';
    }

    /**
     * Get the agents this module needs.
     *
     * @return array<string, array{
     *     label: string,
     *     system_prompt: string,
     *     description: string|null,
     *     parameters: array{temperature?: float, max_tokens?: int}|null
     * }>
     */
    public function agents(): array
    {
        return [
            'ticket_analyzer' => [
                'label' => 'Ticket analyzer',
                'system_prompt' => <<<'PROMPT'
You extract structured data from photographs of purchase receipts, invoices and tickets, from any country.

RULES:
1. Read the currency from the document itself (symbol, ISO code or wording). Never assume a country or a currency.
2. Report amounts as printed. Do not convert, round or reformat beyond removing the thousands separator.
3. Dates: return them as ISO 8601 (YYYY-MM-DD). When the document is ambiguous (day/month order), prefer the reading that matches the receipt number and the weekday if any, and say so in "notes".
4. Line items: copy the printed name, keep the quantity when it is explicit ("2 x 1,20"), and skip subtotal, tax, change and payment-method lines.
5. Report the total actually paid, not the subtotal. If a discount line exists, apply it to the total, not to individual items.
6. Merchant: the trading name, without the legal suffix unless it is the only name present.
7. Leave a field null when the document does not contain it. Never invent a value: a missing field is far better than a wrong one.
8. If the image is unreadable or is not a purchase document at all, return {"error": "unreadable"} instead of guessing.
9. Reply with JSON only, no prose before or after.
PROMPT,
                'description' => 'Analyses receipt images and extracts structured data.',
                'parameters' => [
                    'temperature' => 0.1,
                    'max_tokens' => 4096,
                ],
            ],
        ];
    }

    /**
     * Get the default configuration for the module.
     *
     * @return array<string, mixed>
     */
    public function defaultConfiguration(): array
    {
        return [
            'auto_categorize' => true,
            'require_review' => true,
        ];
    }
}
