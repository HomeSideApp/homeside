<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Icons\ListIcons;
use App\Actions\Products\CreateProduct;
use App\Ai\ImageGenerationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotePersonalProductRequest;
use App\Models\Category;
use App\Models\ListItem;
use App\Models\Product;
use App\Models\RecipeIngredient;
use App\Services\Recipes\IngredientMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles admin management of personal products shared by users.
 */
class AdminPersonalProductController extends Controller
{
    /**
     * List shared personal products with search and pagination.
     */
    public function index(Request $request): Response
    {
        $query = Product::where('is_personal', true)
            ->whereHas('creator', fn ($q) => $q->where('shares_personal_products', true))
            ->with('creator', 'category');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $products = $query->orderBy('name')
            ->paginate($request->input('perPage', 15))
            ->withQueryString();

        return Inertia::render('admin/PersonalProducts/Index', [
            'products' => $products,
            'filters' => $request->only(['search', 'perPage']),
        ]);
    }

    /**
     * Show the promote form for a personal product.
     */
    public function show(Product $product, ListIcons $listIcons): Response
    {
        abort_unless($product->is_personal && $product->creator?->shares_personal_products, 404);

        return Inertia::render('admin/PersonalProducts/Promote', [
            'product' => $product->load('creator', 'category'),
            'categories' => Category::orderBy('sort_order')->get(['id', 'name']),
            'icons' => $listIcons->execute(),
        ]);
    }

    /**
     * Promote a personal product to a global catalog product.
     */
    public function promote(
        PromotePersonalProductRequest $request,
        Product $product,
        CreateProduct $action,
    ): RedirectResponse {
        // IDOR check: ensure product is personal and shared
        abort_unless(
            $product->is_personal && $product->creator?->shares_personal_products,
            403
        );

        $globalProduct = $action->execute([
            'name' => $request->validated('name', $product->name),
            'category_id' => $request->validated('category_id') ?? $product->category_id,
            'icon' => $request->validated('icon') ?? $product->icon,
            'is_personal' => false,
        ], $product->created_by);

        // Update recipe ingredient references
        RecipeIngredient::where('product_id', $product->id)
            ->update(['product_id' => $globalProduct->id]);

        // Update list item references
        ListItem::where('product_id', $product->id)
            ->update(['product_id' => $globalProduct->id]);

        // Delete similar personal products
        $this->deleteSimilarPersonalProducts($product, $globalProduct->id);

        $product->delete();

        return to_route('admin.products')->with('toast', [
            'type' => 'success',
            'message' => __('app.toast.personal_product_promoted', ['name' => $globalProduct->name]),
        ]);
    }

    /**
     * Generate an AI icon for a product being promoted.
     *
     * Accepts a detailed prompt for better icon generation.
     */
    public function generateIcon(Request $request, ImageGenerationService $imageService): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $imageService->generateProductIcon(
                productName: $validated['prompt'],
                category: $validated['category'] ?? null,
            );

            return response()->json([
                'url' => $result['url'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => __('app.toast.image_generation_failed'),
            ], 422);
        }
    }

    /**
     * Group products by name similarity.
     *
     * @return array<int, array{representative: Product, products: array<int, Product>, usage_count: int}>
     */
    private function groupBySimilarity(Collection $products): array
    {
        $matcher = app(IngredientMatcher::class);
        $groups = [];

        foreach ($products as $product) {
            $found = false;
            foreach ($groups as &$group) {
                $match = $matcher->match($product->name, collect([$group['representative']]));
                if ($match['status'] === IngredientMatcher::MATCHED) {
                    $group['products'][] = $product;
                    $group['usage_count']++;
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $groups[] = [
                    'representative' => $product,
                    'products' => [$product],
                    'usage_count' => 1,
                ];
            }
        }

        return $groups;
    }

    /**
     * Delete similar personal products and update references to the global product.
     */
    private function deleteSimilarPersonalProducts(Product $original, string $globalProductId): void
    {
        $matcher = app(IngredientMatcher::class);
        $similar = Product::where('is_personal', true)
            ->where('id', '!=', $original->id)
            ->get();

        foreach ($similar as $product) {
            $match = $matcher->match($product->name, collect([$original]));
            if ($match['status'] === IngredientMatcher::MATCHED) {
                RecipeIngredient::where('product_id', $product->id)
                    ->update(['product_id' => $globalProductId]);
                ListItem::where('product_id', $product->id)
                    ->update(['product_id' => $globalProductId]);
                $product->delete();
            }
        }
    }
}
