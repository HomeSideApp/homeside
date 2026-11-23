<?php

namespace Tests\Feature\Economy;

use App\Jobs\AnalyzeEconomicDocumentJob;
use App\Models\EconomicImport;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * El primer import pregunta una vez y sigue en el mismo lugar.
 *
 * Mandar a la persona a los ajustes y de vuelta sería pedirle que abandone la
 * pantalla que estaba usando.
 */
class ReceiptConsentRouteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create(['ai_receipt_consent_at' => null]);
        $this->user->assignRole('user');
    }

    private function pendingImport(): EconomicImport
    {
        return EconomicImport::factory()->create([
            'household_id' => null,
            'created_by' => $this->user->id,
            'status' => 'failed',
            'error_code' => 'ai_consent_required',
        ]);
    }

    public function test_allowing_the_ai_records_the_permission_and_retries(): void
    {
        $import = $this->pendingImport();

        $this->actingAs($this->user)
            ->post(route('economy.me.imports.allow-ai', $import))
            ->assertRedirect();

        $this->assertNotNull(
            $this->user->refresh()->ai_receipt_consent_at,
            'El permiso tiene que quedar guardado con fecha.',
        );

        Queue::assertPushed(AnalyzeEconomicDocumentJob::class);
    }

    public function test_the_permission_is_kept_when_it_was_already_granted(): void
    {
        $granted = now()->subDay()->startOfDay();
        $this->user->forceFill(['ai_receipt_consent_at' => $granted])->save();

        $import = $this->pendingImport();

        $this->actingAs($this->user)->post(route('economy.me.imports.allow-ai', $import));

        $this->assertSame(
            $granted->toDateTimeString(),
            $this->user->refresh()->ai_receipt_consent_at?->toDateTimeString(),
            'Volver a aceptar no puede mover la fecha original.',
        );
    }
}
