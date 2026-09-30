<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Products\CreatePersonalProduct;
use App\Data\Products\CreatePersonalProductData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePersonalProductApiRequest;
use App\Http\Resources\Products\PersonalProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * API endpoints for managing personal products.
 */
final class PersonalProductController extends Controller
{
    /**
     * List personal products visible to the authenticated user.
     *
     * Shows the user's own personal products plus any personal products
     * that have been added to lists in the active household.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $userId = $this->authenticatedUser($request)->id;
        $householdId = $request->user()->active_household_id;

        $products = Product::where('is_personal', true)
            ->where(function ($query) use ($userId, $householdId) {
                // User's own personal products
                $query->where('created_by', $userId)
                    // Or personal products already in a list item in this household
                    ->orWhere(function ($q) use ($householdId) {
                        if ($householdId === null) {
                            return;
                        }

                        $q->whereIn('id', function ($subQuery) use ($householdId) {
                            $subQuery->select('product_id')
                                ->from('list_items')
                                ->whereNotNull('product_id')
                                ->whereIn('list_id', function ($listQuery) use ($householdId) {
                                    $listQuery->select('id')
                                        ->from('shopping_lists')
                                        ->where('household_id', $householdId);
                                });
                        });
                    });
            })
            ->with('category')
            ->orderBy('name')
            ->paginate($request->input('perPage', 15));

        return PersonalProductResource::collection($products);
    }

    /**
     * Store a newly created personal product.
     */
    public function store(
        StorePersonalProductApiRequest $request,
        CreatePersonalProduct $action,
    ): JsonResponse {
        $data = CreatePersonalProductData::fromArray($request->validated());
        $product = $action->execute($data, $this->authenticatedUser($request)->id);

        return PersonalProductResource::make($product)
            ->response()
            ->setStatusCode(201);
    }
}
