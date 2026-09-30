<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Recipes\GetRecipe;
use App\Data\Recipes\RecipeData;
use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Services\Recipes\Exporter\CooklangRecipeExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Exports authorized recipes through the API.
 */
final class RecipeExportController extends Controller
{
    /**
     * Download a recipe in Cooklang format.
     */
    public function cooklang(
        Recipe $recipe,
        GetRecipe $action,
        CooklangRecipeExporter $exporter,
        Request $request,
    ): Response {
        $this->authorize('export', $recipe);

        $recipe = $action->execute($recipe)['recipe'];
        $data = RecipeData::fromModel($recipe);

        $filename = Str::slug($data->name).'.cook';
        $content = $exporter->export($data);

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Content-Length', (string) strlen($content))
            ->header('Content-Disposition', 'attachment; filename="'.($filename === '.cook' ? 'recipe.cook' : $filename).'"');
    }
}
