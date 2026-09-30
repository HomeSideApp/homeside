<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Prompting\ImagePromptBuilder;
use App\Models\Product;
use HomeSide\AiAgents\Execution\AiExecutionContextData;
use HomeSide\AiAgents\Execution\ImageExecutor;
use HomeSide\AiAgents\Models\AiProvider;
use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates images using an OpenAI-compatible image generation API.
 *
 * Resolves the provider through the AI provider system (ProviderResolver),
 * ensuring all image generation goes through the controlled AI infrastructure.
 * Supports any provider that implements the /v1/images/generations endpoint:
 * NaN Builders (FLUX), OpenAI (DALL-E), Together AI, Replicate, etc.
 *
 * Provides three generation modes:
 * - Product icons: flat minimalist monochromatic PNG with transparent background
 * - Free-form images: any text-to-image generation for agents
 * - Bulk product images: multiple images for product catalog
 */
class ImageGenerationService
{
    public function __construct(
        private readonly ImageExecutor $executor,
    ) {}

    /**
     * Generate a flat monochromatic icon for a product.
     *
     * PNG with transparent background, solid black silhouette.
     * Matches the Icons8 flat design style used in public/icons/.
     *
     * @return array{url: string, b64: string}
     */
    public function generateProductIcon(string $productName, ?string $category = null): array
    {
        $prompt = ImagePromptBuilder::forProductIcon($productName, $category);
        $results = $this->generate($prompt, 1, $productName);

        return $results[0] ?? throw new \RuntimeException('No icon generated');
    }

    /**
     * Generate a free-form image from a text description.
     *
     * Used by AI agents for any image generation request.
     *
     * @param  string  $prompt  The image description
     * @param  int  $count  Number of images to generate (max 4)
     * @return array<int, array{url: string, b64: string}>
     */
    public function generateFreeImage(string $prompt, int $count = 1): array
    {
        return $this->generate($prompt, min($count, 4));
    }

    /**
     * Generate images for a product and store them.
     *
     * @param  Product  $product  The product to generate images for
     * @param  int  $count  Number of images to generate
     * @return array<int, array{b64: string, url: string}>
     */
    public function generateForProduct(Product $product, int $count = 3, ?AiRun $run = null): array
    {
        $prompt = ImagePromptBuilder::forProductIcon(
            $product->name,
            $product->category?->name,
        );

        return $this->generate($prompt, $count, $product->name, $run);
    }

    /**
     * Generate a single icon image from a prompt (legacy method).
     *
     * @return array{url: string, b64: string}
     */
    public function generateIcon(string $prompt, string $suffix = 'icon'): array
    {
        $results = $this->generate($prompt, 1);

        return $results[0] ?? throw new \RuntimeException('No image generated');
    }

    /**
     * Generate images using the resolved AI provider.
     *
     * @param  string  $prompt  The image generation prompt
     * @param  int  $count  Number of images to generate
     * @return array<int, array{b64: string, url: string}>
     */
    private function generate(string $prompt, int $count = 3, ?string $namePrefix = null, ?AiRun $run = null): array
    {
        $userId = $run?->user_id ?? auth()->id();

        if ($userId === null) {
            throw new \RuntimeException('An authenticated user is required for image generation.');
        }

        return $this->executor->execute(
            new AiExecutionContextData(userId: $userId, tenantId: $run?->household_id),
            $prompt,
            $count,
            function (AiProvider $provider) use ($prompt, $count, $namePrefix): array {
                $response = Http::timeout($this->getTimeout($provider))
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$provider->api_key,
                        'Content-Type' => 'application/json',
                    ])
                    ->post($provider->base_url.'/images/generations', [
                        'model' => $provider->model,
                        'prompt' => $prompt,
                        'n' => $count,
                        'size' => $this->getSize($provider),
                        'response_format' => 'b64_json',
                    ]);

                if ($response->failed()) {
                    throw new \RuntimeException('Image generation failed: '.$response->body());
                }

                $images = $response->json('data', []);

                return collect($images)->map(fn ($img) => [
                    'b64' => $img['b64_json'],
                    'url' => $this->storeGeneratedImage($img['b64_json'], $namePrefix ?? 'generated'),
                ])->toArray();
            },
            $run,
        );
    }

    /**
     * Get the image size from provider configuration or default.
     */
    private function getSize(AiProvider $provider): string
    {
        return $provider->configuration['default_size'] ?? '1024x1024';
    }

    /**
     * Get the timeout from provider configuration or default.
     */
    private function getTimeout(AiProvider $provider): int
    {
        return $provider->configuration['timeout'] ?? 60;
    }

    /**
     * Store a generated image in the local disk.
     */
    private function storeGeneratedImage(string $b64, string $prefix = 'generated'): string
    {
        $safeName = Str::slug($prefix);
        $directory = 'images/generated';
        $filename = "{$safeName}.png";
        $fullPath = "{$directory}/{$filename}";

        if (Storage::disk('local')->exists($fullPath)) {
            $counter = 1;
            while (Storage::disk('local')->exists("{$directory}/{$safeName}-{$counter}.png")) {
                $counter++;
            }
            $filename = "{$safeName}-{$counter}.png";
            $fullPath = "{$directory}/{$filename}";
        }

        Storage::disk('local')->put($fullPath, base64_decode($b64));

        return route('images.show', ['type' => 'generated', 'uuid' => $filename]);
    }
}
