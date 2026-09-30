<?php

namespace App\Http\Controllers\Web;

use App\Actions\Products\CreateProduct;
use App\Actions\Products\DeleteProduct;
use App\Actions\Products\ListProducts;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the product CRUD.
 */
final class ProductController extends Controller
{
    /**
     * List all products.
     *
     * @param  ListProducts  $action  The action responsible for the operation.
     * @return Response The HTTP response.
     */
    public function index(ListProducts $action): Response
    {
        return Inertia::render('products/Index', [
            'products' => $action->execute(),
        ]);
    }

    /**
     * Create a new product.
     *
     * @param  StoreProductRequest  $request  The incoming HTTP request.
     * @param  CreateProduct  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function store(StoreProductRequest $request, CreateProduct $action): RedirectResponse
    {
        $action->execute($request->validated(), $this->authenticatedUser($request)->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producto creado correctamente.']);

        return back();
    }

    /**
     * Delete a product.
     *
     * @param  Product  $product  The product model instance.
     * @param  DeleteProduct  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function destroy(Product $product, DeleteProduct $action): RedirectResponse
    {
        $action->execute($product);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producto eliminado correctamente.']);

        return back();
    }
}
