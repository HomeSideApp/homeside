<?php

namespace Tests\Feature\Economy;

use App\Contracts\EntitlementGate;
use App\Jobs\AnalyzeEconomicDocumentJob;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pins the metering boundary of the upload: uploading a ticket is free.
 *
 * The credit belongs to the metered AI analysis, which the job reserves by itself and only
 * after the offline pass failed. A plan without image recognition can therefore still
 * upload, and a ticket the offline layer reads costs nothing at all.
 */
class EconomicImportMeteringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_uploading_a_ticket_does_not_reserve_a_recognition_credit(): void
    {
        $user = $this->createUser();
        $gate = $this->bindRecordingGate();

        $this->actingAs($user)->post(route('economy.me.imports.store'), [
            'file' => UploadedFile::fake()->image('ticket.jpg', 900, 700),
            'sections' => ['place' => true, 'date' => true, 'items' => false, 'taxes' => false],
        ])->assertRedirect();

        $this->assertSame([], $gate->calls, 'The upload path must not touch the entitlement gate.');

        Queue::assertPushed(
            AnalyzeEconomicDocumentJob::class,
            fn (AnalyzeEconomicDocumentJob $job): bool => $job->operationId === null,
        );
    }

    /**
     * Create an authenticated user allowed to import tickets.
     */
    private function createUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    /**
     * Bind a gate that records every call and grants every check.
     *
     * @return object{calls: list<string>}
     */
    private function bindRecordingGate(): object
    {
        $gate = new class implements EntitlementGate
        {
            /** @var list<string> */
            public array $calls = [];

            public function assertFeature(User $user, string $feature): void
            {
                $this->calls[] = "assertFeature:{$feature}";
            }

            public function reserveUsage(User $user, string $metric, int $amount, string $operationId): string
            {
                $this->calls[] = "reserveUsage:{$metric}";

                return "{$user->id}:{$operationId}";
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

                return "{$user->id}:ticket:{$importId}";
            }
        };

        $this->app->instance(EntitlementGate::class, $gate);

        return $gate;
    }
}
