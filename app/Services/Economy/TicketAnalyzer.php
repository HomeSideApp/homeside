<?php

namespace App\Services\Economy;

use App\Data\Economy\ReceiptAnalysisResultData;
use App\Exceptions\IncompleteAnalysisException;
use HomeSide\AiAgents\AiAgentManager;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\LocalImage;
use RuntimeException;

/**
 * Contains structured receipt data and the identifier of the recorded AI run.
 */
final readonly class TicketAnalysisResult
{
    /**
     * Create a ticket analysis result value object.
     *
     * @param  ReceiptAnalysisResultData  $data  The structured data extracted from the receipt.
     * @param  string  $runId  The identifier of the recorded AI execution.
     * @return void This constructor does not return a value.
     */
    public function __construct(
        public ReceiptAnalysisResultData $data,
        public string $runId,
    ) {}
}

class TicketAnalyzer
{
    /**
     * Create a new ticket analyzer service instance.
     *
     * @param  AiAgentManager  $manager  The manager used to execute the configured economy agent.
     * @return void This constructor does not return a value.
     */
    public function __construct(
        private readonly AiAgentManager $manager,
    ) {}

    /**
     * Analyze a receipt through the AI execution layer and return structured economic data.
     *
     * @param  string  $disk  The filesystem disk containing the source document.
     * @param  string  $storedPath  The document path on the selected filesystem disk.
     * @param  string|null  $mimeType  The source document MIME type, when known.
     * @param  array<string, bool>  $sections  The optional receipt sections requested by the user.
     * @param  AiExecutionContextData  $context  The user and household context used to resolve the AI provider.
     * @return TicketAnalysisResult The extracted structured receipt data and recorded run identifier.
     *
     * @throws RuntimeException When no AI provider is configured or provider execution fails.
     * @throws IncompleteAnalysisException When the response is not structured or omits the receipt total.
     */
    public function analyze(
        string $disk,
        string $storedPath,
        ?string $mimeType,
        array $sections,
        AiExecutionContextData $context,
    ): TicketAnalysisResult {
        $absolutePath = Storage::disk($disk)->path($storedPath);

        $result = $this->manager->run(
            agentKey: 'economy.ticket_analyzer',
            context: $context,
            userMessage: $this->buildUserMessage($sections),
            attachments: [new LocalImage($absolutePath, $mimeType ?? 'image/jpeg')],
        );

        $data = json_decode($result->reply, true);

        if (! is_array($data) || ! $result->structured) {
            throw new IncompleteAnalysisException(__('app.economy.analysis.incomplete_response'));
        }

        if (empty($data['amount'])) {
            throw new IncompleteAnalysisException(__('app.economy.analysis.missing_amount'));
        }

        return new TicketAnalysisResult(
            data: ReceiptAnalysisResultData::fromArray($data),
            runId: $result->runId,
        );
    }

    /**
     * Build the localized extraction request sent to the receipt analysis agent.
     *
     * @param  array<string, bool>  $sections  The optional receipt sections requested by the user.
     * @return string The localized user prompt describing the fields to extract.
     */
    public function buildUserMessage(array $sections): string
    {
        // The title is always requested, not gated behind a section: the review step refuses to
        // save a movement without one, so the agent must always propose it.
        $requested = [
            __('app.economy.ai.title'),
            __('app.economy.ai.total'),
            __('app.economy.ai.currency'),
        ];
        if ($sections['place'] ?? false) {
            $requested[] = __('app.economy.ai.place');
        }
        if ($sections['date'] ?? false) {
            $requested[] = __('app.economy.ai.date');
        }
        if ($sections['items'] ?? false) {
            $requested[] = __('app.economy.ai.items');
        }
        if ($sections['taxes'] ?? false) {
            $requested[] = __('app.economy.ai.taxes');
        }

        return __('app.economy.ai.prompt', ['fields' => implode(', ', $requested)]);
    }
}
