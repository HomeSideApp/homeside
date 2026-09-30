<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Categories\CreateCategory;
use App\Actions\Categories\DeleteCategory;
use App\Actions\Categories\UpdateCategory;
use App\Data\Categories\CreateCategoryData;
use App\Data\Categories\UpdateCategoryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the admin category CRUD.
 */
class AdminCategoryController extends Controller
{
    /**
     * List categories with optional search.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The HTTP response.
     */
    public function index(Request $request): Response
    {
        $query = Category::withCount('products');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $categories = $query->orderBy('sort_order')
            ->paginate($request->input('perPage', 10))
            ->withQueryString();

        return Inertia::render('admin/Categories/Index', [
            'categories' => $categories,
            'filters' => $request->only(['search', 'perPage']),
        ]);
    }

    /**
     * Show the category creation form.
     *
     * @return Response The HTTP response.
     */
    public function create(): Response
    {
        return Inertia::render('admin/Categories/Create');
    }

    /**
     * Create a new category.
     *
     * @param  StoreCategoryRequest  $request  The incoming HTTP request.
     * @param  CreateCategory  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function store(StoreCategoryRequest $request, CreateCategory $action): RedirectResponse
    {
        $data = CreateCategoryData::fromArray($request->validated());
        $action->execute($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.category_created')]);

        return to_route('admin.categories');
    }

    /**
     * Show the category editing form.
     *
     * @param  Category  $category  The category model instance.
     * @return Response The HTTP response.
     */
    public function edit(Category $category): Response
    {
        return Inertia::render('admin/Categories/Edit', [
            'category' => $category,
        ]);
    }

    /**
     * Update a category.
     *
     * @param  UpdateCategoryRequest  $request  The incoming HTTP request.
     * @param  Category  $category  The category model instance.
     * @param  UpdateCategory  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategory $action): RedirectResponse
    {
        $data = UpdateCategoryData::fromArray($request->validated());
        $action->execute($category, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.category_updated')]);

        return to_route('admin.categories');
    }

    /**
     * Delete a category.
     *
     * @param  Category  $category  The category model instance.
     * @param  DeleteCategory  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function destroy(Category $category, DeleteCategory $action): RedirectResponse
    {
        $deleted = $action->execute($category);

        if (! $deleted) {
            return back()->withErrors([
                'category' => __('app.toast.category_delete_failed'),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.category_deleted')]);

        return to_route('admin.categories');
    }
}
