<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Categories\CreateCategory;
use App\Actions\Categories\DeleteCategory;
use App\Actions\Categories\UpdateCategory;
use App\Data\Categories\CreateCategoryData;
use App\Data\Categories\UpdateCategoryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\Categories\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Handles the API category CRUD.
 */
final class CategoryController extends Controller
{
    /**
     * List categories with optional search.
     *
     * @param  Request  $request  The incoming HTTP request, optionally containing a search term.
     * @return AnonymousResourceCollection The paginated collection of CategoryResource instances.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $query = Category::withTranslationData()->withCount('products');

        if ($search = $validated['search'] ?? null) {
            $query->where('name', 'like', "%{$search}%");
        }

        $categories = $query->orderBy('sort_order')->orderBy('id')
            ->paginate($validated['perPage'] ?? 15)
            ->withQueryString();

        return CategoryResource::collection($categories);
    }

    /**
     * Create a new category.
     *
     * @param  StoreCategoryRequest  $request  The validated request with the category data.
     * @param  CreateCategory  $action  The action that creates the category.
     * @return JsonResponse The JSON response with the created CategoryResource.
     */
    public function store(StoreCategoryRequest $request, CreateCategory $action): JsonResponse
    {
        $data = CreateCategoryData::fromArray($request->validated());
        $category = $action->execute($data);

        return CategoryResource::make($category)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a category.
     *
     * @param  UpdateCategoryRequest  $request  The validated request with the category data.
     * @param  Category  $category  The category to update.
     * @param  UpdateCategory  $action  The action that updates the category.
     * @return JsonResponse The JSON response with the updated CategoryResource.
     */
    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategory $action): JsonResponse
    {
        $data = UpdateCategoryData::fromArray($request->validated());
        $category = $action->execute($category, $data);

        return CategoryResource::make($category)->response();
    }

    /**
     * Delete a category.
     *
     * @param  Category  $category  The category to delete.
     * @param  DeleteCategory  $action  The action that deletes the category.
     * @return Response|JsonResponse A 204 no-content response, or a 422 JSON error if the category is in use.
     */
    public function destroy(Category $category, DeleteCategory $action): Response|JsonResponse
    {
        $deleted = $action->execute($category);

        if (! $deleted) {
            return response()->json([
                'message' => __('app.toast.category_delete_failed'),
                'code' => 'category_in_use',
                'errors' => (object) [],
            ], 422);
        }

        return response()->noContent();
    }
}
