<?php

namespace App\Http\Controllers\Web;

use App\Actions\Recipes\PreviewJsonLdRecipeImport;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\RecipeCookware;
use App\Services\Recipes\Import\CooklangRecipeImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class RecipeImportController extends Controller
{
    public function create(): InertiaResponse
    {
        return Inertia::render('recipes/Import');
    }

    public function previewJsonLd(Request $request, PreviewJsonLdRecipeImport $import): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $url = $request->input('url');
        $outcome = $import->execute($url);

        if ($outcome['error'] !== null) {
            Log::warning('RecipeImport: JSON-LD preview failed', [
                'url' => $url,
                'error' => $outcome['error'],
            ]);

            return response()->json([
                'data' => [
                    'recipe' => null,
                    'warnings' => [],
                    'matches' => [],
                ],
                'error' => $outcome['error'],
            ], 422);
        }

        $result = $outcome['result'];

        return response()->json([
            'data' => [
                'recipe' => $result->recipe->toArray(),
                'warnings' => array_map(fn ($w) => ['code' => $w->code, 'message' => $w->message, 'level' => $w->level], $result->warnings),
                'matches' => $result->candidates,
            ],
        ]);
    }

    public function previewCooklang(Request $request, CooklangRecipeImporter $importer): JsonResponse
    {
        $request->validate([
            'cooklang' => 'required|string|min:10',
        ]);

        $content = $request->input('cooklang');
        $result = $importer->import($content);

        return response()->json([
            'data' => [
                'recipe' => $result->recipe->toArray(),
                'warnings' => array_map(fn ($w) => ['code' => $w->code, 'message' => $w->message, 'level' => $w->level], $result->warnings),
                'matches' => $result->candidates,
            ],
        ]);
    }

    public function review(Request $request): InertiaResponse|RedirectResponse
    {
        if ($request->isMethod('POST')) {
            $request->validate([
                'recipe' => 'required|json',
            ]);

            $recipeData = json_decode($request->input('recipe'), true, 512, JSON_THROW_ON_ERROR);
            session(['import_recipe' => $recipeData]);

            return redirect()->route('recipes.import.review');
        }

        $recipeData = session('import_recipe');

        if (! $recipeData) {
            return redirect()->route('recipes.import');
        }

        return Inertia::render('recipes/ImportConfirm', [
            'recipe' => $recipeData,
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'cookware' => RecipeCookware::where('type', 'tool')->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
