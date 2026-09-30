<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Assistant\AiRunResource;
use App\Http\Resources\Products\AdminProductResource;
use App\Jobs\GenerateProductImagesJob;
use App\Models\Product;
use App\Models\ProductImage;
use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminProductImageController extends Controller
{
    public function pending(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $products = Product::query()->where('needs_image', true)->where('is_approved', false)
            ->with(['category', 'images'])
            ->when($validated['search'] ?? null, fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')->orderBy('id')->paginate($validated['perPage'] ?? 15)->withQueryString();

        return AdminProductResource::collection($products)->response();
    }

    public function generate(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->needs_image && ! $product->is_approved, 409, 'This product is not awaiting an image.');
        abort_if(AiRun::query()
            ->where('agent', 'admin.product_image_generator')
            ->whereIn('status', ['queued', 'running'])
            ->where('metadata->product_id', $product->id)
            ->exists(), 409, 'Image generation is already in progress for this product.');
        $run = AiRun::create([
            'user_id' => $this->authenticatedUser($request)->id,
            'agent' => 'admin.product_image_generator',
            'agent_version' => 1,
            'duration_ms' => 0,
            'status' => 'queued',
            'metadata' => ['product_id' => $product->id],
        ]);
        GenerateProductImagesJob::dispatch($run->id);

        return AiRunResource::make($run->fresh())->response()->setStatusCode(202);
    }

    public function showRun(Request $request, AiRun $run): AiRunResource
    {
        abort_unless($run->agent === 'admin.product_image_generator', 404);

        return new AiRunResource($run);
    }

    public function approve(ProductImage $image): JsonResponse
    {
        abort_if($image->is_selected, 409, 'This image is already approved.');
        $product = $image->product;
        abort_if($product === null, 404);
        abort_unless($product->needs_image && ! $product->is_approved, 409, 'This product is not awaiting image approval.');
        ProductImage::query()->where('product_id', $product->id)->update(['is_selected' => false]);
        $image->update(['is_selected' => true]);
        $product->update(['image_url' => $image->image_url, 'is_approved' => true, 'needs_image' => false]);

        return response()->json(['data' => [
            'product' => (new AdminProductResource($product->fresh()->load('category')))->resolve(request()),
            'image' => ['id' => $image->id, 'image_url' => $image->image_url, 'is_selected' => true],
        ]]);
    }
}
