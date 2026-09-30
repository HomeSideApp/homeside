<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShoppingLists\StoreResource;
use App\Models\Household;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StoreController extends Controller
{
    public function index(Request $request, Household $household): AnonymousResourceCollection
    {
        $this->authorize('view', $household);
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'between:1,100'],
        ]);

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
