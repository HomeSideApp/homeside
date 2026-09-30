<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\QuickCreateProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\QuickCreateProductRequest;
use App\Http\Resources\Products\ProductResource;
use App\Models\Household;
use App\Models\Product;
use App\Models\ProductUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Class ProductSearchController
 *
 * This controller handles the API endpoints for searching, listing recent and
 * quickly creating products.
 */
final class ProductSearchController extends Controller
{
    /**
     * Search products by the given query.
     *
     * @param  Request  $request  The incoming HTTP request containing the search term.
     * @param  SearchProducts  $action  The action that performs the product search.
     * @return AnonymousResourceCollection The collection of matching product resources.
     */
    public function search(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'between:1,100'],
            'category_id' => ['sometimes', 'uuid', 'exists:categories,id'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $products = Product::query()
            ->withTranslationData()
            ->with(['category' => fn ($query) => $query->withTranslationData()])
            ->where('name', 'like', "%{$validated['q']}%")
            ->when($validated['category_id'] ?? null, fn ($query, string $categoryId) => $query->where('category_id', $categoryId))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($validated['perPage'] ?? 20)
            ->withQueryString();

        return ProductResource::collection($products);
    }

    /**
     * List the recently used products of the authenticated user.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  GetRecentProducts  $action  The action that retrieves the recent products.
     * @return AnonymousResourceCollection The collection of recent product resources.
     */
    public function recent(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'household_id' => ['sometimes', 'uuid', 'exists:households,id'],
            'limit' => ['sometimes', 'integer', 'between:1,50'],
        ]);
        $user = $this->authenticatedUser($request);

        if (isset($validated['household_id'])) {
            $household = Household::query()->findOrFail($validated['household_id']);
            $this->authorize('view', $household);
        }

        $products = ProductUsage::query()
            ->with(['product' => fn ($query) => $query->withTranslationData(), 'product.category' => fn ($query) => $query->withTranslationData()])
            ->where('user_id', $user->id)
            ->when($validated['household_id'] ?? null, fn ($query, string $householdId) => $query->whereHas('list', fn ($listQuery) => $listQuery->where('household_id', $householdId)))
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->limit($validated['limit'] ?? 20)
            ->get()
            ->pluck('product')
            ->filter()
            ->unique('id')
            ->values();

        return ProductResource::collection($products);
    }

    /**
     * Quickly create a product from the given name.
     *
     * @param  QuickCreateProductRequest  $request  The validated request containing the product name.
     * @param  QuickCreateProduct  $action  The action that creates the product.
     * @return JsonResponse The JSON response with the created product resource.
     */
    public function quickCreate(QuickCreateProductRequest $request, QuickCreateProduct $action): JsonResponse
    {
        $product = $action->execute(
            $request->input('name'),
            $this->authenticatedUser($request)->id
        );

        return ProductResource::make($product)
            ->response()
            ->setStatusCode(201);
    }
}
