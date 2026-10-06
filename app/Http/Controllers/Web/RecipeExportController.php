<?php

namespace App\Http\Controllers\Web;

use App\Actions\Recipes\GetRecipe;
use App\Data\Recipes\RecipeData;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExportRecipeRequest;
use App\Models\Recipe;
use App\Services\Recipes\Exporter\CooklangRecipeExporter;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

final class RecipeExportController extends Controller
{
    public function cooklang(Recipe $recipe, GetRecipe $action, CooklangRecipeExporter $exporter, ExportRecipeRequest $request): Response
    {

        $recipeData = $action->execute($recipe, $this->authenticatedUser($request))['recipe'];

        $recipeDataDto = RecipeData::fromModel($recipeData);

        $content = $exporter->export($recipeDataDto);

        $filename = Str::slug($recipeDataDto->name).'.cook';

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.($filename === '.cook' ? 'recipe.cook' : $filename).'"');
    }
}
