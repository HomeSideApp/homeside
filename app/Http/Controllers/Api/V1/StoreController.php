<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexStoresRequest;
use App\Http\Resources\ShoppingLists\StoreResource;
use App\Models\Household;
use App\Models\Store;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StoreController extends Controller
{
    public function index(IndexStoresRequest $request, Household $household): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $stores = Store::query()
            ->withTranslationData()
            ->when($validated['search'] ?? null, fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($validated['perPage'] ?? 15)
            ->withQueryString();

        return StoreResource::collection($stores);
    }
}
