<?php

namespace App\Http\Controllers\Web;

use App\Actions\Recipes\CreateRecipe;
use App\Actions\Recipes\DeleteRecipe;
use App\Actions\Recipes\GetRecipe;
use App\Actions\Recipes\ListRecipes;
use App\Actions\Recipes\UpdateRecipe;
use App\Data\Recipes\CreateRecipeData;
use App\Data\Recipes\RecipeData;
use App\Enums\AiProviderModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeCollection;
use HomeSide\AiAgents\Models\AiGlobalSetting;
use HomeSide\AiAgents\Providers\ProviderResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the user's recipe CRUD.
 */
final class RecipeController extends Controller
{
    /**
     * List the user's recipes with filters.
     *
     * @param  ListRecipes  $action  The action responsible for the operation.
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The HTTP response.
     */
    public function index(ListRecipes $action, Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        $filters = $request->only(['search', 'tag', 'difficulty', 'cuisine', 'collection']);

        return Inertia::render('recipes/Index', [
            'recipes' => fn () => $action->execute($user, $filters),
            'filters' => $filters,
            'collections' => fn () => RecipeCollection::query()
                ->where('owner_id', $user->id)
                ->orderBy('path')
                ->get(['id', 'path']),
        ]);
    }

    /**
     * Show the recipe creation form.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The HTTP response.
     */
    public function create(Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        $householdId = $user->active_household_id;
        $resolver = app(ProviderResolver::class);
        $hasAiProvider = $resolver->resolve('recipes', $user->id, $householdId) !== null;

        $aiExtraPrompt = $this->getCombinedExtraPrompt(AiProviderModule::Recipes);

        // Receta pre-generada por el Assistant (vía query param ?recipe=key)
        $draft = null;
        if ($request->filled('recipe')) {
            $draft = $request->session()->pull("ai_recipe_{$request->input('recipe')}");
        }

        return Inertia::render('recipes/Create', [
            'products' => fn () => Product::query()
                ->select('id', 'name', 'is_personal')
                ->withTranslationData()
                ->where('is_personal', false)
                ->orWhere('created_by', $user->id)
                ->get()
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->localized('name'),
                    'is_personal' => $product->is_personal,
                ])
                ->values(),
            'hasAiProvider' => $hasAiProvider,
            'aiExtraPrompt' => $aiExtraPrompt,
            'draft' => $draft,
            'referenceRecipes' => fn () => Recipe::query()
                ->where('owner_id', $user->id)
                ->with('collection')
                ->orderBy('name')
                ->get(['id', 'name', 'collection_id']),
            'collections' => fn () => RecipeCollection::query()
                ->where('owner_id', $user->id)
                ->orderBy('path')
                ->get(['id', 'path']),
        ]);
    }

    /**
     * Create a new recipe.
     *
     * @param  StoreRecipeRequest  $request  The incoming HTTP request.
     * @param  CreateRecipe  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function store(StoreRecipeRequest $request, CreateRecipe $action): RedirectResponse
    {
        $data = CreateRecipeData::fromArray($request->validated());
        $user = $this->authenticatedUser($request);

        $recipe = $action->execute($data, $user);

        return redirect()->route('recipes.show', $recipe)
            ->with('toast', ['type' => 'success', 'message' => 'Receta creada correctamente.']);
    }

    /**
     * Show a recipe's detail page.
     *
     * @param  Recipe  $recipe  The recipe model instance.
     * @param  GetRecipe  $action  The action responsible for the operation.
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The HTTP response.
     */
    public function show(Recipe $recipe, GetRecipe $action, Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        abort_unless($user->can('view', $recipe), 403);

        $result = $action->execute($recipe);

        $recipe->load('households');

        return Inertia::render('recipes/Show', [
            ...$result,
            'can' => [
                'update' => $user->can('update', $recipe),
                'delete' => $user->can('delete', $recipe),
                'share' => $user->can('share', $recipe),
                'fork' => $user->can('fork', $recipe),
            ],
        ]);
    }

    /**
     * Show the recipe editing form.
     *
     * @param  Recipe  $recipe  The recipe model instance.
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The HTTP response.
     */
    public function edit(Recipe $recipe, Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        abort_unless($user->can('update', $recipe), 403);

        $recipe->load(['sections', 'ingredients.product', 'steps.timers', 'steps.recipeReferences.referencedRecipe', 'cookware', 'tags', 'collection']);

        return Inertia::render('recipes/Edit', [
            'recipe' => $recipe,
            'products' => fn () => Product::query()
                ->select('id', 'name', 'is_personal')
                ->withTranslationData()
                ->where('is_personal', false)
                ->orWhere('created_by', $user->id)
                ->get()
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->localized('name'),
                    'is_personal' => $product->is_personal,
                ])
                ->values(),
            'referenceRecipes' => fn () => Recipe::query()
                ->where('owner_id', $user->id)
                ->whereKeyNot($recipe->id)
                ->with('collection')
                ->orderBy('name')
                ->get(['id', 'name', 'collection_id']),
            'collections' => fn () => RecipeCollection::query()
                ->where('owner_id', $user->id)
                ->orderBy('path')
                ->get(['id', 'path']),
        ]);
    }

    /**
     * Update an existing recipe.
     *
     * @param  UpdateRecipeRequest  $request  The incoming HTTP request.
     * @param  Recipe  $recipe  The recipe model instance.
     * @param  UpdateRecipe  $action  The action responsible for the operation.
     * @return RedirectResponse The HTTP response.
     */
    public function update(UpdateRecipeRequest $request, Recipe $recipe, UpdateRecipe $action): RedirectResponse
    {
        abort_unless($this->authenticatedUser($request)->can('update', $recipe), 403);

        $data = RecipeData::fromArray(array_merge($request->validated(), ['id' => $recipe->id]));
        $action->execute($recipe, $data);

        return redirect()->route('recipes.show', $recipe)
            ->with('toast', ['type' => 'success', 'message' => 'Receta actualizada correctamente.']);
    }

    /**
     * Delete a recipe.
     *
     * @param  Recipe  $recipe  The recipe model instance.
     * @param  DeleteRecipe  $action  The action responsible for the operation.
     * @param  Request  $request  The incoming HTTP request.
     * @return RedirectResponse The HTTP response.
     */
    public function destroy(Recipe $recipe, DeleteRecipe $action, Request $request): RedirectResponse
    {
        abort_unless($this->authenticatedUser($request)->can('delete', $recipe), 403);

        $action->execute($recipe);

        return redirect()->route('recipes.index')
            ->with('toast', ['type' => 'success', 'message' => 'Receta eliminada correctamente.']);
    }

    /**
     * Show the recipe in cooking mode.
     *
     * @param  Recipe  $recipe  The recipe model instance.
     * @param  GetRecipe  $action  The action responsible for the operation.
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The HTTP response.
     */
    public function cook(Recipe $recipe, GetRecipe $action, Request $request): Response
    {
        abort_unless($this->authenticatedUser($request)->can('view', $recipe), 403);

        $result = $action->execute($recipe);

        return Inertia::render('recipes/Cook', [
            ...$result,
        ]);
    }

    /**
     * Get the combined extra prompt: module prompt plus global prompt.
     */
    private function getCombinedExtraPrompt(AiProviderModule $module): ?string
    {
        $parts = [];

        $modulePrompt = AiGlobalSetting::forModule($module->value)->value('extra_prompt');
        if ($modulePrompt !== null && $modulePrompt !== '') {
            $parts[] = $modulePrompt;
        }

        $globalPrompt = AiGlobalSetting::global()->value('extra_prompt');
        if ($globalPrompt !== null && $globalPrompt !== '') {
            $parts[] = $globalPrompt;
        }

        return $parts !== [] ? implode("\n\n", $parts) : null;
    }
}
