<?php

namespace App\Http\Controllers\Web;

use App\Actions\Products\CreatePersonalProduct;
use App\Data\Products\CreatePersonalProductData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePersonalProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;

/**
 * Handles creating personal products from the recipe editor.
 */
final class PersonalProductController extends Controller
{
    /**
     * Store a newly created personal product.
     */
    public function store(
        StorePersonalProductRequest $request,
        CreatePersonalProduct $action,
    ): RedirectResponse {
        $data = CreatePersonalProductData::fromArray($request->validated());
        $product = $action->execute($data, $this->authenticatedUser($request)->id);

        return back()->with('toast', [
            'type' => 'success',
            'message' => __('app.toast.personal_product_created', ['name' => $product->name]),
        ])->with('newProduct', [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'icon' => $product->icon,
            'is_personal' => true,
        ]);
    }
}
