<?php

namespace App\Jobs;

use App\Ai\ImageGenerationService;
use App\Models\Product;
use App\Models\ProductImage;
use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class GenerateProductImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public string $runId) {}

    public function handle(ImageGenerationService $service): void
    {
        $run = AiRun::query()->findOrFail($this->runId);
        $claimed = AiRun::query()->whereKey($run->id)->where('status', 'queued')->update(['status' => 'running']);

        if ($claimed !== 1) {
            return;
        }

        $startedAt = microtime(true);

        try {
            $product = Product::query()->findOrFail($run->metadata['product_id'] ?? null);
            $images = $service->generateForProduct($product, 3, $run);

            foreach ($images as $image) {
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'image_url' => $image['url'],
                    'is_ai_generated' => true,
                    'prompt' => "Icon of {$product->name}",
                ]);
            }

            $run->refresh();
        } catch (Throwable) {
            $run->update([
                'status' => 'error',
                'error_code' => 'image_generation_failed',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        }
    }
}
