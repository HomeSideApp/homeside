<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Icons\ListIcons;
use App\Actions\Products\CreateProduct;
use App\Actions\Products\DeleteProduct;
use App\Actions\Products\UpdateProduct;
use App\Ai\ImageGenerationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminProductRequest;
use App\Http\Requests\Admin\UpdateAdminProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminProductController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Product::with('category');

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($category = $request->input('category')) {
            $query->where('category_id', $category);
        }

        $products = $query->orderBy('name')
            ->paginate($request->input('perPage', 10))
            ->withQueryString();

        return Inertia::render('admin/Products/Index', [
            'products' => $products,
            'categories' => Category::orderBy('sort_order')->get(['id', 'name']),
            'filters' => $request->only(['search', 'perPage', 'category']),
        ]);
    }

    public function create(ListIcons $listIcons): Response
    {
        return Inertia::render('admin/Products/Create', [
            'categories' => Category::orderBy('sort_order')->get(),
            'icons' => Inertia::optional(fn () => $listIcons->execute())->once(),
        ]);
    }

    public function store(StoreAdminProductRequest $request, CreateProduct $action): RedirectResponse
    {
        $userId = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($userId), 401);

        $action->execute($request->validated(), $userId);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producto creado correctamente.']);

        return to_route('admin.products');
    }

    public function edit(Product $product, ListIcons $listIcons): Response
    {
        return Inertia::render('admin/Products/Edit', [
            'product' => $product->load('category'),
            'categories' => Category::orderBy('sort_order')->get(),
            'icons' => Inertia::optional(fn () => $listIcons->execute())->once(),
        ]);
    }

    public function update(UpdateAdminProductRequest $request, Product $product, UpdateProduct $action): RedirectResponse
    {
        $action->execute($product, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producto actualizado correctamente.']);

        return to_route('admin.products');
    }

    public function destroy(Product $product, DeleteProduct $action): RedirectResponse
    {
        $deleted = $action->execute($product);

        if (! $deleted) {
            return back()->withErrors([
                'product' => __('app.toast.product_delete_failed'),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('app.toast.product_deleted')]);

        return to_route('admin.products');
    }

    public function pending(): Response
    {
        $products = Product::where('needs_image', true)
            ->where('is_approved', false)
            ->with('images')
            ->get();

        return Inertia::render('admin/Products/Pending', [
            'products' => $products,
        ]);
    }

    public function generateImages(Product $product, ImageGenerationService $imageService): RedirectResponse
    {
        $images = $imageService->generateForProduct($product);

        foreach ($images as $image) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => $image['url'],
                'is_ai_generated' => true,
                'prompt' => "Icon of {$product->name}",
            ]);
        }

        return back();
    }

    public function approveImage(ProductImage $image): RedirectResponse
    {
        ProductImage::where('product_id', $image->product_id)
            ->update(['is_selected' => false]);

        $image->update(['is_selected' => true]);
        $product = $image->product;
        abort_if($product === null, 404);

        $product->update([
            'image_url' => $image->image_url,
            'is_approved' => true,
            'needs_image' => false,
        ]);

        return back();
    }
}
