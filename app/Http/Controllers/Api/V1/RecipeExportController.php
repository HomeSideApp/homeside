<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Recipes\GetRecipe;
use App\Data\Recipes\RecipeData;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExportRecipeRequest;
use App\Models\Recipe;
use App\Services\Recipes\Exporter\CooklangRecipeExporter;
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
        ExportRecipeRequest $request,
    ): Response {

        $recipe = $action->execute($recipe, $this->authenticatedUser($request))['recipe'];
        $data = RecipeData::fromModel($recipe);

        $filename = Str::slug($data->name).'.cook';
        $content = $exporter->export($data);

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Content-Length', (string) strlen($content))
            ->header('Content-Disposition', 'attachment; filename="'.($filename === '.cook' ? 'recipe.cook' : $filename).'"');
    }
}
