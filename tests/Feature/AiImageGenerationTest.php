<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\ImageGenerationService;
use App\Models\User;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiImageGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_generation_uses_scoped_provider_and_records_unknown_token_usage(): void
    {
        Storage::fake('local');
        Http::fake(['https://api.openai.com/v1/images/generations' => Http::response([
            'data' => [['b64_json' => base64_encode('image bytes')]],
        ])]);
        $user = User::factory()->create();
        $provider = AiProvider::factory()->create([
            'module' => 'image_generation',
            'base_url' => 'https://api.openai.com/v1',
        ]);

        $this->actingAs($user);
        $result = app(ImageGenerationService::class)->generateProductIcon('Milk');

        $this->assertSame(base64_encode('image bytes'), $result['b64']);
        Http::assertSentCount(1);
        $run = AiRun::query()->sole();
        $this->assertSame($provider->id, $run->provider_id);
        $this->assertSame('ok', $run->status);
        $this->assertSame(1, $run->metadata['generated_images_count']);
        $this->assertNull($run->input_tokens);
        $this->assertNull($run->estimated_cost);
    }

    public function test_image_generation_does_not_call_http_without_a_provider(): void
    {
        Http::fake();
        $this->actingAs(User::factory()->create());

        $this->expectException(\RuntimeException::class);

        try {
            app(ImageGenerationService::class)->generateProductIcon('Milk');
        } finally {
            Http::assertNothingSent();
        }
    }
}
