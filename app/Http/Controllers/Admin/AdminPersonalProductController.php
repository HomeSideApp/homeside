<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Icons\ListIcons;
use App\Actions\Products\CreateProduct;
use App\Ai\ImageGenerationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotePersonalProductRequest;
use App\Http\Requests\GenerateProductIconRequest;
use App\Http\Requests\ViewPersonalProductRequest;
use App\Models\Category;
use App\Models\ListItem;
use App\Models\Product;
use App\Models\RecipeIngredient;
use App\Services\Recipes\IngredientMatcher;
use App\Support\ProductNameNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles admin management of personal products shared by users.
 */
class AdminPersonalProductController extends Controller
{
    /**
     * List shared personal products, one row per normalised product name.
     *
     * Pagination happens on the groups, not on the products: the screen shows
     * one row per product name, so `total` must be the number of groups and
     * stays honest across the whole filtered set. The grouping key is accent
     * insensitive, which SQL cannot express portably here, so the filtered
     * columns are grouped in PHP before paginating.
     */
    public function index(Request $request): Response
    {
        $query = Product::query()
            ->where('is_personal', true)
            ->whereHas('creator', fn (Builder $creator): Builder => $creator->where('shares_personal_products', true))
            ->with('creator:id,name', 'category:id,name')
            ->select(['id', 'name', 'category_id', 'icon', 'created_by', 'created_at']);

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $groups = $query->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Product $product): string => ProductNameNormalizer::normalize($product->name))
            ->sortKeys()
            ->values()
            ->map(fn (Collection $group): array => $this->summarizeGroup($group));

        $perPage = (int) $request->input('perPage', 15);
        $page = LengthAwarePaginator::resolveCurrentPage();

        $products = new LengthAwarePaginator(
            $groups->forPage($page, $perPage)->values(),
            $groups->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
        $products->withQueryString();

        return Inertia::render('admin/PersonalProducts/Index', [
            'products' => $products,
            'filters' => $request->only(['search', 'perPage']),
        ]);
    }

    /**
     * Show the promote form for a personal product.
     */
    public function show(ViewPersonalProductRequest $request, Product $product, ListIcons $listIcons): Response
    {

        return Inertia::render('admin/PersonalProducts/Promote', [
            'product' => $product->load('creator', 'category'),
            'categories' => Category::orderBy('sort_order')->get(['id', 'name']),
            'icons' => $listIcons->execute(),
        ]);
    }

    /**
     * Promote a personal product to a global catalog product.
     *
     * The promoted product is the canonical row of its name group, so every
     * personal product that belongs to that group is repointed and deleted.
     */
    public function promote(
        PromotePersonalProductRequest $request,
        Product $product,
        CreateProduct $action,
    ): RedirectResponse {
        $globalProduct = DB::transaction(function () use ($request, $product, $action): Product {
            $name = $request->validated('name', $product->name);
            $mergeable = $this->mergeableProducts($product);

            // The personal rows still hold the slug the global product needs.
            $this->releaseSlugForGlobalProduct(Str::slug($name), $mergeable);

            $globalProduct = $action->execute([
                'name' => $name,
                'category_id' => $request->validated('category_id') ?? $product->category_id,
                'icon' => $request->validated('icon') ?? $product->icon,
                'is_personal' => false,
            ], (string) $product->created_by);

            foreach ($mergeable as $mergeableProduct) {
                $this->moveReferences($mergeableProduct, $globalProduct->id);
                $mergeableProduct->delete();
            }

            return $globalProduct;
        });

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
    public function generateIcon(GenerateProductIconRequest $request, ImageGenerationService $imageService): JsonResponse
    {
        $validated = $request->validated();

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
     * Build the admin row of a name group.
     *
     * @param  Collection<int, Product>  $group
     * @return array{id: string, name: string, icon: string|null, is_personal: bool, created_by: string|null, users: int, creator: array{id: string, name: string}|null, category: array{id: string, name: string}|null}
     */
    private function summarizeGroup(Collection $group): array
    {
        /** @var Product $canonical */
        $canonical = $group->first();
        $creator = $canonical->creator;
        $category = $canonical->category;

        return [
            'id' => (string) $canonical->getKey(),
            'name' => $canonical->name,
            'icon' => $canonical->icon,
            'is_personal' => true,
            'created_by' => $canonical->created_by,
            'users' => $group->pluck('created_by')->filter()->unique()->count(),
            'creator' => $creator === null ? null : [
                'id' => (string) $creator->getKey(),
                'name' => $creator->name,
            ],
            'category' => $category === null ? null : [
                'id' => (string) $category->getKey(),
                'name' => $category->name,
            ],
        ];
    }

    /**
     * Personal products that promote must merge into the global product: the
     * exact name group of the canonical row plus the fuzzy matches it already
     * merged before grouping existed.
     *
     * @return Collection<int, Product>
     */
    private function mergeableProducts(Product $canonical): Collection
    {
        $matcher = app(IngredientMatcher::class);
        $normalizedName = ProductNameNormalizer::normalize($canonical->name);

        return Product::query()
            ->where('is_personal', true)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (Product $candidate): bool => $candidate->is($canonical)
                || ProductNameNormalizer::normalize($candidate->name) === $normalizedName
                || $matcher->match($candidate->name, collect([$canonical]))['status'] === IngredientMatcher::MATCHED)
            ->values();
    }

    /**
     * Move the references of a personal product to the global one.
     */
    private function moveReferences(Product $from, string $globalProductId): void
    {
        RecipeIngredient::where('product_id', $from->id)
            ->update(['product_id' => $globalProductId]);

        ListItem::where('product_id', $from->id)
            ->update(['product_id' => $globalProductId]);
    }

    /**
     * Free the slug the global product will use, without leaving a personal
     * product renamed behind: every member is deleted right after promoting.
     *
     * @param  Collection<int, Product>  $products
     */
    private function releaseSlugForGlobalProduct(string $slug, Collection $products): void
    {
        foreach ($products as $product) {
            if ($product->slug !== $slug) {
                continue;
            }

            $product->update(['slug' => Str::limit($slug, 218, '').'-'.Str::uuid()]);
        }
    }
}
