<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\DeleteProduct;
use App\Actions\Products\UpdateProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\Products\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Class ProductController
 *
 * This controller handles the API endpoints for managing products, including
 * listing, creating, updating and deleting products.
 */
final class ProductController extends Controller
{
    /**
     * List products, optionally filtered by search text.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'category_id' => ['sometimes', 'uuid', 'exists:categories,id'],
            'is_active' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', 'in:name,created_at,updated_at'],
            'direction' => ['sometimes', 'in:asc,desc'],
        ]);
        $query = Product::withTranslationData()->with(['category' => fn ($categoryQuery) => $categoryQuery->withTranslationData()]);

        if ($search = $validated['search'] ?? null) {
            $query->where('name', 'like', "%{$search}%");
        }

        $query->when($validated['category_id'] ?? null, fn ($query, string $categoryId) => $query->where('category_id', $categoryId));
        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', $validated['is_active']);
        }

        $products = $query->orderBy($validated['sort'] ?? 'name', $validated['direction'] ?? 'asc')
            ->orderBy('id')
            ->paginate($validated['perPage'] ?? 15)
            ->withQueryString();

        return ProductResource::collection($products);
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request, CreateProduct $action): JsonResponse
    {
        $product = $action->execute($request->validated(), $this->authenticatedUser($request)->id);

        return ProductResource::make($product)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the given product.
     *
     * @param  UpdateAdminProductRequest  $request  The incoming HTTP request.
     * @param  Product  $product  The product model instance.
     * @param  UpdateProduct  $action  The action responsible for the operation.
     * @return JsonResponse The JSON response.
     */
    public function update(UpdateAdminProductRequest $request, Product $product, UpdateProduct $action): JsonResponse
    {
        $product = $action->execute($product, $request->validated());

        return ProductResource::make($product)->response();
    }

    /**
     * Delete the given product.
     *
     * @param  Product  $product  The product model instance.
     * @param  DeleteProduct  $action  The action responsible for the operation.
     * @return Response|JsonResponse The JSON response.
     */
    public function destroy(Product $product, DeleteProduct $action): Response|JsonResponse
    {
        $deleted = $action->execute($product);

        if (! $deleted) {
            return response()->json([
                'message' => __('app.toast.product_delete_failed'),
                'code' => 'product_in_use',
                'errors' => (object) [],
            ], 422);
        }

        return response()->noContent();
    }
}
