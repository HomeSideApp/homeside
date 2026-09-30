<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecipeCollectionRequest;
use App\Http\Requests\UpdateRecipeCollectionRequest;
use App\Models\RecipeCollection;
use App\Services\Recipes\ManageRecipeCollections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RecipeCollectionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->authenticatedUser($request);

        return Inertia::render('recipes/collections/Index', [
            'collections' => RecipeCollection::query()
                ->where('owner_id', $user->id)
                ->with(['recipes' => fn ($query) => $query
                    ->select('id', 'name', 'collection_id')
                    ->orderBy('name')])
                ->withCount('recipes')
                ->orderBy('path')
                ->get(),
        ]);
    }

    public function store(StoreRecipeCollectionRequest $request, ManageRecipeCollections $collections): RedirectResponse
    {
        $collections->create(
            $this->authenticatedUser($request),
            $request->validated('name'),
            $request->validated('parent_id'),
        );

        return back()->with('toast', ['type' => 'success', 'message' => __('app.toast.collection_created')]);
    }

    public function update(
        UpdateRecipeCollectionRequest $request,
        RecipeCollection $recipeCollection,
        ManageRecipeCollections $collections,
    ): RedirectResponse {
        $this->ensureOwner($request, $recipeCollection);
        $collections->rename($recipeCollection, $request->validated('name'));

        return back()->with('toast', ['type' => 'success', 'message' => __('app.toast.collection_renamed')]);
    }

    public function destroy(Request $request, RecipeCollection $recipeCollection, ManageRecipeCollections $collections): RedirectResponse
    {
        $this->ensureOwner($request, $recipeCollection);
        $collections->delete($recipeCollection);

        return back()->with('toast', ['type' => 'success', 'message' => __('app.toast.collection_deleted')]);
    }

    private function ensureOwner(Request $request, RecipeCollection $collection): void
    {
        abort_unless($collection->owner_id === $this->authenticatedUser($request)->id, 403);
    }
}
