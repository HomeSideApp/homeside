<?php

namespace Tests\Feature\Economy;

use App\Contracts\EntitlementGate;
use App\Contracts\ReceiptTextExtractor;
use App\Data\Economy\ReceiptTextData;
use App\Enums\EconomicImportStatus;
use App\Exceptions\EntitlementQuotaExceededException;
use App\Jobs\AnalyzeEconomicDocumentJob;
use App\Models\EconomicDocument;
use App\Models\EconomicImport;
use App\Models\User;
use App\Services\Economy\TicketAnalyzer;
use HomeSide\AiAgents\AiAgentManager;
use HomeSide\AiAgents\Execution\AiExecutionResultData;
use HomeSide\AiAgents\Execution\AiUsageData;
use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Proves the import is read offline first and only pays for AI when it has to.
 *
 * The gate double records every port call, so "no credit was spent" is asserted as a fact
 * about the port rather than inferred from the import status.
 */
class ReceiptAnalysisJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Raw text captured from the Avenue ticket, reused as the offline reader's output.
     */
    private const AVENUE_TEXT = <<<'TEXT'
    AVENUE DES CHAMPS
    12 RUE DE PARIS 75008
    TEL 01 23 45 67 89
    yoTaL 77 135,00 §
    TOTAL TTC 135,00 €
    date: 22/9/16 12:07:28
    TEXT;

    /**
     * Raw text captured from the Harrods ticket: the label survived, the amount did not.
     */
    private const HARRODS_TEXT = <<<'TEXT'
    HARRODS
    87-135 BROMPTON ROAD
    AMOUNT DUE
    THANK YOU FOR SHOPPING WITH US
    TEXT;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_a_readable_ticket_becomes_ready_for_review_without_touching_the_gate(): void
    {
        $import = $this->createImport();
        $gate = $this->bindRecordingGate();
        $this->bindOfflineText(self::AVENUE_TEXT);

        $this->mock(AiAgentManager::class, function ($mock): void {
            $mock->shouldNotReceive('run');
        });

        $this->runJob($import);

        $import->refresh();

        $this->assertSame(EconomicImportStatus::ReadyForReview, $import->status);
        $this->assertNull($import->ai_run_id, 'Offline reading leaves no AI run to point at.');
        $this->assertNotNull($import->finished_at);
        $this->assertSame('135.00', $import->extracted_payload['amount']);
        $this->assertSame('EUR', $import->extracted_payload['currency']);
        $this->assertSame('AVENUE DES CHAMPS', $import->extracted_payload['title']);
        $this->assertSame('2016-09-22T00:00:00.000000Z', $import->extracted_payload['occurred_at']);
        $this->assertSame([], $import->extracted_payload['items']);
        $this->assertSame([], $gate->calls, 'The free offline path must not reserve or consume any credit.');
    }

    public function test_an_unreadable_document_reserves_one_credit_and_uses_the_ai_path(): void
    {
        $import = $this->createImport();
        $gate = $this->bindRecordingGate();
        $this->bindOfflineText(null);
        $this->mockAiReply(title: 'Mercadona', amount: '42.73', currency: 'EUR');

        $this->runJob($import);

        $import->refresh();

        $this->assertSame(EconomicImportStatus::ReadyForReview, $import->status);
        $this->assertNotNull($import->ai_run_id);
        $this->assertSame('42.73', $import->extracted_payload['amount']);
        $this->assertSame('reserveTicketRecognition:'.$import->id, $gate->calls[0] ?? null);
        $this->assertSame('commitUsage:'.$gate->lastReservationId, $gate->calls[1] ?? null);
        $this->assertCount(2, $gate->calls);
    }

    public function test_offline_text_without_a_legible_amount_still_falls_back_to_the_ai_path(): void
    {
        $import = $this->createImport();
        $gate = $this->bindRecordingGate();
        $this->bindOfflineText(self::HARRODS_TEXT);
        $this->mockAiReply(title: 'Harrods', amount: '88.00', currency: 'EUR');

        $this->runJob($import);

        $import->refresh();

        $this->assertSame(EconomicImportStatus::ReadyForReview, $import->status);
        $this->assertNotNull($import->ai_run_id, 'A ticket without a legible total must go through the AI path.');
        $this->assertSame('reserveTicketRecognition:'.$import->id, $gate->calls[0] ?? null);
    }

    public function test_a_denied_reservation_fails_the_import_without_calling_the_ai(): void
    {
        $import = $this->createImport();
        $this->bindDenyingGate();
        $this->bindOfflineText(null);

        $this->mock(AiAgentManager::class, function ($mock): void {
            $mock->shouldNotReceive('run');
        });

        $this->runJob($import);

        $import->refresh();

        $this->assertSame(EconomicImportStatus::Failed, $import->status);
        $this->assertSame('no_credits', $import->error_code);
        $this->assertNull($import->ai_run_id);
    }

    public function test_a_credit_reserved_before_dispatch_is_reused_instead_of_reserved_twice(): void
    {
        $import = $this->createImport();
        $gate = $this->bindRecordingGate();
        $this->bindOfflineText(null);
        $this->mockAiReply(title: 'Mercadona', amount: '42.73', currency: 'EUR');

        (new AnalyzeEconomicDocumentJob($import->id, 'reserved:canonical-1'))->handle(app(TicketAnalyzer::class));

        $this->assertSame(
            ['commitUsage:reserved:canonical-1'],
            $gate->calls,
            'A dispatcher reservation must be committed once, never reserved again.'
        );
    }

    public function test_a_credit_reserved_before_dispatch_is_released_when_the_offline_pass_succeeds(): void
    {
        $import = $this->createImport();
        $gate = $this->bindRecordingGate();
        $this->bindOfflineText(self::AVENUE_TEXT);

        $this->mock(AiAgentManager::class, function ($mock): void {
            $mock->shouldNotReceive('run');
        });

        (new AnalyzeEconomicDocumentJob($import->id, 'reserved:canonical-1'))->handle(app(TicketAnalyzer::class));

        $import->refresh();

        $this->assertSame(EconomicImportStatus::ReadyForReview, $import->status);
        $this->assertSame(
            ['releaseUsage:reserved:canonical-1'],
            $gate->calls,
            'A credit reserved before dispatch must not leak when the ticket was read offline.'
        );
    }

    /**
     * Create a pending import whose document is a stored image.
     */
    private function createImport(): EconomicImport
    {
        $path = 'economy/documents/ticket.jpg';

        Storage::disk('local')->put(
            $path,
            (string) UploadedFile::fake()->image('ticket.jpg', 900, 700)->get(),
        );

        $document = EconomicDocument::factory()->create([
            'disk' => 'local',
            'path' => $path,
            'ai_path' => $path,
            'mime_type' => 'image/jpeg',
        ]);

        return EconomicImport::factory()->create([
            'document_id' => $document->id,
            'status' => EconomicImportStatus::Pending,
        ]);
    }

    /**
     * Run the analysis job the way the queue worker does.
     */
    private function runJob(EconomicImport $import): void
    {
        (new AnalyzeEconomicDocumentJob($import->id))->handle(app(TicketAnalyzer::class));
    }

    /**
     * Bind an offline reader that returns the given text, or nothing at all.
     */
    private function bindOfflineText(?string $text): void
    {
        $this->app->instance(ReceiptTextExtractor::class, new class($text) implements ReceiptTextExtractor
        {
            public function __construct(private readonly ?string $text) {}

            public function candidates(string $absolutePath): array
            {
                return $this->text === null
                    ? []
                    : [new ReceiptTextData(text: $this->text, engine: 'tesseract', variant: 'raw_1x', confidence: 88.0, language: 'spa')];
            }

            public function extract(string $absolutePath): ?ReceiptTextData
            {
                return $this->candidates($absolutePath)[0] ?? null;
            }
        });
    }

    /**
     * Stub the AI agent manager with one structured receipt reply.
     */
    private function mockAiReply(string $title, string $amount, string $currency): void
    {
        $run = AiRun::create([
            'user_id' => User::factory()->create()->id,
            'agent' => 'economy.ticket_analyzer',
            'agent_version' => 1,
            'status' => 'ok',
            'duration_ms' => 120,
        ]);

        $this->mock(AiAgentManager::class, function ($mock) use ($run, $title, $amount, $currency): void {
            $mock->shouldReceive('run')->once()->andReturn(new AiExecutionResultData(
                runId: $run->id,
                agent: 'economy.ticket_analyzer',
                agentVersion: 1,
                provider: 'test',
                model: 'test-model',
                status: 'ok',
                reply: (string) json_encode(['title' => $title, 'amount' => $amount, 'currency' => $currency]),
                usage: new AiUsageData,
                structured: true,
            ));
        });
    }

    /**
     * Bind a gate that records every call and grants the reservation.
     *
     * @return object{calls: list<string>, lastReservationId: ?string}
     */
    private function bindRecordingGate(): object
    {
        $gate = new class implements EntitlementGate
        {
            /** @var list<string> */
            public array $calls = [];

            public ?string $lastReservationId = null;

            public function assertFeature(User $user, string $feature): void
            {
                $this->calls[] = "assertFeature:{$feature}";
            }

            public function reserveUsage(User $user, string $metric, int $amount, string $operationId): string
            {
                $this->calls[] = "reserveUsage:{$metric}";
                $this->lastReservationId = "{$user->id}:{$operationId}";

                return $this->lastReservationId;
            }

            public function commitUsage(string $operationId): void
            {
                $this->calls[] = "commitUsage:{$operationId}";
            }

            public function releaseUsage(string $operationId): void
            {
                $this->calls[] = "releaseUsage:{$operationId}";
            }

            public function assertHouseholdOwnershipCapacity(User $user, int $incoming = 1): void {}

            public function reserveTicketRecognition(User $user, string $importId): string
            {
                $this->calls[] = "reserveTicketRecognition:{$importId}";
                $this->lastReservationId = "{$user->id}:ticket:{$importId}";

                return $this->lastReservationId;
            }
        };

        $this->app->instance(EntitlementGate::class, $gate);

        return $gate;
    }

    /**
     * Bind a gate that denies the recognition reservation.
     */
    private function bindDenyingGate(): void
    {
        $this->app->instance(EntitlementGate::class, new class implements EntitlementGate
        {
            public function assertFeature(User $user, string $feature): void {}

            public function reserveUsage(User $user, string $metric, int $amount, string $operationId): string
            {
                return 'unused';
            }

            public function commitUsage(string $operationId): void {}

            public function releaseUsage(string $operationId): void {}

            public function assertHouseholdOwnershipCapacity(User $user, int $incoming = 1): void {}

            public function reserveTicketRecognition(User $user, string $importId): string
            {
                throw new EntitlementQuotaExceededException(
                    userId: $user->id,
                    metric: 'ai_image_monthly',
                    cap: 5,
                    used: 5,
                    requested: 1,
                );
            }
        });
    }
}
