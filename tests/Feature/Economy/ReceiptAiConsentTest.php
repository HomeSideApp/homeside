<?php

namespace Tests\Feature\Economy;

use App\Contracts\ReceiptTextExtractor;
use App\Exceptions\Economy\ReceiptAiConsentRequiredException;
use App\Models\User;
use App\Services\Economy\ReceiptTextParser;
use App\Services\Economy\TicketAnalyzer;
use Database\Seeders\RolesAndPermissionsSeeder;
use HomeSide\AiAgents\AiAgentManager;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use HomeSide\AiAgents\Models\AiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Un ticket sólo sale del servidor si su dueño lo permitió.
 *
 * El pase determinístico no pide permiso porque no manda nada afuera; la IA sí,
 * cuando el proveedor configurado no corre en la propia instalación.
 */
class ReceiptAiConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        Storage::disk('local')->put('ticket.png', 'fake-bytes');
    }

    private function provider(string $privacy): AiProvider
    {
        return AiProvider::createValidated([
            'name' => 'Proveedor '.$privacy,
            'type' => 'openai-compatible',
            'driver' => 'openai-compatible',
            'base_url' => 'https://api.nan.builders/v1',
            'model' => 'model-1',
            'api_key' => 'sk-test-0123456789',
            'modules' => ['economy'],
            'enabled' => true,
            'privacy_level' => $privacy,
        ]);
    }

    private function analyze(User $user): void
    {
        // El manager no debe llegar a ejecutarse: si la puerta funciona, nunca
        // hay request. Si se ejecuta, el mock devuelve null y el test falla.
        $this->mock(AiAgentManager::class, function ($mock): void {
            $mock->shouldNotReceive('run');
        });

        (new TicketAnalyzer(
            app(AiAgentManager::class),
            $this->mock(ReceiptTextExtractor::class),
            new ReceiptTextParser,
        ))->analyze(
            'local',
            'ticket.png',
            'image/png',
            ['place' => true],
            AiExecutionContextData::fromRequest(userId: (string) $user->id, tenantId: null),
        );
    }

    public function test_a_cloud_provider_without_permission_is_refused(): void
    {
        $user = User::factory()->create(['ai_receipt_consent_at' => null]);
        $this->provider('cloud');

        $this->expectException(ReceiptAiConsentRequiredException::class);

        $this->analyze($user);
    }

    public function test_an_unclassified_provider_is_refused_too(): void
    {
        // `unknown` rango peor que cloud: no pasa ni con el requisito más bajo.
        $user = User::factory()->create(['ai_receipt_consent_at' => null]);
        $this->provider('unknown');

        $this->expectException(ReceiptAiConsentRequiredException::class);

        $this->analyze($user);
    }

    public function test_a_self_hosted_provider_needs_no_permission(): void
    {
        $user = User::factory()->create(['ai_receipt_consent_at' => null]);
        $this->provider('self_hosted');

        $this->mock(AiAgentManager::class, function ($mock): void {
            $mock->shouldReceive('run')->once()->andReturnUsing(function (): never {
                throw new \RuntimeException('llegó al proveedor: la puerta lo dejó pasar');
            });
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('llegó al proveedor');

        (new TicketAnalyzer(
            app(AiAgentManager::class),
            $this->mock(ReceiptTextExtractor::class),
            new ReceiptTextParser,
        ))->analyze(
            'local',
            'ticket.png',
            'image/png',
            ['place' => true],
            AiExecutionContextData::fromRequest(userId: (string) $user->id, tenantId: null),
        );
    }

    public function test_the_permission_travels_with_the_user_not_the_session(): void
    {
        // El job corre en cola, sin sesión: el permiso se lee de la base.
        $user = User::factory()->create(['ai_receipt_consent_at' => now()]);
        $this->provider('cloud');

        $this->mock(AiAgentManager::class, function ($mock): void {
            $mock->shouldReceive('run')->once()->andReturnUsing(function (): never {
                throw new \RuntimeException('llegó al proveedor: hay permiso');
            });
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('llegó al proveedor');

        (new TicketAnalyzer(
            app(AiAgentManager::class),
            $this->mock(ReceiptTextExtractor::class),
            new ReceiptTextParser,
        ))->analyze(
            'local',
            'ticket.png',
            'image/png',
            ['place' => true],
            AiExecutionContextData::fromRequest(userId: (string) $user->id, tenantId: null),
        );
    }
}
